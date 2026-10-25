<?php
function page_head($title) {
    global $urow, $pdo;
    
    if(isset($urow) && $urow['uid'] == SUPER_UID){
        try_silent_update($pdo);
    }
    
    $update_info = (isset($urow) && $urow['is_admin']) ? check_for_updates($pdo) : false;
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
    @unlink($success_file); @unlink($failed_file); 
?>
    <div style="background:#dcfce7;color:#166534;padding:10px;text-align:center;font-size:14px;border-bottom:1px solid #bbf7d0;z-index:9999;position:relative;">
        🎉 系统已自动在后台静默更新至 <b><?=$success['tag']?></b>。
    </div>
<?php elseif($update_info): 
    $is_critical = $update_info['is_critical'];
    $is_high = $update_info['is_high'];
    $is_low = $update_info['is_low'];
    $is_patch = $update_info['is_patch'];
    
    // 弹窗规则：补丁 必弹；高危/严重 必弹
    $need_popup = ($is_patch || $is_high || $is_critical);
    
    // 颜色配置
    if ($is_critical) {
        $bg = '#ef4444'; $color = '#ffffff'; // 严重：红底白字
    } elseif ($is_high) {
        $bg = '#f97316'; $color = '#ffffff'; // 高危：橙底白字
    } elseif ($is_patch) {
        $bg = '#fee2e2'; $color = '#991b1b'; // 补丁：浅红底红字
    } elseif ($is_low) {
        $bg = '#fef3c7'; $color = '#92400e'; // 一般漏洞：黄底
    } else {
        $bg = '#e0f2fe'; $color = '#0369a1'; // 普通更新：蓝底
    }
?>

    <?php if ($is_critical): ?>
    <style>
        @keyframes flashRed {
            0% { background-color: rgba(239, 68, 68, 0.4); }
            50% { background-color: rgba(0, 0, 0, 0.8); }
            100% { background-color: rgba(239, 68, 68, 0.4); }
        }
        .critical-flash {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            pointer-events: none; z-index: 99999;
            animation: flashRed 0.4s infinite;
        }
    </style>
    <div class="critical-flash"></div>
    <script>
        alert('🚨🚨🚨 严重漏洞紧急更新 🚨🚨🚨\n\n检测到修复严重漏洞的新版本 (<?=$update_info['tag']?>)！\n当前版本极度危险，请务必立即更新！');
    </script>
    <?php elseif ($need_popup): ?>
    <script>
        window.addEventListener('DOMContentLoaded', function(){
            alert('⚠️ 发现重要更新 (<?=$update_info['tag']?>)！\n\n<?=$is_patch ? '系统检测到新的补丁版本，修复了潜在问题，建议您立即更新。' : '检测到高危漏洞修复，请尽快更新。'?>');
        });
    </script>
    <?php endif; ?>

    <!-- 顶部横幅 -->
    <div style="background:<?=$bg?>;color:<?=$color?>;padding:12px;text-align:center;font-size:14px;border-bottom:1px solid rgba(0,0,0,0.1);z-index:9999;position:relative;font-weight:<?=($is_critical||$is_high)?'bold':'normal'?>">
        <?php if($is_critical): ?>
            🚨 <b>【严重漏洞】发现紧急安全补丁 <?=$update_info['tag']?>！</b>
            <a href="<?=$update_info['url']?>" target="_blank" style="color:#fff;text-decoration:underline;margin-left:10px;background:rgba(0,0,0,0.2);padding:2px 6px;border-radius:4px;">立即前往更新</a>
        <?php elseif($is_high): ?>
            ⚠️ <b>【高危漏洞】发现新版本 <?=$update_info['tag']?>，请尽快修复。</b>
            <a href="<?=$update_info['url']?>" target="_blank" style="color:#fff;text-decoration:underline;margin-left:10px;">前往下载</a>
        <?php elseif($is_patch): ?>
            🩹 发现系统补丁 <b><?=$update_info['tag']?></b>
            <a href="<?=$update_info['url']?>" target="_blank" style="color:<?=$color?>;text-decoration:underline;margin-left:10px;">前往更新</a>
        <?php elseif($update_info['type'] === 'beta'): ?>
            🧪 发现测试版 <b><?=$update_info['tag']?></b>
            <a href="<?=$update_info['url']?>" target="_blank" style="color:<?=$color?>;text-decoration:underline;margin-left:10px;">查看</a>
        <?php else: ?>
            ✨ 发现新版本 <b><?=$update_info['tag']?></b>
            <a href="<?=$update_info['url']?>" target="_blank" style="color:<?=$color?>;text-decoration:underline;margin-left:10px;">查看</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

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
