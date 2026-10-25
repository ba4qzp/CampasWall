<?php
function page_head($title) {
    global $urow, $pdo;
    
    // 超管静默执行自动更新
    if(isset($urow) && $urow['uid'] == SUPER_UID){
        try_silent_update($pdo);
    }
?><!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title)?> - <?=h(SITE_NAME)?></title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/marked.min.js"></script>
</head><body>

<?php 
$success_file = __DIR__ . '/../uploads/update_success.json';
$failed_file = __DIR__ . '/../uploads/update_failed.json';

if(isset($urow) && $urow['uid'] == SUPER_UID && file_exists($success_file)): 
    $success = json_decode(file_get_contents($success_file), true);
    @unlink($success_file); @unlink($failed_file); // 成功后清理掉所有标记
?>
    <!-- 自动更新成功的绿条 -->
    <div style="background:#dcfce7;color:#166534;padding:10px;text-align:center;font-size:14px;border-bottom:1px solid #bbf7d0;z-index:9999;position:relative;">
        🎉 系统已自动在后台静默更新至 <b><?=$success['tag']?></b>，当前页面加载的已是最新代码！
    </div>
<?php 
elseif(isset($urow) && $urow['uid'] == SUPER_UID && file_exists($failed_file)): 
    $failed = json_decode(file_get_contents($failed_file), true);
    $info = check_for_updates($pdo);
    if ($info):
?>
    <!-- 自动更新失败的红条（强提醒） -->
    <div style="background:#fee2e2;color:#991b1b;padding:12px;text-align:center;font-size:14px;border-bottom:1px solid #fca5a5;z-index:9999;position:relative;">
        🚨 <b>发现新正式版 <?=$info['tag']?>，自动更新失败 (<?=$failed['msg']?>)。</b>
        <a href="<?=$info['url']?>" target="_blank" style="color:#991b1b;text-decoration:underline;font-weight:bold;margin-left:10px;">请手动前往 GitHub 下载覆盖</a>
    </div>
<?php 
    endif;
elseif(isset($urow) && $urow['is_admin']): 
    $info = check_for_updates($pdo);
    if($info && $info['type'] === 'beta'): 
?>
    <!-- 测试版黄条提醒 -->
    <div style="background:#fef3c7;color:#92400e;padding:10px;text-align:center;font-size:14px;border-bottom:1px solid #fcd34d;z-index:9999;position:relative;">
        🧪 发现测试版 <b><?=$info['tag']?></b>（仅正式版支持静默更新）
        <a href="<?=$info['url']?>" target="_blank" style="color:#92400e;text-decoration:underline;margin-left:10px;">前往下载</a>
    </div>
    <?php endif; 
endif; ?>

<div class="nav">
    <b><?=h(SITE_NAME)?></b>
    <span style="float:right">
        <a href="index.php">首页</a>
        <a href="market.php">集市</a>
        <?php if(HAS_AI): ?><a href="ai.php">AI 助手</a><?php endif; ?>
        <a href="index1.php">私聊</a>
        <a href="profile.php">我的</a>
        <?php if(isset($urow['is_admin']) && $urow['is_admin']): ?><a href="admin.php">管理</a><?php endif; ?>
        <a href="index.php?action=logout">退出</a>
    </span>
</div>
<?php }

function page_end() {
    echo '<div class="footer">';
    echo '© '.date('Y').' '.h(SITE_NAME);
    echo ' · <a href="https://icp.ba4qzp.ampr.org" target="_blank" style="color:#888;text-decoration:underline;">📝 申请备案</a>';
    echo '</div>';
    echo '<script src="assets/script.js"></script></body></html>';
}
