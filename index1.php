<?php
require_once __DIR__ . '/src/init.php';
if(empty($_SESSION['uid'])){ header('Location: index.php?action=login'); exit; }
$uid = (int)$_SESSION['uid'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE uid = ?'); $stmt->execute([$uid]); $urow = $stmt->fetch();
$with = isset($_GET['with']) ? (int)$_GET['with'] : 0;

$unread = 0;
// ★ 修复未读红点：加上 deleted=0，排除已撤回消息
$stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE to_uid = ? AND is_read = 0 AND deleted = 0'); $stmt->execute([$uid]);
$unread = (int)$stmt->fetchColumn();

if($with){
    $pdo->prepare('UPDATE messages SET is_read = 1 WHERE to_uid = ? AND from_uid = ?')->execute([$uid, $with]);
}

page_head('私聊'); ?>
<div class="nav"><b>私聊</b> <span class="badge-unread" id="navUnread" style="<?=$unread?'':'display:none'?>"><?=$unread?></span></div>
<div class="card">
    <form method="get"><input type="text" name="with" placeholder="输入对方 UID" value="<?=$with?:''?>"><button type="submit">找人</button></form>
</div>
<?php if($with && $with > 0):
    $stmt = $pdo->prepare('SELECT uid, username, nickname FROM users WHERE uid = ?'); $stmt->execute([$with]); $withUser = $stmt->fetch();
    if($withUser):
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE (from_uid=? AND to_uid=?) OR (from_uid=? AND to_uid=?) ORDER BY mid DESC LIMIT 300');
    $stmt->execute([$uid, $with, $with, $uid]); $conv = array_reverse($stmt->fetchAll());
?>
    <div class="card">
        <h3>与 <?=h(show_name($withUser))?> 的对话</h3>
        <div id="chatBody" style="max-height:60vh;overflow-y:auto;padding-right:4px">
            <?php foreach($conv as $m):
                $mine = ((int)$m['from_uid'] === $uid);
                ?>
                <div style="display:flex;<?=$mine?'justify-content:flex-end':''?>" data-mid="<?=$m['mid']?>">
                    <div style="margin:8px 0;padding:8px;border-radius:6px;background:<?=$mine?'#dbeafe':'#f5f5f5'?>;max-width:80%;word-break:break-word">
                        <?php if($m['deleted']): ?><div style="color:#999;font-style:italic">[已撤回]</div>
                        <?php else: ?>
                            <div class="md"><?=h($m['content'])?></div>
                            <?php if($m['file_path']): ?><a href="<?=h($m['file_path'])?>">📎 文件</a><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="post" action="send_msg.php" style="margin-top:10px" id="msgForm">
            <input type="hidden" name="to" value="<?=$with?>">
            <textarea name="content" placeholder="说点什么..." required></textarea>
            <button type="submit">发送</button>
        </form>
    </div>
<?php endif; endif; ?>
<?php page_end(); ?>
