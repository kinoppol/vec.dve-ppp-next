<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\BoardController;
use App\Controllers\EnterpriseController;
use App\Controllers\MigrationController;
use App\Controllers\ProgressController;
use App\Controllers\PublicController;
use App\Controllers\SurveyController;
use App\Core\Config;
use App\Core\Context;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Router;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;

// ยังไม่ได้ติดตั้ง — ส่งไปหน้าติดตั้ง
if (!Config::exists()) {
    header('Location: ' . rtrim(dirname((string) $_SERVER['SCRIPT_NAME']), '/\\') . '/install.php');
    exit;
}

Database::connect();
Session::start();
Csrf::verify();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

View::share('appName', (string) \App\Core\Settings::get('site_name', 'DVE PPP'));
View::share('appTagline', (string) \App\Core\Settings::get('site_tagline', 'ระบบฐานข้อมูลความต้องการกำลังคน เพื่อการจัดการอาชีวศึกษาระบบทวิภาคี ภายใต้ความร่วมมือระหว่างสถานประกอบการและสำนักงานคณะกรรมการการอาชีวศึกษา'));
View::share('theme', Context::theme());
View::share('flash', Session::takeFlash());

$router = new Router();

// ------------------------------------------------------------- สาธารณะ ----
$router->get('',               [PublicController::class, 'home']);
$router->get('search',         [PublicController::class, 'search']);
$router->get('downloads',      [PublicController::class, 'downloads']);
$router->get('share/{token}',  [PublicController::class, 'shared']);

// --------------------------------------------------------- กระดานถามตอบ ----
// อ่านได้ทุกคน ตั้งกระทู้/ตอบต้องเข้าสู่ระบบ ลบได้เฉพาะผู้ดูแลระบบ
$router->get('board',                    [BoardController::class, 'index']);
$router->get('board/new',                [BoardController::class, 'create']);
$router->post('board/new',               [BoardController::class, 'store']);
$router->post('board/reply/{id}/delete', [BoardController::class, 'destroyReply']);
$router->get('board/{id}',               [BoardController::class, 'show']);
$router->post('board/{id}/reply',        [BoardController::class, 'reply']);
$router->post('board/{id}/delete',       [BoardController::class, 'destroy']);

// ----------------------------------------------------------- เข้าสู่ระบบ ----
$router->get('login',           [AuthController::class, 'showLogin']);
$router->post('login',          [AuthController::class, 'login']);
$router->post('logout',         [AuthController::class, 'logout']);
$router->get('password/change', [AuthController::class, 'showChangePassword']);
$router->post('password/change',[AuthController::class, 'changePassword']);
$router->post('password/skip',  [AuthController::class, 'postponePasswordChange']);
$router->post('context/year',   [AuthController::class, 'switchYear']);
$router->post('context/estate', [AuthController::class, 'switchEstate']);
$router->post('context/theme',  [AuthController::class, 'switchTheme']);
$router->get('estate/picker',   [AuthController::class, 'estatePicker']);

// ------------------------------------------------------------------ สอจ. ----
$router->get('pveo',                 [EnterpriseController::class, 'dashboard']);
$router->get('pveo/enterprises',     [EnterpriseController::class, 'index']);
$router->get('pveo/enterprises/new', [EnterpriseController::class, 'create']);
$router->post('pveo/enterprises/new',[EnterpriseController::class, 'store']);
$router->get('pveo/enterprises/{id}',[EnterpriseController::class, 'show']);
$router->get('pveo/survey/{id}',     [SurveyController::class, 'wizard']);
$router->post('pveo/survey/{id}',    [SurveyController::class, 'save']);
$router->get('pveo/progress',        [ProgressController::class, 'index']);
$router->post('pveo/progress/upload',[ProgressController::class, 'upload']);

// ---------------------------------------------------------- ผู้ดูแลระบบ ----
$router->get('admin',                   [AdminController::class, 'dashboard']);
$router->get('admin/estates',           [AdminController::class, 'estates']);
$router->get('admin/uploads',           [AdminController::class, 'uploads']);
$router->get('admin/assign',            [AdminController::class, 'assign']);
$router->post('admin/assign/quota',     [AdminController::class, 'saveQuota']);
$router->post('admin/assign/sync',      [AdminController::class, 'syncAssignments']);
$router->post('admin/impersonate',      [AdminController::class, 'impersonate']);
$router->post('admin/impersonate/stop', [AdminController::class, 'stopImpersonating']);
$router->get('admin/settings',          [AdminController::class, 'settings']);
$router->post('admin/settings',         [AdminController::class, 'saveSettings']);
$router->post('admin/share',            [AdminController::class, 'createShareLink']);

// ------------------------------------------------- เมนู Migration (Admin) ----
$router->get('admin/migrations',              [MigrationController::class, 'index']);
$router->get('admin/migrations/{version}',    [MigrationController::class, 'show']);
$router->post('admin/migrations/run',         [MigrationController::class, 'run']);
$router->post('admin/migrations/rollback',    [MigrationController::class, 'rollback']);
$router->post('admin/migrations/resync',      [MigrationController::class, 'resync']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', Url::current());
