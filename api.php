<?php
require_once __DIR__ . '/src/init.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'check_app_version') {
    $latest_version_code = 1; // 每次发版+1
    $latest_version_name = "1.0.0";
    $download_url = "https://github.com/BA4QZP/PHPCampus/releases/latest";

    $client_version_code = (int)($_GET['v_code'] ?? 0);

    if ($client_version_code < $latest_version_code) {
        echo json_encode([
            'update' => true,
            'new_version' => $latest_version_name,
            'download_url' => $download_url,
            'msg' => '发现新版本，请更新！'
        ]);
    } else {
        echo json_encode(['update' => false, 'msg' => '已是最新版本']);
    }
    exit;
}

if ($action === 'posts') {
    $stmt = $pdo->query('SELECT pid, content, created_at FROM posts WHERE hidden = 0 ORDER BY pid DESC LIMIT 20');
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['code' => 200, 'data' => $posts]);
    exit;
}

echo json_encode(['code' => 404, 'msg' => '接口不存在']);
