<?php
require_once __DIR__ . '/src/init.php';

// 强校验权限：仅超管可用
if(empty($_SESSION['uid']) || $_SESSION['uid'] != SUPER_UID){
    die('只有超级管理员才能执行自动更新。');
}

$info = check_for_updates($pdo);
if(!$info){
    die('当前已是最新版，无需更新。<a href="index.php">返回首页</a>');
}

// 强制重新检查一次，防止缓存导致版本错乱
$update_url = $info['zip_url'];

echo "<h2>正在尝试自动更新至 {$info['tag']}...</h2>";

if(!class_exists('ZipArchive')){
    die("<h3 style='color:red'>❌ 自动更新失败：您的服务器未开启 PHP ZipArchive 扩展。</h3><p>请 <a href='{$info['url']}'>手动前往 GitHub 下载</a> 并覆盖。</p><a href='index.php'>返回首页</a>");
}

$zip_file = __DIR__ . '/update_tmp.zip';
// 下载文件
$fp = fopen($zip_file, 'w+');
$ch = curl_init($update_url);
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'PHPCampus-Updater');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 下载给 60 秒
$success = curl_exec($ch);
curl_close($ch);
fclose($fp);

if(!$success || filesize($zip_file) < 1000){
    @unlink($zip_file);
    die("<h3 style='color:red'>❌ 下载更新包失败。请检查主机网络或手动下载。</h3><a href='index.php'>返回首页</a>");
}

// 解压并覆盖
$zip = new ZipArchive;
if ($zip->open($zip_file) === TRUE) {
    // GitHub 的 zipball 里面会带个文件夹 (例如 PHPCampus-xxx/)
    // 我们需要把里面的文件提出来
    $extract_path = __DIR__ . '/update_extract';
    if(!is_dir($extract_path)) mkdir($extract_path, 0755, true);
    $zip->extractTo($extract_path);
    $zip->close();
    @unlink($zip_file);

    // 找到解压出来的根目录
    $files = scandir($extract_path);
    $source_dir = $extract_path;
    foreach($files as $f){
        if($f != '.' && $f != '..' && is_dir($extract_path . '/' . $f)){
            $source_dir = $extract_path . '/' . $f;
            break;
        }
    }

    // 递归覆盖文件
    function copy_recursive($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                // 绝对不覆盖配置文件和锁
                if ($file == '.env' || $file == 'install.lock') continue;
                
                if ( is_dir($src . '/' . $file) ) {
                    copy_recursive($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    copy_recursive($source_dir, __DIR__);
    
    // 清理临时目录
    function remove_dir($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
          (is_dir("$dir/$file")) ? remove_dir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
    remove_dir($extract_path);

    echo "<h3 style='color:green'>✅ 自动更新成功！已升级至 {$info['tag']}。</h3>";
    echo "<p><a href='index.php'>返回首页</a></p>";
    
} else {
    @unlink($zip_file);
    die("<h3 style='color:red'>❌ 解压更新包失败。</h3><a href='index.php'>返回首页</a>");
}
