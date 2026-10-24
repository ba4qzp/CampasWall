<?php
function page_head($title) {
    global $urow;
?><!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title)?> - <?=h(SITE_NAME)?></title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/marked.min.js"></script>
</head><body>
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
    echo '<div class="footer">© '.date('Y').' '.h(SITE_NAME).'</div>';
    echo '<script src="assets/script.js"></script></body></html>';
}
