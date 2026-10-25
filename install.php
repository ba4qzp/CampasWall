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
            
            if($upgraded) $upgrade_msg = '<h3 style="color:green">✅ 数据库结构已自动平滑升级成功！</h3><p>您可以直接去前台或后台体验新功能。</p>';
            else $upgrade_msg = '<h3 style="color:blue">💡 系统已安装，且数据库结构已是最新，无需重复升级。</h3>';
        } catch(Exception $e) { $upgrade_msg = '<h3 style="color:red">❌ 自动升级失败：' . $e->getMessage() . '</h3>'; }
    }
    die($upgrade_msg . '<p><a href="index.php">← 返回首页</a> | 如需彻底重装，请删除 .env 和 install.lock 文件</p>');
}

$step = $_GET['step'] ?? '1'; $msg = '';
if($step === '2'){
    $db_host = $_POST['db_host'] ?? ''; $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? ''; $db_pass = $_POST['db_pass'] ?? '';
    $site_name = $_POST['site_name'] ?? ''; $admin_email = $_POST['admin_email'] ?? '';
    $admin_user = trim($_POST['admin_user'] ?? ''); $admin_pass = $_POST['admin_pass'] ?? '';

    $allow_register = isset($_POST['allow_register']) ? 'true' : 'false';
    $require_approval = isset($_POST['require_approval']) ? 'true' : 'false';
    $require_email = isset($_POST['require_email']) ? 'true' : 'false';
    $limit_private_msg = isset($_POST['limit_private_msg']) ? 'true' : 'false';

    if(!$admin_user || !$admin_pass) { $msg = '<h3 style="color:red">失败：超管账号和密码不能为空！</h3>'; }
    else {
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $sql = "
            CREATE TABLE IF NOT EXISTS users (
                uid INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(20) UNIQUE NOT NULL, nickname VARCHAR(50) DEFAULT '', password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(100) DEFAULT '', email_verified TINYINT(1) DEFAULT 0, bio VARCHAR(500) DEFAULT '', avatar VARCHAR(255) DEFAULT '',
                gender VARCHAR(10) DEFAULT 'secret', grade VARCHAR(20) DEFAULT '', class_name VARCHAR(20) DEFAULT '', show_info TINYINT(1) DEFAULT 0,
                points INT DEFAULT 10000, is_admin TINYINT(1) DEFAULT 0, ng TINYINT(1) DEFAULT 0, status VARCHAR(20) DEFAULT 'active',
                reg_ip VARCHAR(45) DEFAULT '', last_ip VARCHAR(45) DEFAULT '', username_changed_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            CREATE TABLE IF NOT EXISTS posts (
                pid INT AUTO_INCREMENT PRIMARY KEY, uid INT NOT NULL, is_anon TINYINT(1) DEFAULT 0, content TEXT, files TEXT,
                tags VARCHAR(500), password VARCHAR(10) DEFAULT NULL, closed TINYINT(1) DEFAULT 0, hidden TINYINT(1) DEFAULT 0,
                ip VARCHAR(45), created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            CREATE TABLE IF NOT EXISTS comments (
                cid INT AUTO_INCREMENT PRIMARY KEY, pid INT NOT NULL, uid INT NOT NULL, is_anon TINYINT(1) DEFAULT 0, content TEXT,
                hidden TINYINT(1) DEFAULT 0, ip VARCHAR(45), created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            CREATE TABLE IF NOT EXISTS messages (
                mid INT AUTO_INCREMENT PRIMARY KEY, from_uid INT NOT NULL, to_uid INT NOT NULL, content TEXT,
                file_path VARCHAR(255) DEFAULT '', file_name VARCHAR(255) DEFAULT '', file_size INT DEFAULT 0,
                is_read TINYINT(1) DEFAULT 0, deleted TINYINT(1) DEFAULT 0, reply_to INT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            CREATE TABLE IF NOT EXISTS friendships (
                id INT AUTO_INCREMENT PRIMARY KEY, uid1 INT NOT NULL, uid2 INT NOT NULL, status TINYINT(1) DEFAULT 0,
                pinned TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY pair (uid1, uid2)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            CREATE TABLE IF NOT EXISTS likes (id INT AUTO_INCREMENT PRIMARY KEY, uid INT, pid INT, UNIQUE KEY u_p (uid, pid));
            CREATE TABLE IF NOT EXISTS point_logs (id INT AUTO_INCREMENT PRIMARY KEY, uid INT, delta INT, reason VARCHAR(32), ref_id INT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS market_items (
                id INT AUTO_INCREMENT PRIMARY KEY, uid INT NOT NULL, title VARCHAR(100) NOT NULL, desc_text TEXT,
                price INT NOT NULL, image_path VARCHAR(255) DEFAULT '', status TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            CREATE TABLE IF NOT EXISTS market_logs (id INT AUTO_INCREMENT PRIMARY KEY, item_id INT, buyer_uid INT, seller_uid INT, price INT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS user_actions (id INT AUTO_INCREMENT PRIMARY KEY, uid INT, admin_uid INT, action VARCHAR(50), detail TEXT, is_read TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS announcements (aid INT PRIMARY KEY DEFAULT 1, content TEXT);
            INSERT IGNORE INTO announcements (aid, content) VALUES (1, '欢迎来到 ".addslashes($site_name)."！');
            CREATE TABLE IF NOT EXISTS ip_logs (lid INT AUTO_INCREMENT PRIMARY KEY, uid INT, ip VARCHAR(45), path VARCHAR(255), ua VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS ai_convs (id INT AUTO_INCREMENT PRIMARY KEY, uid INT, title VARCHAR(80), summary MEDIUMTEXT NULL, summary_upto_mid INT DEFAULT 0, summary_ctx INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS ai_msgs (id INT AUTO_INCREMENT PRIMARY KEY, conv_id INT, role VARCHAR(16), content MEDIUMTEXT, image_path VARCHAR(255), tokens INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            ";
            $pdo->exec($sql);
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, email, is_admin, status, reg_ip) VALUES (?, ?, ?, 1, "active", ?)');
            $stmt->execute([$admin_user, $hash, $admin_email, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
            
            $envContent = "SITE_NAME=\"{$site_name}\"\nSITE_URL=\"http://".$_SERVER['HTTP_HOST']."\"\nADMIN_EMAIL=\"{$admin_email}\"\nALLOW_REGISTER={$allow_register}\nREQUIRE_APPROVAL={$require_approval}\nREQUIRE_EMAIL={$require_email}\nLIMIT_PRIVATE_MSG={$limit_private_msg}\nDB_HOST=\"{$db_host}\"\nDB_NAME=\"{$db_name}\"\nDB_USER=\"{$db_user}\"\nDB_PASS=\"{$db_pass}\"\nAI_BASE_URL=\"\"\nAI_API_KEY=\"\"\nAI_MODEL=\"\"\n";
            if(file_put_contents(__DIR__ . '/.env', $envContent) === false) throw new Exception("无法写入 .env 文件");
            file_put_contents(__DIR__ . '/install.lock', 'locked');
            $msg = '<h3 style="color:green">安装成功！</h3><p>请立即删除 install.php。现在去登录你刚才设置的管理员账号。</p><p><a href="index.php">进入首页</a></p>';
        } catch (Exception $e) { $msg = '<h3 style="color:red">失败：'.$e->getMessage().'</h3>'; }
    }
}
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>安装 PHPSchool</title>
<style>body{font-family:sans-serif;max-width:500px;margin:40px auto;padding:20px;} input[type=text],input[type=email],input[type=password]{width:100%;padding:8px;margin:5px 0 15px;box-sizing:border-box;} .switch-row{margin:10px 0;display:flex;align-items:center;gap:8px;font-size:14px;} button{padding:10px 20px;background:#2563eb;color:#fff;border:none;cursor:pointer;width:100%;font-size:16px;border-radius:4px;}</style></head><body><h2>安装 PHPSchool</h2><?php echo $msg; ?>
<?php if($step === '1'): ?><form method="POST" action="install.php?step=2">
<label>站点名称</label><input type="text" name="site_name" value="PHPSchool" required>
<label>管理员邮箱</label><input type="email" name="admin_email" value="admin@example.com" required>
<hr style="border:0;border-top:1px solid #eee;margin:20px 0">
<label style="font-weight:bold;margin-bottom:10px;display:block">超管账号设置</label>
<label>超管用户名</label><input type="text" name="admin_user" value="admin" required>
<label>超管密码</label><input type="password" name="admin_pass" required>
<hr style="border:0;border-top:1px solid #eee;margin:20px 0">
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
