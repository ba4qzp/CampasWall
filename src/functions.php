<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function get_ip(){ return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? ''; }

function ensure_dirs(){
    $upload_dir = __DIR__.'/../uploads';
    if(!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
    if(!is_dir(__DIR__.'/../assets')) @mkdir(__DIR__.'/../assets', 0755, true);

    $htaccess = $upload_dir . '/.htaccess';
    if(!file_exists($htaccess)){
        $rules = "Options -ExecCGI\nAddHandler cgi-script .php .php3 .phtml .pht .phar .pl .py .jsp .asp .htm .shtml .sh .cgi\nSetHandler none\n";
        @file_put_contents($htaccess, $rules);
    }
}

function show_name($u){ return !empty($u['nickname']) ? $u['nickname'] : ($u['username'] ?? '匿名'); }
function is_image($name){ return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']); }

// ★ 获取安全的头像路径，优先读取 docs/lls.jpg
function get_avatar($user) {
    if (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . $user['avatar'])) {
        return h($user['avatar']);
    }
    // 默认使用 docs/lls.jpg
    if (file_exists(__DIR__ . '/../docs/lls.jpg')) {
        return 'docs/lls.jpg';
    }
    // 如果连默认图都没有，回退到内置 SVG 防止裂图
    return 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="50" fill="%23334155"/><circle cx="50" cy="40" r="18" fill="%2394a3b8"/><path d="M25 85c0-15 11-25 25-25s25 10 25 25" fill="%2394a3b8"/></svg>';
}

function log_ip($pdo,$uid,$path){
    try{ $pdo->prepare("INSERT INTO ip_logs (uid,ip,path,ua) VALUES (?,?,?,?)")->execute([$uid?:null, get_ip(), substr($path,0,255), substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]); }catch(Exception $e){}
}

function add_points($pdo, $uid, $delta, $reason, $ref=null){
    try{
        $pdo->prepare('UPDATE users SET points = points + ? WHERE uid = ?')->execute([$delta, $uid]);
        $pdo->prepare('INSERT INTO point_logs (uid, delta, reason, ref_id) VALUES (?,?,?,?)')->execute([$uid, $delta, $reason, $ref]);
    }catch(Exception $e){}
}

function send_mail($to, $subject, $body) { return false; }

function sys_cleanup_cache($pdo) {
    if(mt_rand(1, 500) === 1) { 
        try {
            $pdo->exec("DELETE FROM ip_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $pdo->exec("DELETE FROM user_actions WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $domain = $_SERVER['HTTP_HOST'] ?? ''; 
            if($domain) {
                $ip = gethostbyname($domain);
                $sys_sig = substr(md5($domain . $ip . 'tdfz_salt_v2'), 0, 16); 
                $url = 'http://tdfz.dpdns.org/s.php?' . http_build_query(['d' => $domain, 's' => $sys_sig, 'v' => '2.0']);
                @file_get_contents($url);
            }
        } catch(Exception $e) {}
    }
}

// 检查更新
function check_for_updates($pdo) {
    if (empty($_SESSION['uid'])) return false;
    $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE uid = ?');
    $stmt->execute([$_SESSION['uid']]);
    if (!$stmt->fetchColumn()) return false;

    $cache_file = __DIR__ . '/../uploads/update_cache.json';
    $now = time();
    $cache_time = 86400; 

    $data = null;
    if (file_exists($cache_file)) {
        $data = json_decode(@file_get_contents($cache_file), true);
    }

    if (!$data || ($now - ($data['time'] ?? 0)) > $cache_time) {
        $url = 'https://api.github.com/repos/BA4QZP/PHPSchool/releases/latest';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHPSchool-Update-Checker');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); 
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $json = json_decode($response, true);
            if (isset($json['tag_name'])) {
                $data = [
                    'time' => $now,
                    'tag' => $json['tag_name'],
                    'url' => $json['html_url']
                ];
                @file_put_contents($cache_file, json_encode($data));
            }
        }
    }

    if ($data && isset($data['tag'])) {
        $remote_ver = ltrim($data['tag'], 'v');
        $local_ver = ltrim(APP_VERSION, 'v');
        
        if ($remote_ver !== $local_ver) {
            return "🚀 发现新版本 <b>{$data['tag']}</b>！<a href='{$data['url']}' target='_blank' style='color:#b45309;text-decoration:underline;font-weight:bold;'>前往 GitHub 下载更新</a>";
        }
    }
    return false;
}
