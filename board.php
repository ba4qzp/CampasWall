<?php
if(!defined('SITE_NAME')){ die('非法访问'); }
$search = trim($_GET['q'] ?? '');
$where = ['p.hidden = 0']; $params = [];
if($search){ $where[] = '(p.content LIKE ? OR p.tags LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$sql = 'SELECT p.*, u.username, u.nickname, u.avatar, u.gender, u.grade, u.class_name, u.show_info FROM posts p JOIN users u ON p.uid = u.uid WHERE '.implode(' AND ', $where).' ORDER BY p.pid DESC LIMIT 200';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $posts = $stmt->fetchAll();

$pids = array_column($posts, 'pid'); $myLikes = []; $likeCounts = [];
if($pids){
    $place = implode(',', array_fill(0, count($pids), '?'));
    $s2 = $pdo->prepare("SELECT pid, COUNT(*) c FROM likes WHERE pid IN ($place) GROUP BY pid"); $s2->execute($pids);
    while($r = $s2->fetch()) $likeCounts[$r['pid']] = $r['c'];
    $s3 = $pdo->prepare("SELECT pid FROM likes WHERE uid = ? AND pid IN ($place)"); $s3->execute(array_merge([$uid], $pids));
    while($r = $s3->fetch()) $myLikes[$r['pid']] = true;
}
page_head('首页'); ?>

<div class="card" style="text-align:center">
    <a class="fl" href="javascript:void(0)" onclick="document.getElementById('postForm').style.display='block'">✏️ 发帖</a>
</div>

<div class="card" id="postForm" style="display:none">
    <form method="post" action="?action=post" id="pForm" enctype="multipart/form-data">
        <textarea name="content" placeholder="说点什么..."></textarea>
        <div class="hint">Markdown：`**粗体**` · `*斜体*` · `` `代码` `` · `- 列表`</div>
        <input type="text" name="tags" placeholder="标签（逗号分隔）">
        <input type="file" id="fileInput" multiple>
        <div class="progress" id="progWrap" style="display:none"><div id="progBar" style="width:0"></div></div>
        <input type="hidden" name="files_json" id="filesJson">
        <label><input type="checkbox" name="is_anon" value="1"> 匿名</label>
        <input type="text" name="pwd" placeholder="6位数字密码(可选)" pattern="\d{6}">
        <button type="submit" id="subBtn">发布</button>
    </form>
</div>

<div class="card">
    <form method="get"><input type="text" name="q" placeholder="搜索帖子或标签..." value="<?=h($search)?>"><button type="submit">搜索</button></form>
</div>

<?php foreach($posts as $p):
    $is_owner = ((int)$p['uid'] === $uid); $files = json_decode($p['files'] ?: '[]', true);
    $locked = ($p['password'] && !$is_admin && !$is_owner && empty($_SESSION['unlocked_posts'][$p['pid']]));
    ?>
    <div class="post" id="p<?=$p['pid']?>">
        <div class="meta">
            <?php if($p['is_anon']): ?>
                <span class="anon-tag">匿名</span>
            <?php else: ?>
                <img src="<?=get_avatar($p)?>" style="width:20px;height:20px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:4px;">
                <a href="profile.php?uid=<?=$p['uid']?>"><?=h(show_name($p))?></a>
                <?php if($p['show_info']): 
                    $g_icon = $p['gender']=='male'?'♂':($p['gender']=='female'?'♀':'');
                    $info = $g_icon . ($p['grade'] ? $p['grade'] : '') . ($p['class_name'] ? $p['class_name'] : '');
                    if($info) echo "<span style='font-size:11px;color:#888;margin-left:5px;border:1px solid #ddd;padding:1px 4px;border-radius:4px;'>{$info}</span>";
                endif; ?>
            <?php endif; ?>
            · <?=h($p['created_at'])?>
            
            <span style="margin-left:auto">
                <?php if($is_admin || $is_owner): ?>
                    <a href="?action=toggle_comments&pid=<?=$p['pid']?>" style="color:var(--gray);margin-right:8px;">
                        <?=$p['closed'] ? '打开评论区' : '关闭评论区'?>
                    </a>
                    <a href="?action=delete_post&pid=<?=$p['pid']?>" onclick="return confirm('删帖？')" style="color:red">删除帖子</a>
                <?php endif; ?>
            </span>
        </div>
        
        <?php if($locked): ?>
            <form method="post" action="?action=unlock_post&pid=<?=$p['pid']?>">
                <p>密码帖：<input type="text" name="pwd" style="width:120px"> <button type="submit">解锁</button></p>
            </form>
        <?php else: ?>
            <div class="md"><?=h($p['content'])?></div>
            <?php foreach($files as $f){ if($f['img']) echo '<img class="post-img" src="'.h($f['path']).'">'; else echo '<a href="'.h($f['path']).'">📎 '.h($f['orig']).'</a>'; } ?>
            
            <div style="margin-top:10px">
                <form method="post" action="?action=like" style="display:inline">
                    <input type="hidden" name="pid" value="<?=$p['pid']?>">
                    <button type="submit" class="fl <?=isset($myLikes[$p['pid']])?'red':'gray'?>">❤ <?=$likeCounts[$p['pid']]??0?></button>
                </form>
            </div>

            <div style="margin-top:10px;border-top:1px solid #eee;padding-top:8px">
                <?php if($p['closed']): ?>
                    <p class="hint" style="color:#c00">评论区已关闭</p>
                <?php else: 
                    $cstmt = $pdo->prepare('SELECT c.*, u.username, u.nickname, u.avatar, u.gender, u.grade, u.class_name, u.show_info FROM comments c JOIN users u ON c.uid = u.uid WHERE c.pid = ? AND c.hidden = 0 ORDER BY c.cid ASC');
                    $cstmt->execute([$p['pid']]);
                    while($c = $cstmt->fetch()):
                        $can_del_c = ($is_admin || $is_owner || ((int)$c['uid'] === $uid));
                ?>
                    <div style="margin:8px 0;font-size:13px">
                        <div class="meta" style="display:inline-flex;margin:0">
                            <?php if($c['is_anon']): ?>
                                <span class="anon-tag">匿名</span>
                            <?php else: ?>
                                <b><?=h(show_name($c))?></b>
                                <?php if($c['show_info']): 
                                    $g_icon = $c['gender']=='male'?'♂':($c['gender']=='female'?'♀':'');
                                    $info = $g_icon . ($c['grade'] ? $c['grade'] : '') . ($c['class_name'] ? $c['class_name'] : '');
                                    if($info) echo "<span style='font-size:11px;color:#888;margin-left:5px;border:1px solid #ddd;padding:1px 4px;border-radius:4px;'>{$info}</span>";
                                endif; ?>
                            <?php endif; ?>
                            ：<?=nl2br(h($c['content']))?>
                            <?php if($can_del_c): ?>
                                <a href="?action=del_comment&cid=<?=$c['cid']?>" onclick="return confirm('删评论？')" style="color:#dc2626;margin-left:8px;">删除</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
                
                <form method="post" action="?action=comment" style="margin-top:10px">
                    <input type="hidden" name="pid" value="<?=$p['pid']?>">
                    <div style="display:flex;gap:6px;align-items:center">
                        <input type="text" name="content" placeholder="写评论..." required style="flex:1">
                        <label style="white-space:nowrap;font-size:13px"><input type="checkbox" name="is_anon" value="1"> 匿名</label>
                        <button type="submit">发送</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<script>
const CHUNK_SIZE = 4 * 1024 * 1024, form = document.getElementById('pForm');
if(form){
    form.addEventListener('submit', async (e)=>{
        const files = document.getElementById('fileInput').files;
        if(!files.length) return;
        e.preventDefault(); document.getElementById('subBtn').disabled = true;
        document.getElementById('progWrap').style.display = 'block';
        let res = [];
        for(let file of files){
            let id = [...crypto.getRandomValues(new Uint8Array(16))].map(b=>b.toString(16).padStart(2,'0')).join('');
            let total = Math.ceil(file.size / CHUNK_SIZE);
            for(let i=0;i<total;i++){
                let fd = new FormData(); fd.append('fileId', id); fd.append('chunkIndex', i); fd.append('totalChunks', total); fd.append('filename', file.name); fd.append('chunk', file.slice(i*CHUNK_SIZE, (i+1)*CHUNK_SIZE));
                let r = await fetch('upload.php', {method:'POST', body:fd}); let j = await r.json();
                if(j.path) res.push(j);
                document.getElementById('progBar').style.width = ((i+1)/total*100) + '%';
            }
        }
        document.getElementById('filesJson').value = JSON.stringify(res);
        HTMLFormElement.prototype.submit.call(form);
    });
}
</script>
<?php page_end(); ?>
