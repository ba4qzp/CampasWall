<?php
require_once __DIR__ . '/src/init.php';
header('Content-Type: application/json');
if(empty($_SESSION['uid'])){ echo json_encode(['ok'=>false]); exit; }
$fileId = preg_replace('/[^a-f0-9]/','', $_POST['fileId'] ?? '');
$chunkIdx = (int)($_POST['chunkIndex'] ?? -1);
$total = (int)($_POST['totalChunks'] ?? 0);
$filename = $_POST['filename'] ?? '';
$dir = __DIR__.'/uploads/tmp/'.$fileId;
if(!is_dir($dir)) @mkdir($dir,0777,true);
move_uploaded_file($_FILES['chunk']['tmp_name'], $dir.'/'.$chunkIdx);
if($chunkIdx === $total - 1){
    $ext = preg_replace('/[^a-z0-9]/i','', pathinfo($filename, PATHINFO_EXTENSION));
    $finalName = bin2hex(random_bytes(16)) . ($ext ? '.'.$ext : '');
    $out = fopen(__DIR__.'/uploads/'.$finalName,'wb');
    for($i=0;$i<$total;$i++){ $in=fopen($dir.'/'.$i,'rb'); stream_copy_to_stream($in,$out); fclose($in); }
    fclose($out);
    for($i=0;$i<$total;$i++) @unlink($dir.'/'.$i); @rmdir($dir);
    echo json_encode(['path'=>'uploads/'.$finalName, 'orig'=>$filename, 'img'=>is_image($filename)]); exit;
}
echo json_encode(['ok'=>true]);
