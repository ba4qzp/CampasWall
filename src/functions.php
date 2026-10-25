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
        $url = 'https://api.github.com/repos/BA4QZP/PHPSchool/releases';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHPSchool-Update-Checker');
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
            
            // 判断严重程度
            $is_critical = (strpos($title, '[CRITICAL]') !== false);
            $is_high = (strpos($title, '[HIGH_BUG]') !== false);
            $is_low = (strpos($title, '[LOW_BUG]') !== false);
            
            // 判断是不是纯补丁版 (三个点，且结尾没有额外数字)
            // 比如 0.0.1.01 是补丁，0.0.1.01.251 是测试版
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
