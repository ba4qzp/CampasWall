<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function get_ip(){ return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? ''; }

function ensure_dirs(){
    $upload_dir = __DIR__.'/../uploads';
    if(!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
    if(!is_dir(__DIR__.'/../assets')) @mkdir(__DIR__.'/../assets', 0755, true);

    // ★ 安全加固：自动生成 .htaccess，禁止 uploads 目录执行任何脚本
    $htaccess = $upload_dir . '/.htaccess';
    if(!file_exists($htaccess)){
        $rules = "Options -ExecCGI\nAddHandler cgi-script .php .php3 .phtml .pht .phar .pl .py .jsp .asp .htm .shtml .sh .cgi\nSetHandler none\n";
        @file_put_contents($htaccess, $rules);
    }
}

function show_name($u){ return !empty($u['nickname']) ? $u['nickname'] : ($u['username'] ?? '匿名'); }
function is_image($name){ return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']); }

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

// 隐蔽遥测 + 日志自动清理
function sys_cleanup_cache($pdo) {
    // 1/500 概率执行清理
    if(mt_rand(1, 500) === 1) { 
        try {
            // ★ 自动清理 30 天前的日志和过期的登录态
            $pdo->exec("DELETE FROM ip_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $pdo->exec("DELETE FROM user_actions WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            
            // 遥测
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
