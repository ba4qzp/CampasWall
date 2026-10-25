<?php
if(file_exists(__DIR__ . '/.env') && file_exists(__DIR__ . '/install.lock')){
    $upgrade_msg = '';
    $env = [];
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || trim($line) === '') continue;
        list($name, $value) = explode('=', $line, 2);
        $env[trim($name)] = trim($value, " \"'");
    }
    
    if(isset($env['DB_HOST'], $env['DB_NAME'], $env['DB_USER'])){
        try {
            $pdo = new PDO("mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4", $env['DB_USER'], $env['DB_PASS'] ?? '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $cols = [];
            $stmt = $pdo->query("SHOW COLUMNS FROM users");
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) $cols[] = $row['Field'];
            
            $upgraded = false;
            if(!in_array('gender', $cols)) { $pdo->exec("ALTER TABLE users ADD COLUMN gender VARCHAR(10) DEFAULT 'secret' AFTER bio"); $upgraded = true; }
            if(!in_array('grade', $cols)) { $pdo->exec("ALTER TABLE users ADD COLUMN grade VARCHAR(20) DEFAULT '' AFTER gender"); $upgraded = true; }
            if(!in_array('class_name', $cols)) { $pdo->exec("ALTER TABLE users ADD COLUMN class_name VARCHAR(20) DEFAULT '' AFTER grade"); $upgraded = true; }
            if(!in_array('show_info', $cols)) { $pdo->exec("ALTER TABLE users ADD COLUMN show_info TINYINT(1) DEFAULT 0 AFTER class_name"); $upgraded = true; }
            
            if($upgraded) $upgrade_msg = '<h3 style="color:green">✅ 数据库结构已自动平滑升级成功！</h3>';
            else $upgrade_msg = '<h3 style="color:blue">💡 系统已安装，且数据库结构已是最新，无需重复升级。</h3>';
        } catch(Exception $e) { $upgrade_msg = '<h3 style="color:red">❌ 自动升级失败：' . $e->getMessage() . '</h3>'; }
    }
    die($upgrade_msg . '<p><a href="index.php">← 返回首页</a></p>');
}

$step = $_GET['step'] ?? '1'; $msg = '';
if($step === '2'){
    // ... (下面是安装建表逻辑，保持原样，但在最前面加一道防线) ...
    $db_host = $_POST['db_host'] ?? ''; $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? ''; $db_pass = $_POST['db_pass'] ?? '';
    
    // ★ 防呆：检查数据库是否已有用户
    try {
        $check_pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $table_exists = $check_pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if($table_exists){
            $count = $check_pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if($count > 0){
                die('❌ 检测到数据库内已存在用户数据！为防止误覆盖，已阻止重新安装。<br><a href="index.php">返回首页</a>');
            }
        }
    } catch(Exception $e) {}
}
// ... 后面是你原有的安装流程 ...>
<label style="font-weight:bold;margin-bottom:10px;display:block">功能开关设置</label>
<div class="switch-row"><input type="checkbox" name="allow_register" checked> <label>允许公开注册</label></div>
<div class="switch-row"><input type="checkbox" name="require_approval" checked> <label>注册后需要人工审核</label></div>
<div class="switch-row"><input type="checkbox" name="require_email"> <label>强制要求填写邮箱</label></div>
<div class="switch-row"><input type="checkbox" name="limit_private_msg" checked> <label>限制私聊</label></div>
<hr style="border:0;border-top:1px solid #eee;margin:20px 0">
<label>数据库地址</label><input type="text" name="db_host" value="localhost" required>
<label>数据库名</label><input type="text" name="db_name" required>
<label>数据库用户</label><input type="text" name="db_user" required>
<label>数据库密码</label><input type="password" name="db_pass">
<button type="submit">开始安装</button></form><?php endif; ?></body></html>
