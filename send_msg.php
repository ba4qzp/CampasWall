<?php
require_once __DIR__ . '/src/init.php';
if(empty($_SESSION['uid'])){ die('未登录'); }
$uid = (int)$_SESSION['uid'];
$to = (int)($_POST['to'] ?? 0);
$content = trim($_POST['content'] ?? '');
if($to && $content !== ''){
    $pdo->prepare('INSERT INTO messages (from_uid, to_uid, content) VALUES (?,?,?)')->execute([$uid, $to, $content]);
    $a = min($uid, $to); $b = max($uid, $to);
    $pdo->prepare('INSERT INTO friendships (uid1, uid2, status) VALUES (?,?,1) ON DUPLICATE KEY UPDATE status=1')->execute([$a, $b]);
}
header('Location: index1.php?with='.$to); exit;
