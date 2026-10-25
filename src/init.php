<?php
if(!file_exists(__DIR__ . '/../.env')){ header('Location: install.php'); exit; }

$env = [];
$lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0 || trim($line) === '') continue;
    list($name, $value) = explode('=', $line, 2);
    $name = trim($name); $value = trim($value, " \"'");
    $env[$name] = $value;
    @putenv("{$name}={$value}"); 
    $_ENV[$name] = $value;
}

define('SITE_NAME', $env['SITE_NAME'] ?? 'PHPSchool');
define('SITE_URL', $env['SITE_URL'] ?? 'http://localhost');
define('ADMIN_EMAIL', $env['ADMIN_EMAIL'] ?? 'admin@example.com');
define('ALLOW_REGISTER', ($env['ALLOW_REGISTER'] ?? 'true') === 'true');
define('REQUIRE_APPROVAL', ($env['REQUIRE_APPROVAL'] ?? 'true') === 'true');
define('REQUIRE_EMAIL', ($env['REQUIRE_EMAIL'] ?? 'false') === 'true');
define('LIMIT_PRIVATE_MSG', ($env['LIMIT_PRIVATE_MSG'] ?? 'true') === 'true');

define('DB_HOST', $env['DB_HOST'] ?? '');
define('DB_NAME', $env['DB_NAME'] ?? '');
define('DB_USER', $env['DB_USER'] ?? '');
define('DB_PASS', $env['DB_PASS'] ?? '');

define('AI_BASE_URL', $env['AI_BASE_URL'] ?? '');
define('AI_API_KEY', $env['AI_API_KEY'] ?? '');
define('AI_MODEL', $env['AI_MODEL'] ?? '');
define('HAS_AI', (AI_BASE_URL !== '' && AI_API_KEY !== ''));

if(session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Shanghai');
if(!headers_sent()) header('X-Frame-Options: DENY');

try{
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec("SET time_zone = '+08:00'");
}catch(PDOException $e){ 
    die('数据库连接失败，详细原因：' . $e->getMessage() . '<br><br>请检查你的 .env 文件是否正确，或者删掉 .env 和 install.lock 重新安装。'); 
}

$super_uid = 0;
try { $super_uid = (int)$pdo->query("SELECT MIN(uid) FROM users")->fetchColumn(); } catch (Exception $e) {}
define('SUPER_UID', $super_uid ?: 1);

// ★ 版本号 (测试版)
define('APP_VERSION', 'v0.0.1-2610251');

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
ensure_dirs();
