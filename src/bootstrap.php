<?php
declare(strict_types=1);

/**
 * Shared bootstrap: autoloader, error handling, helpers.
 * Safe to require before the app has been installed - it never touches the
 * database and never assumes config/config.php exists.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_START', microtime(true));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require APP_ROOT . '/src/Support/helpers.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Bangkok');

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

App\Core\Config::load();
$debug = (bool) App\Core\Config::get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');

set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    if ($debug) {
        echo '<pre style="padding:16px;font:13px/1.6 monospace;white-space:pre-wrap">'
           . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }
    echo '<h1>500 — ระบบขัดข้อง</h1><p>เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่อีกครั้ง '
       . 'รายละเอียดถูกบันทึกไว้ใน storage/logs/php-error.log</p>';
});
