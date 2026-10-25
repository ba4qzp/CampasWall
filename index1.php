<?php
require_once __DIR__ . '/src/init.php';
if(empty($_SESSION['uid'])){ header('Location: index.php?action=login'); exit; }
$uid = (int)$_SESSION['uid'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE uid = ?'); $stmt->execute([$uid]); $urow = $stmt->fetch();
$with = isset($_GET['with']) ? (int)$_GET['with'] : 0;

$stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE to_uid = ? AND is_read = 0 AND deleted = 0'); $stmt->execute([$uid]);
$unread = (int)$stmt->fetchColumn();

if($with){ $pdo->prepare('UPDATE messages SET is_read = 1 WHERE to_uid = ? AND from_uid = ?')->execute([$uid, $with]); }

page_head('私聊'); ?>
<div class="nav"><b>私聊</b> <span class="badge-unread" id="navUnread" style="<?=$unread?'':'display:none'?>"><?=$unread?></span></div>
<div class="card">
    <form method="get"><input type="text" name="with" placeholder="输入对方 UID" value="<?=$with?:''?>"><button type="submit">找人</button></form>
</div>
<?php if($with > 0):
    $stmt = $pdo->prepare('SELECT uid, username, nickname FROM users WHERE uid = ?'); $stmt->execute([$with]); $withUser = $stmt->fetch();
    if($withUser):
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE (from_uid=? AND to_uid=?) OR (from_uid=? AND to_uid=?) ORDER BY mid DESC LIMIT 300');
    $stmt->execute([$uid, $with, $with, $uid]); $conv = array_reverse($stmt->fetchAll());
?>
    <div class="card">
        <h3>与 <?=h(show_name($withUser))?> 的对话</h3>
        <div id="chatBody" style="max-height:60vh;overflow-y:auto;padding-right:4px">
            <?php foreach($conv as $m): $mine = ((int)$m['from_uid'] === $uid); ?>
                <div style="display:flex;<?=$mine?'justify-content:flex-end':''?>;margin-bottom:10px;" data-mid="<?=$m['mid']?>">
                    <div class="msg-bubble <?=$mine?'msg-me':'msg-other'" style="max-width:80%;">
                        <div style="font-size:10px;color:#888;margin-bottom:2px;"><?=date('m-d H:i', strtotime($m['created_at']))?></div>
                        <?php if($m['deleted']): ?>
                            <div style="color:#999;font-style:italic">[该消息已撤回]</div>
                        <?php else: ?>
                            <?php if($m['reply_to']): 
                                $rstmt = $pdo->prepare('SELECT content FROM messages WHERE mid = ?'); $rstmt->execute([$m['reply_to']]); $rcontent = $rstmt->fetchColumn();
                            ?>
                                <div style="background:rgba(0,0,0,0.05);border-left:2px solid #ccc;padding:4px;font-size:12px;margin-bottom:4px;color:#666;">
                                    引用：<?=h(mb_substr($rcontent?:'[已撤回]', 0, 20))?>...
                                </div>
                            <?php endif; ?>
                            <div class="md"><?=h($m['content'])?></div>
                            <?php if($m['file_path']): ?>
                                <?php if(is_image($m['file_path'])): ?>
                                    <img src="<?=h($m['file_path'])?>" style="max-width:100%;border-radius:4px;margin-top:4px;">
                                <?php else: ?>
                                    <a href="<?=h($m['file_path'])?>" target="_blank">📎 附件: <?=h($m['file_name'])?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- 操作按钮 -->
                            <div style="font-size:11px;margin-top:4px;text-align:right;">
                                <a href="javascript:void(0)" onclick="quoteMsg(<?=$m['mid']?>, '<?=h(addslashes($m['content']))?>')" style="color:#2563eb;">引用</a>
                                <?php if($mine && time() - strtotime($m['created_at']) < 120): ?>
                                    | <a href="javascript:void(0)" onclick="recallMsg(<?=$m['mid']?>)" style="color:#dc2626;">撤回</a>
                                <?php endif; ?>
                                | <a href="javascript:void(0)" onclick="forwardMsg(<?=$m['mid']?>)" style="color:#2563eb;">转发</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div id="chatEnd"></div>
        </div>
        
        <form id="msgForm" enctype="multipart/form-data" style="margin-top:10px">
            <input type="hidden" name="to" value="<?=$with?>">
            <input type="hidden" name="reply_to" id="replyToInput" value="">
            <div id="quotePreview" style="display:none;background:#f3f4f6;padding:5px;font-size:12px;margin-bottom:5px;border-radius:4px;">
                正在引用: <span id="quoteText"></span> <a href="javascript:void(0)" onclick="cancelQuote()">取消</a>
            </div>
            <textarea name="content" placeholder="说点什么..." style="width:100%;min-height:60px;"></textarea>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:5px;">
                <input type="file" name="file" style="font-size:12px;">
                <button type="submit" id="sendBtn">发送</button>
            </div>
        </form>
    </div>
    
    <script>
    var chatBody = document.getElementById('chatBody'), chatEnd = document.getElementById('chatEnd'), lastMid = <?=end($conv)['mid']?:0?>;
    function scrollBottom(){ chatEnd.scrollIntoView(); } window.addEventListener('load', scrollBottom);
    
    function quoteMsg(mid, content) {
        document.getElementById('replyToInput').value = mid;
        document.getElementById('quoteText').innerText = content.substring(0, 20) + '...';
        document.getElementById('quotePreview').style.display = 'block';
    }
    function cancelQuote() {
        document.getElementById('replyToInput').value = '';
        document.getElementById('quotePreview').style.display = 'none';
    }
    function recallMsg(mid) {
        if(!confirm('确定撤回该消息？')) return;
        fetch('send_msg.php?action=recall', {method:'POST', body: new URLSearchParams({mid: mid})})
            .then(r => r.json()).then(j => {
                if(j.ok) location.reload(); else alert('撤回失败');
            });
    }
    function forwardMsg(mid) {
        var to = prompt("输入要转发给对方的 UID:");
        if(!to) return;
        fetch('send_msg.php?action=forward', {method:'POST', body: new URLSearchParams({mid: mid, to: to})})
            .then(r => r.json()).then(j => {
                if(j.ok) alert('转发成功'); else alert('转发失败');
            });
    }

    document.getElementById('msgForm').onsubmit = async function(e){
        e.preventDefault();
        var btn = document.getElementById('sendBtn'); btn.disabled = true;
        var fd = new FormData(this);
        var r = await fetch('send_msg.php', {method:'POST', body:fd});
        var j = await r.json();
        if(j.ok){ 
            this.querySelector('textarea').value = ''; 
            this.querySelector('input[type=file]').value = '';
            cancelQuote();
            location.reload(); // 简单粗暴，发完就刷新
        } else {
            alert('发送失败: ' + (j.msg || '未知错误'));
        }
        btn.disabled = false;
    };
    
    setInterval(async function(){
        var r = await fetch('pull.php?with=<?=$with?>&last=' + lastMid);
        var j = await r.json();
        if(j.messages && j.messages.length){
            location.reload(); // 收到新消息直接刷新
        }
    }, 3000);
    </script>
<?php endif; endif; ?>
<?php page_end(); ?>
