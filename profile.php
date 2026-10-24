<?php
require_once __DIR__ . '/src/init.php';

if(empty($_SESSION['uid'])){ header('Location: index.php?action=login'); exit; }
$uid = (int)$_SESSION['uid'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE uid = ?'); $stmt->execute([$uid]); $urow = $stmt->fetch();

$target_uid = isset($_GET['uid']) ? (int)$_GET['uid'] : $uid;
$is_self = ($target_uid === $uid);
$msg = '';
$err = '';

// ==================== 处理资料修改 ====================
if($is_self && $_SERVER['REQUEST_METHOD'] === 'POST'){
    $mode = $_POST['mode'] ?? '';

    // 更新基本信息
    if($mode === 'profile'){
        $nickname = mb_substr(trim($_POST['nickname'] ?? ''), 0, 50);
        $bio = mb_substr(trim($_POST['bio'] ?? ''), 0, 500);
        
        $pdo->prepare('UPDATE users SET nickname=?, bio=? WHERE uid=?')->execute([$nickname, $bio, $uid]);
        $msg = '资料已更新';
        // 刷新数据
        $stmt = $pdo->prepare('SELECT * FROM users WHERE uid = ?'); $stmt->execute([$uid]); $urow = $stmt->fetch();
    }

    // 修改密码
    if($mode === 'password'){
        $old = $_POST['old_pwd'] ?? '';
        $new = $_POST['new_pwd'] ?? '';
        if(!password_verify($old, $urow['password_hash'])){
            $err = '原密码错误';
        } elseif(strlen($new) < 6){
            $err = '新密码至少 6 位';
        } else {
            $pdo->prepare('UPDATE users SET password_hash=? WHERE uid=?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            $msg = '密码已修改';
        }
    }

    // 上传头像
    if($mode === 'avatar' && !empty($_FILES['avatar']['name'])){
        $f = $_FILES['avatar'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if($f['error'] !== UPLOAD_ERR_OK){
            $err = '上传失败';
        } elseif(!in_array($ext, ['jpg','jpeg','png','webp','gif'])){
            $err = '只支持 jpg/png/webp/gif';
        } elseif($f['size'] > 2*1024*1024){
            $err = '头像不能超过 2MB';
        } else {
            $fn = 'avatar_'.$uid.'_'.time().'.'.$ext;
            if(move_uploaded_file($f['tmp_name'], __DIR__.'/uploads/'.$fn)){
                // 删除旧头像
                if(!empty($urow['avatar']) && file_exists(__DIR__.'/'.$urow['avatar'])) @unlink(__DIR__.'/'.$urow['avatar']);
                $pdo->prepare('UPDATE users SET avatar=? WHERE uid=?')->execute(['uploads/'.$fn, $uid]);
                $urow['avatar'] = 'uploads/'.$fn;
                $msg = '头像已更新';
            } else {
                $err = '保存失败';
            }
        }
    }
}

// 获取目标用户信息
$stmt = $pdo->prepare('SELECT * FROM users WHERE uid = ?'); $stmt->execute([$target_uid]); $target = $stmt->fetch();
if(!$target) die('用户不存在');

// ==================== 拉取用户动态 ====================
// 帖子 (看别人隐藏匿名)
$sql_p = 'SELECT * FROM posts WHERE uid = ?' . ($is_self ? '' : ' AND is_anon = 0') . ' ORDER BY pid DESC LIMIT 50';
$stmt = $pdo->prepare($sql_p); $stmt->execute([$target_uid]); $posts = $stmt->fetchAll();

// 评论 (看别人隐藏匿名)
$sql_c = 'SELECT c.*, p.pid AS post_pid FROM comments c JOIN posts p ON c.pid = p.pid WHERE c.uid = ?' . ($is_self ? '' : ' AND c.is_anon = 0') . ' ORDER BY c.cid DESC LIMIT 50';
$stmt = $pdo->prepare($sql_c); $stmt->execute([$target_uid]); $comments = $stmt->fetchAll();

// 积分流水 (仅自己可见)
$point_logs = [];
if($is_self){
    $stmt = $pdo->prepare('SELECT * FROM point_logs WHERE uid = ? ORDER BY id DESC LIMIT 50');
    $stmt->execute([$uid]);
    $point_logs = $stmt->fetchAll();
}

page_head('个人主页'); ?>

<style>
.profile-header { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
.profile-header .avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #eee; }
.profile-header .avatar-default { width: 80px; height: 80px; border-radius: 50%; background: var(--main); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 32px; }
.tab-btn { display: inline-block; padding: 8px 16px; cursor: pointer; border-bottom: 2px solid transparent; color: #666; }
.tab-btn.active { border-bottom-color: var(--main); color: var(--main); font-weight: bold; }
.tab-content { display: none; margin-top: 15px; }
.tab-content.active { display: block; }
.log-item { border-bottom: 1px dashed #eee; padding: 8px 0; font-size: 13px; }
</style>

<div class="card profile-header">
    <?php if($target['avatar']): ?>
        <img class="avatar" src="<?=h($target['avatar'])?>">
    <?php else: ?>
        <div class="avatar-default"><?=h(mb_substr(show_name($target), 0, 1))?></div>
    <?php endif; ?>
    
    <div style="flex:1;min-width:200px">
        <h2 style="margin:0 0 4px"><?=h(show_name($target))?> <?php if($target['uid'] == SUPER_UID) echo '<span class="badge-super">超管</span>'; elseif($target['is_admin']) echo '<span class="badge-admin">管理员</span>'; ?></h2>
        <p style="margin:0;color:#888;font-size:13px">@<?=h($target['username'])?> · UID: <?=$target['uid']?> · 注册于 <?=date('Y-m-d', strtotime($target['created_at']))?></p>
        <p style="margin:6px 0 0;color:var(--main);font-weight:bold">💰 积分：<?=$target['points']?></p>
        <?php if($target['bio']): ?><p style="margin:8px 0 0;color:#444"><?=nl2br(h($target['bio']))?></p><?php endif; ?>
    </div>

    <div>
        <?php if(!$is_self): ?>
            <a class="fl" href="index1.php?with=<?=$target['uid']?>">私聊 TA</a>
        <?php endif; ?>
    </div>
</div>

<?php if($msg): ?><p class="ok"><?=h($msg)?></p><?php endif; ?>
<?php if($err): ?><p class="err"><?=h($err)?></p><?php endif; ?>

<?php if($is_self): ?>
<div class="card">
    <h3>⚙️ 账号设置</h3>
    <div style="display:flex;gap:20px;flex-wrap:wrap">
        <form method="post" style="flex:1;min-width:250px">
            <input type="hidden" name="mode" value="profile">
            <label>昵称</label>
            <input type="text" name="nickname" value="<?=h($target['nickname'])?>" placeholder="留空则显示用户名">
            <label>简介</label>
            <textarea name="bio" style="min-height:60px"><?=h($target['bio'])?></textarea>
            <button type="submit">保存资料</button>
        </form>
        
        <form method="post" style="flex:1;min-width:250px">
            <input type="hidden" name="mode" value="avatar">
            <label>更换头像 (最大 2MB)</label>
            <input type="file" name="avatar" accept="image/*" required>
            <button type="submit">上传头像</button>
        </form>

        <form method="post" style="flex:1;min-width:250px">
            <input type="hidden" name="mode" value="password">
            <label>修改密码</label>
            <input type="password" name="old_pwd" placeholder="原密码" required>
            <input type="password" name="new_pwd" placeholder="新密码 (至少6位)" required>
            <button type="submit">确认修改</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div style="border-bottom:1px solid #eee; margin-bottom:10px;">
        <span class="tab-btn active" onclick="switchTab('posts')">📝 TA的帖子 (<?=count($posts)?>)</span>
        <span class="tab-btn" onclick="switchTab('comments')">💬 TA的评论 (<?=count($comments)?>)</span>
        <?php if($is_self): ?>
            <span class="tab-btn" onclick="switchTab('points')">💰 积分流水</span>
        <?php endif; ?>
    </div>

    <!-- 帖子列表 -->
    <div id="tab-posts" class="tab-content active">
        <?php if(!$posts): ?><p style="color:#888">暂无帖子</p><?php endif; ?>
        <?php foreach($posts as $p): ?>
            <div class="post" style="padding:10px;margin:8px 0">
                <div class="meta">
                    <?=$p['is_anon'] ? '<span class="anon-tag">匿名</span>' : ''?>
                    · <?=h($p['created_at'])?> 
                    <a href="index.php#p<?=$p['pid']?>" style="float:right;color:var(--main)">查看原帖 →</a>
                </div>
                <div class="md"><?=h(mb_substr($p['content'], 0, 200))?><?=mb_strlen($p['content'])>200?'...':''?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- 评论列表 -->
    <div id="tab-comments" class="tab-content">
        <?php if(!$comments): ?><p style="color:#888">暂无评论</p><?php endif; ?>
        <?php foreach($comments as $c): ?>
            <div class="post" style="padding:10px;margin:8px 0">
                <div class="meta">
                    <?=$c['is_anon'] ? '<span class="anon-tag">匿名</span>' : ''?>
                    · 评论于 <?=h($c['created_at'])?>
                    <a href="index.php#p<?=$c['post_pid']?>" style="float:right;color:var(--main)">查看原帖 →</a>
                </div>
                <div class="md"><?=h(mb_substr($c['content'], 0, 200))?><?=mb_strlen($c['content'])>200?'...':''?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- 积分流水 (仅自己) -->
    <?php if($is_self): ?>
    <div id="tab-points" class="tab-content">
        <?php if(!$point_logs): ?><p style="color:#888">暂无流水</p><?php endif; ?>
        <?php foreach($point_logs as $l): $d = (int)$l['delta']; ?>
            <div class="log-item">
                <span style="float:right;font-weight:bold;color:<?=$d>=0?'green':'red'?>"><?=$d>=0?'+':''?><?=$d?></span>
                <b><?=h($l['reason'])?></b> <span style="color:#888;margin-left:10px"><?=h($l['created_at'])?></span>
                <?php if($l['ref_id']): ?><span style="color:#aaa;font-size:11px;margin-left:10px">(关联 ID: <?=$l['ref_id']?>)</span><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function switchTab(name){
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    
    // 找到对应按钮高亮 (简单通过索引查)
    if(name === 'posts') document.querySelectorAll('.tab-btn')[0].classList.add('active');
    if(name === 'comments') document.querySelectorAll('.tab-btn')[1].classList.add('active');
    if(name === 'points') document.querySelectorAll('.tab-btn')[2].classList.add('active');
    
    document.getElementById('tab-' + name).classList.add('active');
    
    if(window.mdRenderAll) window.mdRenderAll();
}
</script>

<?php page_end(); ?>
