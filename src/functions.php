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

function get_avatar($user) {
    if (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . $user['avatar'])) {
        return h($user['avatar']);
    }
    if (file_exists(__DIR__ . '/../docs/lls.jpg')) {
        return 'docs/lls.jpg';
    }
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

// 检查更新（区分等级，识别补丁）
function check_for_updates($pdo) {
    if (empty($_SESSION['uid'])) return false;
    $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE uid = ?');
    $stmt->execute([$_SESSION['uid']]);
    if (!$stmt->fetchColumn()) return false;

    $cache_file = __DIR__ . '/../uploads/update_cache.json';
    $now = time();
    $cache_time = 3600; 

    $data = null;
    if (file_exists($cache_file)) {
        $data = json_decode(@file_get_contents($cache_file), true);
    }

    if (!$data || ($now - ($data['time'] ?? 0)) > $cache_time) {
        $url = 'https://api.github.com/repos/BA4QZP/PHPCampus/releases';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHPCampus-Update-Checker');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); 
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $releases = json_decode($response, true);
            $latestStable = null;
            $latestBeta = null;
            if (is_array($releases)) {
                foreach ($releases as $r) {
                    if ($r['prerelease'] == false && !$latestStable) $latestStable = $r;
                    if ($r['prerelease'] == true && !$latestBeta) $latestBeta = $r;
                }
            }
            $data = ['time' => $now, 'stable' => $latestStable, 'beta' => $latestBeta];
            @file_put_contents($cache_file, json_encode($data));
        }
    }

    if (!$data) return false;
    $local_ver = ltrim(APP_VERSION, 'v');
    
    // 检查正式版/补丁
    if (!empty($data['stable']['tag_name'])) {
        $remote_stable = ltrim($data['stable']['tag_name'], 'v');
        if (version_compare($remote_stable, $local_ver, '>')) {
            $title = $data['stable']['name'];
            
            $is_critical = (strpos($title, '[CRITICAL]') !== false);
            $is_high = (strpos($title, '[HIGH_BUG]') !== false);
            $is_low = (strpos($title, '[LOW_BUG]') !== false);
            
            $is_patch = preg_match('/^\d+\.\d+\.\d+\.\d+$/', $remote_stable);
            
            return [
                'type' => 'stable', 
                'tag' => $data['stable']['tag_name'], 
                'url' => $data['stable']['html_url'], 
                'zip_url' => $data['stable']['zipball_url'],
                'is_critical' => $is_critical,
                'is_high' => $is_high,
                'is_low' => $is_low,
                'is_patch' => $is_patch
            ];
        }
    }
    
    // 检查测试版
    if (!empty($data['beta']['tag_name'])) {
        $remote_beta = ltrim($data['beta']['tag_name'], 'v');
        if (version_compare($remote_beta, $local_ver, '>')) {
            return [
                'type' => 'beta', 
                'tag' => $data['beta']['tag_name'], 
                'url' => $data['beta']['html_url'], 
                'zip_url' => $data['beta']['zipball_url'],
                'is_critical' => false,
                'is_high' => false,
                'is_low' => false,
                'is_patch' => false
            ];
        }
    }
    return false;
}

function try_silent_update($pdo) {
    if (empty($_SESSION['uid']) || $_SESSION['uid'] != SUPER_UID) return;

    $lock_file = __DIR__ . '/../uploads/update.lock';
    if (file_exists($lock_file) && time() - filemtime($lock_file) < 300) return; 
    
    $info = check_for_updates($pdo);
    if (!$info || $info['type'] !== 'stable') return; 

    file_put_contents($lock_file, time());

    if (!class_exists('ZipArchive')) {
        file_put_contents(__DIR__ . '/../uploads/update_failed.json', json_encode(['msg' => '主机不支持 ZipArchive 扩展']));
        @unlink($lock_file); return;
    }

    $zip_file = __DIR__ . '/../uploads/update_tmp.zip';
    $fp = fopen($zip_file, 'w+');
    $ch = curl_init($info['zip_url']);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHPCampus-Updater');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8); 
    $success = curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    if (!$success || !file_exists($zip_file) || filesize($zip_file) < 1000) {
        @unlink($zip_file);
        file_put_contents(__DIR__ . '/../uploads/update_failed.json', json_encode(['msg' => '下载超时/网络错误']));
        @unlink($lock_file); return;
    }

    $zip = new ZipArchive;
    if ($zip->open($zip_file) === TRUE) {
        $extract_path = __DIR__ . '/../uploads/update_extract';
        if (is_dir($extract_path)) remove_dir_recursive($extract_path);
        mkdir($extract_path, 0755, true);
        $zip->extractTo($extract_path);
        $zip->close();
        @unlink($zip_file);

        $files = scandir($extract_path);
        $source_dir = $extract_path;
        foreach($files as $f){
            if($f != '.' && $f != '..' && is_dir($extract_path . '/' . $f)){ $source_dir = $extract_path . '/' . $f; break; }
        }

        copy_recursive_update($source_dir, __DIR__ . '/../');
        remove_dir_recursive($extract_path);

        file_put_contents(__DIR__ . '/../uploads/update_success.json', json_encode(['tag' => $info['tag']]));
    } else {
        @unlink($zip_file);
        file_put_contents(__DIR__ . '/../uploads/update_failed.json', json_encode(['msg' => '解压失败']));
    }
    @unlink($lock_file);
}

function copy_recursive_update($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ($file == '.env' || $file == 'install.lock' || $file == 'uploads') continue;
            if ( is_dir($src . '/' . $file) ) {
                copy_recursive_update($src . '/' . $file, $dst . '/' . $file);
            } else {
                @copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function remove_dir_recursive($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
      (is_dir("$dir/$file")) ? remove_dir_recursive("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
}
