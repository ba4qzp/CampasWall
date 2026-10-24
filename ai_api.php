<?php
require_once __DIR__ . '/src/init.php';
if(!HAS_AI){ die('AI 未启用'); }
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['uid'])){ echo json_encode(['ok'=>false]); exit; }
$uid = (int)$_SESSION['uid'];
$action = $_GET['action'] ?? '';

if($action === 'new'){
    $pdo->prepare('INSERT INTO ai_convs (uid) VALUES (?)')->execute([$uid]);
    echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId()]); exit;
}

if($action === 'chat'){
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $conv_id = (int)$input['conv_id']; $text = trim($input['text']);
    
    $pdo->prepare('INSERT INTO ai_msgs (conv_id, role, content) VALUES (?,?,?)')->execute([$conv_id, 'user', $text]);
    
    $payload = [
        'model' => AI_MODEL,
        'messages' => [['role'=>'user', 'content'=>$text]],
        'chat_template_kwargs' => ['enable_thinking' => true]
    ];
    
    $ch = curl_init(AI_BASE_URL.'/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer '.AI_API_KEY],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp = curl_exec($ch); curl_close($ch);
    $j = json_decode($resp, true);
    
    $reply = $j['choices'][0]['message']['content'] ?? '思考超时或出错';
    $pdo->prepare('INSERT INTO ai_msgs (conv_id, role, content) VALUES (?,?,?)')->execute([$conv_id, 'assistant', $reply]);
    echo json_encode(['ok'=>true, 'msg'=>['content'=>$reply]]);
    exit;
}
