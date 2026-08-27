<?php
declare(strict_types=1);

/**
 * ตัวติดตั้งระบบ DVE PPP — รองรับการติดตั้งซ้ำ
 *
 * ขั้นตอน
 *   1 requirements — ตรวจรุ่น PHP, ส่วนขยาย, และสิทธิ์การเขียนไฟล์
 *   2 database     — เชื่อมต่อ MariaDB, สร้างฐานข้อมูลถ้ายังไม่มี, เลือก collation
 *   3 migrate      — รัน migration ที่ยังค้างอยู่
 *   4 admin        — เพิ่มบัญชีผู้ดูแลระบบ (บังคับเฉพาะการติดตั้งครั้งแรก)
 *   5 done
 *
 * การติดตั้งซ้ำ: เมื่อมี config อยู่แล้ว ต้องยืนยันด้วยบัญชีผู้ดูแลระบบเดิมก่อน
 * จึงจะแก้ไขการตั้งค่าได้ ข้อมูลเดิมจะไม่ถูกลบ — migration รันเฉพาะรุ่นที่ยังค้าง
 */

require __DIR__ . '/src/bootstrap.php';

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Migrator;
use App\Core\Requirements;

session_name('dveppp_install');
session_start();

$root       = __DIR__;
$installed  = Config::exists() && is_file($root . '/storage/installed.lock');
$errors     = [];
$notices    = [];
// ตั้งค่าเมื่อพบว่าฐานข้อมูลปลายทางมีข้อมูลของระบบเดิมอยู่แล้ว
$sharedWarning = null;
$migrateLog = [];

/** ผู้ติดตั้งซ้ำต้องยืนยันตัวตนก่อน */
$unlocked = !$installed || !empty($_SESSION['install_unlocked']);

$step = $_GET['step'] ?? ($installed && !$unlocked ? 'unlock' : 'requirements');
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

if ($post && !Csrf::check($_POST[Csrf::FIELD] ?? null)) {
    $errors[] = 'เซสชันหมดอายุ กรุณาส่งฟอร์มใหม่อีกครั้ง';
    $post = false;
}

// ---------------------------------------------------------------- unlock ----
if ($post && $step === 'unlock') {
    try {
        Database::connect();
        $row = Database::first('SELECT * FROM admins WHERE username = ? LIMIT 1', [trim((string) ($_POST['username'] ?? ''))]);
        $stored = (string) ($row['password'] ?? '');
        if ($row !== null && $stored !== '' && password_verify((string) ($_POST['password'] ?? ''), $stored)) {
            $_SESSION['install_unlocked'] = true;
            header('Location: install.php?step=requirements');
            exit;
        }
        $errors[] = 'ชื่อผู้ใช้หรือรหัสผ่านของผู้ดูแลระบบไม่ถูกต้อง';
    } catch (Throwable $e) {
        $errors[] = 'เชื่อมต่อฐานข้อมูลเดิมไม่สำเร็จ: ' . $e->getMessage()
                  . ' — หากต้องการติดตั้งใหม่ทั้งหมด ให้ลบไฟล์ storage/installed.lock';
    }
}

// ------------------------------------------------------------- database ----
if ($post && $step === 'database') {
    $db = [
        'host'     => trim((string) ($_POST['host'] ?? '127.0.0.1')),
        'port'     => (int) ($_POST['port'] ?? 3306),
        'database' => trim((string) ($_POST['database'] ?? '')),
        'username' => trim((string) ($_POST['username'] ?? 'root')),
        'password' => (string) ($_POST['password'] ?? ''),
        'charset'  => 'utf8mb4',
    ];

    if ($db['database'] === '' || !preg_match('/^[A-Za-z0-9_]+$/', $db['database'])) {
        $errors[] = 'ชื่อฐานข้อมูลต้องเป็นตัวอักษรภาษาอังกฤษ ตัวเลข หรือขีดล่างเท่านั้น';
    }

    if ($errors === []) {
        try {
            // เชื่อมต่อโดยยังไม่ระบุฐานข้อมูล เพื่อสร้างให้ถ้ายังไม่มี
            $server = Database::connect(['database' => ''] + $db);

            $versionCheck = Requirements::databaseServer($server);
            if (!$versionCheck['ok']) {
                $errors[] = 'รุ่นฐานข้อมูลไม่ผ่านเกณฑ์: พบ ' . $versionCheck['actual']
                          . ' — ต้องการ ' . $versionCheck['label'];
            }

            $picked = Requirements::pickCollation($server);
            $db['collation'] = $picked['collation'];

            if ($errors === []) {
                $server->exec(sprintf(
                    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE %s',
                    $db['database'],
                    $db['collation']
                ));

                if (!$picked['exact']) {
                    $notices[] = 'เซิร์ฟเวอร์นี้ไม่รองรับ collation utf8mb4_thai_520_w2 '
                               . 'ที่ production ใช้อยู่ ระบบจึงใช้ ' . $db['collation']
                               . ' แทน — การเรียงลำดับภาษาไทยจะต่างจาก production เล็กน้อย '
                               . 'แต่ข้อมูลยังถูกต้องครบถ้วน';
                }

                Database::reset();
                $target = Database::connect($db, asDefault: true); // ยืนยันว่าเชื่อมต่อฐานข้อมูลจริงได้

                // กันติดตั้งทับฐานข้อมูลของระบบเดิมโดยไม่ตั้งใจ — ทั้งสองระบบ
                // ใช้ชื่อตารางชุดเดียวกัน และ 0004 จะสร้าง trigger ทับของเดิม
                $legacy = Requirements::legacyData($target);
                if ($legacy['shared'] && empty($_POST['confirm_shared_db'])) {
                    $summary = [];
                    foreach ($legacy['tables'] as $t => $rows) {
                        $summary[] = $t . ' (' . number_format($rows) . ' แถว)';
                    }
                    $sharedWarning = implode(', ', $summary);
                    $errors[] = 'ฐานข้อมูล "' . $db['database'] . '" มีข้อมูลของระบบเดิมอยู่แล้ว — '
                              . 'กรุณายืนยันการใช้ฐานข้อมูลร่วมกันก่อนติดตั้ง';
                }

                // เขียน config ต่อเมื่อผ่านการตรวจฐานข้อมูลร่วมแล้วเท่านั้น
                if ($errors === []) {
                    $config = Config::all();
                    $config['db']  = $db;
                    $config['app'] = ($config['app'] ?? []) + [
                        'name'                    => 'DVE PPP',
                        'debug'                   => false,
                        'session_name'            => 'dveppp_session',
                        'session_timeout_minutes' => 30,
                        'base_path'               => '',
                        'key'                     => bin2hex(random_bytes(16)),
                    ];
                    Config::write($config);

                    header('Location: install.php?step=migrate');
                    exit;
                }
            }
        } catch (Throwable $e) {
            $errors[] = 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage();
        }
    }
    $_SESSION['install_db'] = ['host' => $db['host'], 'port' => $db['port'], 'database' => $db['database'], 'username' => $db['username']];
}

// -------------------------------------------------------------- migrate ----
if ($post && $step === 'migrate') {
    try {
        Database::connect();
        $migrator   = new Migrator(Database::pdo());
        $migrateLog = $migrator->migrate('installer');
        $failed     = array_filter($migrateLog, static fn(array $l) => !$l['ok']);

        if ($failed === []) {
            header('Location: install.php?step=admin');
            exit;
        }
        foreach ($failed as $f) {
            $errors[] = 'migration ' . $f['version'] . ' ล้มเหลว: ' . $f['message'];
        }
    } catch (Throwable $e) {
        $errors[] = 'รัน migration ไม่สำเร็จ: ' . $e->getMessage();
    }
}

// ----------------------------------------------------------------- admin ----
if ($post && $step === 'admin') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.-]{4,100}$/', $username)) {
        $errors[] = 'ชื่อผู้ใช้ต้องยาว 4–100 ตัว ใช้ได้เฉพาะ A-Z a-z 0-9 . _ -';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
    }
    if ($password !== $confirm) {
        $errors[] = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
    }
    if ($password !== '' && $password === $username) {
        $errors[] = 'รหัสผ่านต้องไม่เหมือนกับชื่อผู้ใช้';
    }

    if ($errors === []) {
        try {
            Database::connect();
            $exists = Database::first('SELECT admin_id AS id FROM admins WHERE username = ?', [$username]);
            if ($exists !== null) {
                Database::run(
                    'UPDATE admins SET password = ?, admin_name = ? WHERE admin_id = ?',
                    [password_hash($password, PASSWORD_DEFAULT), $fullName ?: $username, $exists['id']]
                );
                $notices[] = 'มีผู้ใช้ชื่อนี้อยู่แล้ว — อัปเดตรหัสผ่านและข้อมูลให้เรียบร้อย';
            } else {
                Database::run(
                    'INSERT INTO admins (username, password, admin_name) VALUES (?,?,?)',
                    [$username, password_hash($password, PASSWORD_DEFAULT), $fullName ?: $username]
                );
            }

            @file_put_contents($root . '/storage/installed.lock', json_encode([
                'installed_at' => date('c'),
                'version'      => '1.0.0',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            header('Location: install.php?step=done');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'สร้างบัญชีผู้ดูแลระบบไม่สำเร็จ: ' . $e->getMessage();
        }
    }
}

// --------------------------------------------------------- page state ------
$sysChecks   = Requirements::system();
$dirChecks   = Requirements::writable();
$readyToGo   = Requirements::passes($sysChecks, $dirChecks);
$dbDefaults  = $_SESSION['install_db'] ?? [
    'host'     => (string) (Config::get('db.host') ?? '127.0.0.1'),
    'port'     => (int) (Config::get('db.port') ?? 3306),
    'database' => (string) (Config::get('db.database') ?? 'dve_ppp'),
    'username' => (string) (Config::get('db.username') ?? 'root'),
];

$migrationRows = [];
$adminCount    = null;
if (in_array($step, ['migrate', 'admin', 'done'], true) && Config::exists()) {
    try {
        Database::connect();
        $migrationRows = (new Migrator(Database::pdo()))->status();
        if (Database::tableExists('admins')) {
            $adminCount = Database::int('SELECT COUNT(*) FROM admins');
        }
    } catch (Throwable $e) {
        $errors[] = 'อ่านสถานะฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage();
    }
}

$steps = [
    'requirements' => 'ตรวจสอบระบบ',
    'database'     => 'ฐานข้อมูล',
    'migrate'      => 'โครงสร้างข้อมูล',
    'admin'        => 'ผู้ดูแลระบบ',
    'done'         => 'เสร็จสิ้น',
];
$stepIndex = array_search($step, array_keys($steps), true);
$stepIndex = $stepIndex === false ? 0 : (int) $stepIndex;

?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>ติดตั้งระบบ DVE PPP</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="install-body">

<div class="install-shell">
  <header class="install-header">
    <div class="install-logo">PPP</div>
    <div>
      <h1>ติดตั้งระบบ DVE PPP</h1>
      <p class="muted">ระบบฐานข้อมูลความต้องการกำลังคน เพื่อการจัดการอาชีวศึกษาระบบทวิภาคี ภายใต้ความร่วมมือระหว่างสถานประกอบการและสำนักงานคณะกรรมการการอาชีวศึกษา</p>
    </div>
    <?php if ($installed): ?>
      <span class="badge badge-warn nw">ติดตั้งแล้ว — โหมดติดตั้งซ้ำ</span>
    <?php endif; ?>
  </header>

  <?php if ($step !== 'unlock'): ?>
  <ol class="stepper" aria-label="ขั้นตอนการติดตั้ง">
    <?php $i = 0; foreach ($steps as $key => $label): $i++; ?>
      <li class="stepper-item <?= $key === $step ? 'is-current' : ($i - 1 < $stepIndex ? 'is-done' : '') ?>">
        <span class="stepper-dot"><?= $i - 1 < $stepIndex ? '✔' : $i ?></span>
        <span class="stepper-label"><?= e($label) ?></span>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>

  <?php foreach ($errors as $msg): ?>
    <div class="alert alert-err"><span aria-hidden="true">✕</span><div><?= e($msg) ?></div></div>
  <?php endforeach; ?>
  <?php foreach ($notices as $msg): ?>
    <div class="alert alert-info"><span aria-hidden="true">i</span><div><?= e($msg) ?></div></div>
  <?php endforeach; ?>

  <main class="card install-card">

  <?php if ($step === 'unlock'): ?>
    <h2>ยืนยันตัวตนก่อนติดตั้งซ้ำ</h2>
    <p class="muted">ระบบนี้ติดตั้งไว้แล้ว การติดตั้งซ้ำจะเขียนไฟล์ตั้งค่าใหม่และรัน migration
      ที่ยังค้างอยู่ <strong>ข้อมูลเดิมจะไม่ถูกลบ</strong> กรุณายืนยันด้วยบัญชีผู้ดูแลระบบ</p>
    <form method="post" action="install.php?step=unlock" class="form-grid">
      <?= Csrf::field() ?>
      <label class="field">
        <span>ชื่อผู้ใช้ผู้ดูแลระบบ</span>
        <input class="input" type="text" name="username" required autofocus autocomplete="username">
      </label>
      <label class="field">
        <span>รหัสผ่าน</span>
        <input class="input" type="password" name="password" required autocomplete="current-password">
      </label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">ยืนยันตัวตน</button>
        <a class="btn btn-ghost" href="<?= e(dirname($_SERVER['SCRIPT_NAME'] ?? '/')) ?>">กลับหน้าแรก</a>
      </div>
    </form>
    <p class="hint">ลืมรหัสผ่านผู้ดูแลระบบ? ลบไฟล์ <code>storage/installed.lock</code> บนเซิร์ฟเวอร์
      แล้วเปิดหน้านี้อีกครั้งเพื่อเริ่มติดตั้งใหม่</p>

  <?php elseif ($step === 'requirements'): ?>
    <h2>1 · ตรวจสอบความต้องการของระบบ</h2>
    <p class="muted">ต้องผ่านทุกข้อที่ทำเครื่องหมาย <strong>จำเป็น</strong> จึงจะติดตั้งต่อได้</p>

    <h3 class="section-title">สภาพแวดล้อม PHP</h3>
    <table class="table table-check">
      <thead><tr><th>รายการ</th><th>ค่าที่พบ</th><th class="nw">ผล</th></tr></thead>
      <tbody>
      <?php foreach ($sysChecks as $c): ?>
        <tr>
          <td>
            <?= e($c['label']) ?>
            <?php if ($c['required']): ?><span class="badge badge-mute">จำเป็น</span><?php endif; ?>
            <div class="hint"><?= e($c['hint']) ?></div>
          </td>
          <td class="mono"><?= e($c['actual']) ?></td>
          <td><?= $c['ok']
                ? '<span class="badge badge-ok">✔ ผ่าน</span>'
                : ($c['required'] ? '<span class="badge badge-err">✕ ไม่ผ่าน</span>' : '<span class="badge badge-warn">◐ แนะนำ</span>') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <h3 class="section-title">สิทธิ์การเขียนไฟล์</h3>
    <table class="table table-check">
      <thead><tr><th>โฟลเดอร์</th><th>ใช้ทำอะไร</th><th class="nw">ผล</th></tr></thead>
      <tbody>
      <?php foreach ($dirChecks as $c): ?>
        <tr>
          <td class="mono"><?= e($c['label']) ?></td>
          <td><?= e($c['hint']) ?><div class="hint"><?= e($c['actual']) ?></div></td>
          <td><?= $c['ok']
                ? '<span class="badge badge-ok">✔ เขียนได้</span>'
                : '<span class="badge badge-err">✕ เขียนไม่ได้</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <?php if (!$readyToGo): ?>
      <div class="alert alert-err">
        <span aria-hidden="true">✕</span>
        <div>ยังมีข้อที่จำเป็นไม่ผ่าน — แก้ไขแล้วกด “ตรวจสอบอีกครั้ง”
          บน Windows/XAMPP มักแก้ได้ด้วยการให้สิทธิ์เขียนแก่ผู้ใช้ที่รัน Apache</div>
      </div>
    <?php endif; ?>

    <div class="form-actions">
      <a class="btn btn-ghost" href="install.php?step=requirements">ตรวจสอบอีกครั้ง</a>
      <?php if ($readyToGo): ?>
        <a class="btn btn-primary" href="install.php?step=database">ถัดไป — ตั้งค่าฐานข้อมูล</a>
      <?php else: ?>
        <button class="btn btn-primary" disabled>ถัดไป — ตั้งค่าฐานข้อมูล</button>
      <?php endif; ?>
    </div>

  <?php elseif ($step === 'database'): ?>
    <h2>2 · ตั้งค่าฐานข้อมูล</h2>
    <p class="muted">ต้องการ MariaDB <?= e(Requirements::MARIADB_MIN) ?> ขึ้นไป (หรือ MySQL 8)
      ถ้ายังไม่มีฐานข้อมูลชื่อนี้ ระบบจะสร้างให้อัตโนมัติ</p>
    <form method="post" action="install.php?step=database" class="form-grid form-grid-2">
      <?= Csrf::field() ?>
      <label class="field">
        <span>โฮสต์</span>
        <input class="input" type="text" name="host" value="<?= e($dbDefaults['host']) ?>" required>
      </label>
      <label class="field">
        <span>พอร์ต</span>
        <input class="input" type="number" name="port" value="<?= e((string) $dbDefaults['port']) ?>" required>
      </label>
      <label class="field">
        <span>ชื่อฐานข้อมูล</span>
        <input class="input" type="text" name="database" value="<?= e($dbDefaults['database']) ?>" required>
      </label>
      <label class="field">
        <span>ชื่อผู้ใช้</span>
        <input class="input" type="text" name="username" value="<?= e($dbDefaults['username']) ?>" required autocomplete="off">
      </label>
      <label class="field field-wide">
        <span>รหัสผ่าน</span>
        <input class="input" type="password" name="password" autocomplete="new-password">
        <span class="hint">XAMPP ค่าเริ่มต้นคือผู้ใช้ root และรหัสผ่านว่าง</span>
      </label>
      <?php if ($sharedWarning !== null): ?>
        <div class="alert alert-info field-wide">
          <span aria-hidden="true">ℹ</span>
          <div>
            <strong>ใช้ฐานข้อมูลร่วมกับระบบเดิม</strong>
            <p>พบข้อมูลของระบบเดิมอยู่แล้ว: <?= e($sharedWarning) ?></p>
            <p><strong>ระบบจะเพิ่มเท่านั้น ไม่แก้ของเดิม</strong> — สร้างตารางของแอปใหม่
              (<code>app_settings</code>, <code>report_steps</code>, <code>share_links</code>,
              <code>ppp_enterprise_completeness</code>), เพิ่มคอลัมน์รหัสผ่านแบบเข้ารหัสให้
              <code>admins</code> กับ <code>provincial_vocational_offices</code>
              (คอลัมน์เดิมอยู่ครบ ระบบเดิมยังล็อกอินได้) และสร้าง view/procedure
              ที่ขึ้นต้นด้วย <code>v_ppp_</code> / <code>Ppp</code></p>
            <p><strong>สิ่งที่จะไม่ถูกแตะ:</strong> ตารางและข้อมูลเดิมทุกตาราง,
              trigger ของระบบเดิมทั้ง 3 ตัว, <code>SyncPveoEstateAssignments</code>,
              <code>RecalcEnterpriseCompleteness</code>, <code>v_estate_progress</code>
              และ <code>enterprise_completeness</code></p>
            <label class="check">
              <input type="checkbox" name="confirm_shared_db" value="1">
              <span>ยืนยันใช้ฐานข้อมูลนี้ร่วมกับระบบเดิม (แนะนำให้สำรองข้อมูลก่อน)</span>
            </label>
          </div>
        </div>
      <?php endif; ?>
      <div class="form-actions field-wide">
        <a class="btn btn-ghost" href="install.php?step=requirements">ย้อนกลับ</a>
        <button class="btn btn-primary" type="submit">ทดสอบการเชื่อมต่อและบันทึก</button>
      </div>
    </form>

  <?php elseif ($step === 'migrate'): ?>
    <h2>3 · สร้าง/ปรับปรุงโครงสร้างฐานข้อมูล</h2>
    <?php
      $pendingRows = array_filter($migrationRows, static fn(array $r) => $r['state'] === 'pending');
    ?>
    <p class="muted">
      พบ migration ทั้งหมด <?= count($migrationRows) ?> รุ่น ·
      รอดำเนินการ <strong><?= count($pendingRows) ?></strong> รุ่น
      <?php if ($installed): ?> — การติดตั้งซ้ำจะรันเฉพาะรุ่นที่ยังค้าง ไม่แตะข้อมูลเดิม<?php endif; ?>
    </p>

    <?php if ($migrateLog !== []): ?>
      <div class="log-box">
        <?php foreach ($migrateLog as $line): ?>
          <div class="log-line <?= $line['ok'] ? 'ok' : 'err' ?>">
            <?= $line['ok'] ? '✔' : '✕' ?> <?= e($line['version']) ?> ·
            <?= e($line['name']) ?> — <?= e($line['message']) ?>
            <?php if ($line['ok']): ?><span class="muted">(<?= (int) $line['ms'] ?> ms)</span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <table class="table">
      <thead><tr><th>รุ่น</th><th>ชื่อ</th><th>สถานะ</th><th class="nw">เมื่อ</th></tr></thead>
      <tbody>
      <?php foreach ($migrationRows as $row): ?>
        <tr>
          <td class="mono"><?= e($row['version']) ?></td>
          <td><?= e($row['name']) ?><div class="hint mono"><?= e($row['file']) ?></div></td>
          <td><?php
            echo match ($row['state']) {
                'applied' => '<span class="badge badge-ok">✔ ใช้งานแล้ว</span>',
                'pending' => '<span class="badge badge-warn">◐ รอดำเนินการ</span>',
                'drifted' => '<span class="badge badge-err">⚠ ไฟล์ถูกแก้ไข</span>',
                default   => '<span class="badge badge-mute">? ไม่พบไฟล์</span>',
            };
          ?></td>
          <td class="nw"><?= e(thai_date($row['applied_at'], true)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post" action="install.php?step=migrate" class="form-actions">
      <?= Csrf::field() ?>
      <a class="btn btn-ghost" href="install.php?step=database">ย้อนกลับ</a>
      <?php if ($pendingRows !== []): ?>
        <button class="btn btn-primary" type="submit">รัน migration ที่ค้างอยู่</button>
      <?php else: ?>
        <a class="btn btn-primary" href="install.php?step=admin">ถัดไป — บัญชีผู้ดูแลระบบ</a>
      <?php endif; ?>
    </form>

  <?php elseif ($step === 'admin'): ?>
    <h2>4 · บัญชีผู้ดูแลระบบ</h2>
    <?php if ($adminCount): ?>
      <div class="alert alert-info">
        <span aria-hidden="true">i</span>
        <div>มีบัญชีผู้ดูแลระบบอยู่แล้ว <strong><?= (int) $adminCount ?></strong> บัญชี
          — จะเพิ่มบัญชีใหม่ก็ได้ หรือข้ามขั้นตอนนี้ไปเลย
          (ถ้ากรอกชื่อผู้ใช้ที่มีอยู่แล้ว ระบบจะตั้งรหัสผ่านใหม่ให้บัญชีนั้น)</div>
      </div>
    <?php else: ?>
      <p class="muted">ยังไม่มีบัญชีผู้ดูแลระบบ — ต้องสร้างอย่างน้อยหนึ่งบัญชีจึงจะใช้งานระบบได้
        รหัสผ่านจะถูกเข้ารหัสด้วย bcrypt</p>
    <?php endif; ?>

    <form method="post" action="install.php?step=admin" class="form-grid form-grid-2">
      <?= Csrf::field() ?>
      <label class="field">
        <span>ชื่อผู้ใช้ <em class="req">*</em></span>
        <input class="input" type="text" name="username" required autocomplete="username"
               value="<?= e((string) ($_POST['username'] ?? '')) ?>">
      </label>
      <label class="field">
        <span>ชื่อ-นามสกุล</span>
        <input class="input" type="text" name="full_name" placeholder="ผู้ดูแลระบบ สอศ."
               value="<?= e((string) ($_POST['full_name'] ?? '')) ?>">
      </label>
      <label class="field">
        <span>รหัสผ่าน <em class="req">*</em></span>
        <input class="input" type="password" name="password" required minlength="8" autocomplete="new-password">
        <span class="hint">อย่างน้อย 8 ตัวอักษร และต้องไม่ซ้ำกับชื่อผู้ใช้</span>
      </label>
      <label class="field">
        <span>ยืนยันรหัสผ่าน <em class="req">*</em></span>
        <input class="input" type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
      </label>
      <div class="form-actions field-wide">
        <a class="btn btn-ghost" href="install.php?step=migrate">ย้อนกลับ</a>
        <?php if ($adminCount): ?>
          <a class="btn btn-secondary" href="install.php?step=done">ข้ามขั้นตอนนี้</a>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">บันทึกบัญชีผู้ดูแลระบบ</button>
      </div>
    </form>

  <?php else: ?>
    <h2>ติดตั้งเสร็จสมบูรณ์</h2>
    <p class="muted">ระบบพร้อมใช้งานแล้ว</p>
    <ul class="checklist">
      <li>✔ ไฟล์ตั้งค่าอยู่ที่ <code>config/config.php</code></li>
      <li>✔ โครงสร้างฐานข้อมูลอัปเดตครบทุก migration</li>
      <li>✔ มีบัญชีผู้ดูแลระบบ <?= $adminCount !== null ? (int) $adminCount . ' บัญชี' : '' ?></li>
    </ul>
    <div class="alert alert-warn">
      <span aria-hidden="true">⚠</span>
      <div><strong>สิ่งที่ควรทำต่อบนเซิร์ฟเวอร์จริง:</strong> ลบหรือปิดการเข้าถึงไฟล์
        <code>install.php</code> และนำเข้าข้อมูลจริง (77 จังหวัด, 75 นิคมฯ, 171 สาขา, บัญชี สอจ.)
        จากฐานข้อมูล production</div>
    </div>
    <div class="form-actions">
      <a class="btn btn-primary" href="<?= e(rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/')) ?>/">ไปหน้าแรกของระบบ</a>
      <a class="btn btn-ghost" href="<?= e(rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/')) ?>/login">เข้าสู่ระบบผู้ดูแล</a>
    </div>
  <?php endif; ?>

  </main>

  <footer class="install-footer muted">
    PHP <?= e(PHP_VERSION) ?> · <?= e(Config::exists() ? 'พบไฟล์ตั้งค่าแล้ว' : 'ยังไม่มีไฟล์ตั้งค่า') ?>
  </footer>
</div>

</body>
</html>
