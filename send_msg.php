<?php
require_once __DIR__ . '/src/init.php';
if(empty($_SESSION['uid'])){ die(json_encode(['ok'=>false, 'msg'=>'未登录'])); }
$uid = (int)$_SESSION['uid'];
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'recall') {
    $mid = (int)$_POST['mid'];
    $stmt = $pdo->prepare('SELECT from_uid FROM messages WHERE mid = ?'); $stmt->execute([$mid]); $from_uid = $stmt->fetchColumn();
    if ((int)$from_uid === $uid) {
        $pdo->prepare('UPDATE messages SET deleted = 1 WHERE mid = ?')->execute([$mid]);
        echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false, 'msg'=>'无权撤回']); exit;
}

if ($action === 'forward') {
    $mid = (int)$_POST['mid'];
    $to = (int)$_POST['to'];
    $stmt = $pdo->prepare('SELECT content, file_path, file_name, file_size FROM messages WHERE mid = ? AND deleted = 0'); $stmt->execute([$mid]); $m = $stmt->fetch();
    if ($m) {
        $pdo->prepare('INSERT INTO messages (from_uid, to_uid, content, file_path, file_name, file_size) VALUES (?,?,?,?,?,?)')
            ->execute([$uid, $to, $m['content'], $m['file_path'], $m['file_name'], $m['file_size']]);
        echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false, 'msg'=>'消息不存在或已撤回']); exit;
}

// 普通发消息
$to = (int)($_POST['to'] ?? 0);
$content = trim($_POST['content'] ?? '');
$reply_to = !empty($_POST['reply_to']) ? (int)$_POST['reply_to'] : null;

$file_path = ''; $file_name = ''; $file_size = 0;

if (!empty($_FILES['file']['name'])) {
    $f = $_FILES['file'];
    if ($f['error'] === UPLOAD_ERR_OK && $f['size'] < 10*1024*1024) { // 限制10MB
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $fn = 'msg_'.bin2hex(random_bytes(8)).'.'.$ext;
        if (move_uploaded_file($f['tmp_name'], __DIR__.'/uploads/'.$fn)) {
            $file_path = 'uploads/'.$fn;
            $file_name = $f['name'];
            $file_size = $f['size'];
        }
    }
}

if ($to > 0 && ($content !== '' || $file_path !== '')) {
    $stmt = $pdo->prepare('INSERT INTO messages (from_uid, to_uid, content, file_path, file_name, file_size, reply_to) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$uid, $to, $content, $file_path, $file_name, $file_size, $reply_to]);
    echo json_encode(['ok'=>true, 'mid'=>$pdo->lastInsertId()]); exit;
}
echo json_encode(['ok'=>false, 'msg'=>'发送内容为空']);
