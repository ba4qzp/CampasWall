<?php
require_once __DIR__ . '/src/init.php';
header('Content-Type: application/json');
if(empty($_SESSION['uid'])){ echo json_encode(['ok'=>false]); exit; }
$uid = (int)$_SESSION['uid'];
$with = (int)($_GET['with'] ?? 0);
$last = (int)($_GET['last'] ?? 0);

if(isset($_GET['state'])){
    $stmt = $pdo->prepare('SELECT mid, deleted FROM messages WHERE (from_uid=? AND to_uid=?) OR (from_uid=? AND to_uid=?)');
    $stmt->execute([$uid, $with, $with, $uid]);
    $state = [];
    foreach($stmt->fetchAll() as $r) $state[$r['mid']] = $r['deleted'];
    echo json_encode(['ok'=>true, 'state'=>$state]); exit;
}

$stmt = $pdo->prepare('SELECT * FROM messages WHERE ((from_uid=? AND to_uid=?) OR (from_uid=? AND to_uid=?)) AND mid > ? ORDER BY mid ASC');
$stmt->execute([$uid, $with, $with, $uid, $last]);
echo json_encode(['ok'=>true, 'messages'=>$stmt->fetchAll()]);
