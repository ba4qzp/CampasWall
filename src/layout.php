<?php
function page_head($title) {
    global $urow, $pdo;
?><!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title)?> - <?=h(SITE_NAME)?></title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/marked.min.js"></script>
</head><body>

<?php if(isset($urow) && $urow['is_admin']): 
    $update_msg = check_for_updates($pdo);
    if($update_msg): ?>
        <div style="background:#fef3c7;color:#92400e;padding:10px;text-align:center;font-size:14px;border-bottom:1px solid #fcd34d;z-index:9999;position:relative;">
            <?= $update_msg ?>
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
