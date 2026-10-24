<?php
if(!file_exists(__DIR__ . '/../.env')){ header('Location: install.php'); exit; }

$lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0 || trim($line) === '') continue;
    list($name, $value) = explode('=', $line, 2);
    $name = trim($name); $value = trim($value, " \"'");
    if (!array_key_exists($name, $_ENV)) { putenv("{$name}={$value}"); $_ENV[$name] = $value; }
}

define('SITE_NAME', getenv('SITE_NAME') ?: 'CampusWall');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@example.com');
define('ALLOW_REGISTER', getenv('ALLOW_REGISTER') === 'true');
define('REQUIRE_APPROVAL', getenv('REQUIRE_APPROVAL') === 'true');
define('REQUIRE_EMAIL', getenv('REQUIRE_EMAIL') === 'true');
define('LIMIT_PRIVATE_MSG', getenv('LIMIT_PRIVATE_MSG') === 'true');

define('DB_HOST', getenv('DB_HOST')); define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER')); define('DB_PASS', getenv('DB_PASS'));

define('AI_BASE_URL', getenv('AI_BASE_URL') ?: ''); define('AI_API_KEY', getenv('AI_API_KEY') ?: '');
define('AI_MODEL', getenv('AI_MODEL') ?: '');
define('HAS_AI', (AI_BASE_URL !== '' && AI_API_KEY !== ''));

if(session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Shanghai');
if(!headers_sent()) header('X-Frame-Options: DENY');

try{
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec("SET time_zone = '+08:00'");
}catch(PDOException $e){ die('数据库连接失败'); }

$super_uid = 0;
try { $super_uid = (int)$pdo->query("SELECT MIN(uid) FROM users")->fetchColumn(); } catch (Exception $e) {}
define('SUPER_UID', $super_uid ?: 1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
ensure_dirs();
