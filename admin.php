<?php
require_once __DIR__ . '/src/init.php';

if(empty($_SESSION['uid'])){ header('Location: index.php?action=login'); exit; }
$uid = (int)$_SESSION['uid'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE uid = ?'); $stmt->execute([$uid]); $urow = $stmt->fetch();
if(!$urow || (int)$urow['is_admin'] !== 1){ die('无权访问后台'); }
$is_super = ($uid === SUPER_UID);
$msg = '';

// ==================== 处理 POST 请求 ====================
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $mode = $_POST['mode'] ?? '';

    // 1. 更新公告
    if($mode === 'announce'){
        $content = trim($_POST['content'] ?? '');
        $pdo->prepare('UPDATE announcements SET content = ? WHERE aid = 1')->execute([$content]);
        $msg = '公告已更新';
    }
    
    // 2. 修改用户资料 (积分、密码等)
    if($mode === 'edit_user'){
        $tid = (int)$_POST['tid'];
        $new_points = (int)$_POST['points'];
        $new_pwd = trim($_POST['newpwd'] ?? '');
        
        $stmt = $pdo->prepare('SELECT points FROM users WHERE uid = ?'); $stmt->execute([$tid]); $old_points = (int)$stmt->fetchColumn();
        
        if($new_points !== $old_points){
            $delta = $new_points - $old_points;
            add_points($pdo, $tid, $delta, 'admin_adjust', $uid);
            // 记录管理员操作
            $pdo->prepare('INSERT INTO user_actions (uid, admin_uid, action, detail) VALUES (?,?,?,?)')->execute([$tid, $uid, '积分变更', '管理员将你的积分调整了 '.($delta>0?'+':'').$delta]);
        }
        if($new_pwd !== ''){
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE uid = ?')->execute([password_hash($new_pwd, PASSWORD_DEFAULT), $tid]);
        }
        $msg = '用户资料已更新';
    }
}

// ==================== 处理 GET 请求 ====================
$action = $_GET['action'] ?? '';

// 审核通过
if($action === 'approve' && isset($_GET['id'])){
    $pdo->prepare('UPDATE users SET status = "active" WHERE uid = ?')->execute([(int)$_GET['id']]);
    $pdo->prepare('INSERT INTO user_actions (uid, admin_uid, action, detail) VALUES (?,?,?,?)')->execute([(int)$_GET['id'], $uid, '注册通过', '你的注册申请已通过']);
    header('Location: admin.php'); exit;
}

// 拒绝审核 (直接删)
if($action === 'reject' && isset($_GET['id'])){
    $tid = (int)$_GET['id'];
    $pdo->prepare('DELETE FROM users WHERE uid = ? AND status = "pending"')->execute([$tid]);
    header('Location: admin.php'); exit;
}

// 冻结/解冻
if($action === 'toggle_ban' && isset($_GET['id'])){
    $tid = (int)$_GET['id'];
    if($tid !== $uid){
        $pdo->prepare('UPDATE users SET ng = 1 - ng WHERE uid = ?')->execute([$tid]);
        $pdo->prepare('INSERT INTO user_actions (uid, admin_uid, action, detail) VALUES (?,?,?,?)')->execute([$tid, $uid, '账号状态变更', '你的账号冻结状态已被切换']);
    }
    header('Location: admin.php'); exit;
}

// 提权/降权 (仅超管)
if($action === 'toggle_admin' && isset($_GET['id']) && $is_super){
    $tid = (int)$_GET['id'];
    if($tid !== $uid && $tid !== SUPER_UID){
        $pdo->prepare('UPDATE users SET is_admin = 1 - is_admin WHERE uid = ?')->execute([$tid]);
    }
    header('Location: admin.php'); exit;
}

// 删帖
if($action === 'del_post' && isset($_GET['id'])){
    $pid = (int)$_GET['id'];
    $stmt = $pdo->prepare('SELECT uid FROM posts WHERE pid = ?'); $stmt->execute([$pid]); $post_owner = $stmt->fetchColumn();
    if($post_owner !== false){
        $pdo->prepare('DELETE FROM posts WHERE pid = ?')->execute([$pid]);
        $pdo->prepare('DELETE FROM comments WHERE pid = ?')->execute([$pid]);
        if($post_owner != $uid){
            $pdo->prepare('INSERT INTO user_actions (uid, admin_uid, action, detail) VALUES (?,?,?,?)')->execute([$post_owner, $uid, '帖子被删除', '你的帖子因违规被管理员删除']);
        }
    }
    header('Location: admin.php?tab=posts'); exit;
}

// 删集市
if($action === 'del_market' && isset($_GET['id'])){
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare('SELECT uid FROM market_items WHERE id = ?'); $stmt->execute([$id]); $m_owner = $stmt->fetchColumn();
    if($m_owner !== false){
        $pdo->prepare('DELETE FROM market_items WHERE id = ?')->execute([$id]);
        if($m_owner != $uid){
            $pdo->prepare('INSERT INTO user_actions (uid, admin_uid, action, detail) VALUES (?,?,?,?)')->execute([$m_owner, $uid, '集市物品被删除', '你的集市物品因违规被管理员删除']);
        }
    }
    header('Location: admin.php?tab=market'); exit;
}

// 限制/放开私聊 (这里简单用用户状态字段扩展，或者暂时不需要)
// 获取当前 Tab
$tab = $_GET['tab'] ?? 'dashboard';

// 拉取数据
$ann = $pdo->query('SELECT content FROM announcements WHERE aid = 1')->fetchColumn();
$pending_users = $pdo->query('SELECT * FROM users WHERE status = "pending" ORDER BY uid ASC')->fetchAll();
$users = $pdo->query('SELECT * FROM users ORDER BY uid DESC LIMIT 100')->fetchAll();
$posts = $pdo->query('SELECT p.*, u.username FROM posts p JOIN users u ON p.uid = u.uid ORDER BY p.pid DESC LIMIT 100')->fetchAll();
$markets = $pdo->query('SELECT m.*, u.username FROM market_items m JOIN users u ON m.uid = u.uid ORDER BY m.id DESC LIMIT 100')->fetchAll();
$logs = $pdo->query('SELECT * FROM ip_logs ORDER BY lid DESC LIMIT 100')->fetchAll();

page_head('管理后台'); ?>

<style>
.nav-tabs { display: flex; gap: 10px; border-bottom: 1px solid #ddd; margin-bottom: 15px; flex-wrap: wrap; }
.nav-tabs a { padding: 8px 16px; text-decoration: none; color: #555; border-radius: 4px 4px 0 0; background: #f5f5f5; }
.nav-tabs a.active { background: #fff; border: 1px solid #ddd; border-bottom-color: #fff; margin-bottom: -1px; color: #2563eb; font-weight: bold; }
table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
th, td { border: 1px solid #eee; padding: 8px; text-align: left; }
th { background: #fafafa; }
.btn-sm { padding: 4px 8px; font-size: 12px; }
</style>

<div class="nav-tabs">
    <a href="?tab=dashboard" class="<?=$tab=='dashboard'?'active':''?>">概览 & 公告</a>
    <a href="?tab=users" class="<?=$tab=='users'?'active':''?>">用户管理 <?=count($pending_users)>0?'('.count($pending_users).')':''?></a>
    <a href="?tab=posts" class="<?=$tab=='posts'?'active':''?>">帖子管理</a>
    <a href="?tab=market" class="<?=$tab=='market'?'active':''?>">集市管理</a>
    <a href="?tab=logs" class="<?=$tab=='logs'?'active':''?>">IP 日志</a>
</div>

<?php if($msg): ?><div class="ok" style="margin-bottom:10px;padding:10px;background:#e8f5e9;border-radius:4px"><?=h($msg)?></div><?php endif; ?>

<!-- 概览与公告 -->
<?php if($tab === 'dashboard'): ?>
    <div class="card">
        <h3>系统数据</h3>
        <p>总用户数：<b><?=count($users)?></b> | 待审核用户：<b><?=count($pending_users)?></b></p>
        <p>最近帖子数：<b><?=count($posts)?></b> | 集市物品：<b><?=count($markets)?></b></p>
    </div>
    <div class="card">
        <h3>全局公告</h3>
        <form method="post">
            <input type="hidden" name="mode" value="announce">
            <textarea name="content" style="width:100%;min-height:80px"><?=h($ann)?></textarea>
            <button type="submit">保存公告</button>
        </form>
    </div>
<?php endif; ?>

<!-- 用户管理 -->
<?php if($tab === 'users'): ?>
    <?php if($pending_users): ?>
    <div class="card" style="border: 1px solid #f5c6cb; background: #fff8f8;">
        <h3 style="color:#c00">⚠️ 待审核用户</h3>
        <table>
            <tr><th>UID</th><th>用户名</th><th>邮箱</th><th>注册时间</th><th>操作</th></tr>
            <?php foreach($pending_users as $pu): ?>
                <tr>
                    <td><?=$pu['uid']?></td>
                    <td><?=h($pu['username'])?></td>
                    <td><?=h($pu['email'])?></td>
                    <td><?=$pu['created_at']?></td>
                    <td>
                        <a href="?action=approve&id=<?=$pu['uid']?>" class="fl green btn-sm">通过</a>
                        <a href="?action=reject&id=<?=$pu['uid']?>" class="fl red btn-sm" onclick="return confirm('拒绝并删除该用户？')">拒绝</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3>用户列表</h3>
        <table>
            <tr><th>UID</th><th>用户名</th><th>积分</th><th>状态</th><th>操作</th></tr>
            <?php foreach($users as $u): ?>
                <tr>
                    <td><?=$u['uid']?> <?=$u['uid']==SUPER_UID?'<span class="badge-super">超管</span>':''?></td>
                    <td><?=h($u['username'])?> <?=$u['is_admin']&&$u['uid']!=SUPER_UID?'<span class="badge-admin">管理</span>':''?></td>
                    <td><?=$u['points']?></td>
                    <td><?=$u['ng']?'<span style="color:red">冻结</span>':'<span style="color:green">正常</span>'?></td>
                    <td>
                        <?php if($u['uid'] != $uid): ?>
                            <a href="?action=toggle_ban&id=<?=$u['uid']?>" class="fl gray btn-sm"><?=$u['ng']?'解冻':'冻结'?></a>
                            <?php if($is_super && $u['uid'] != SUPER_UID): ?>
                                <a href="?action=toggle_admin&id=<?=$u['uid']?>" class="fl btn-sm"><?=$u['is_admin']?'降权':'提权'?></a>
                            <?php endif; ?>
                            <button class="btn-sm" onclick="editUser(<?=$u['uid']?>, '<?=h($u['username'])?>', <?=$u['points']?>)">修改</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <!-- 修改用户弹窗 -->
    <div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:999">
        <div style="background:#fff;padding:20px;border-radius:8px;width:300px">
            <h3 id="eu_title"></h3>
            <form method="post">
                <input type="hidden" name="mode" value="edit_user">
                <input type="hidden" name="tid" id="eu_id">
                <label>积分</label>
                <input type="number" name="points" id="eu_points" style="width:100%">
                <label>重置密码 (留空不改)</label>
                <input type="text" name="newpwd" style="width:100%">
                <div style="margin-top:10px;text-align:right">
                    <button type="button" class="fl gray" onclick="document.getElementById('editModal').style.display='none'">取消</button>
                    <button type="submit" class="fl">保存</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    function editUser(id, name, pts){
        document.getElementById('eu_id').value = id;
        document.getElementById('eu_title').innerText = '修改用户: ' + name;
        document.getElementById('eu_points').value = pts;
        document.getElementById('editModal').style.display = 'flex';
    }
    </script>
<?php endif; ?>

<!-- 帖子管理 -->
<?php if($tab === 'posts'): ?>
    <div class="card">
        <h3>最新帖子</h3>
        <table>
            <tr><th>PID</th><th>作者</th><th>内容 (截取)</th><th>时间</th><th>操作</th></tr>
            <?php foreach($posts as $p): ?>
                <tr>
                    <td><?=$p['pid']?></td>
                    <td><?=h($p['username'])?></td>
                    <td><?=h(mb_substr($p['content'], 0, 30))?>...</td>
                    <td><?=$p['created_at']?></td>
                    <td><a href="?action=del_post&id=<?=$p['pid']?>" class="fl red btn-sm" onclick="return confirm('确定删除该帖子及评论？')">删除</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>

<!-- 集市管理 -->
<?php if($tab === 'market'): ?>
    <div class="card">
        <h3>集市物品</h3>
        <table>
            <tr><th>ID</th><th>卖家</th><th>标题</th><th>价格</th><th>状态</th><th>操作</th></tr>
            <?php foreach($markets as $m): ?>
                <tr>
                    <td><?=$m['id']?></td>
                    <td><?=h($m['username'])?></td>
                    <td><?=h($m['title'])?></td>
                    <td><?=$m['price']?></td>
                    <td><?=$m['status']?'在售':'已结束'?></td>
                    <td><a href="?action=del_market&id=<?=$m['id']?>" class="fl red btn-sm" onclick="return confirm('确定删除该物品？')">删除</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>

<!-- 日志 -->
<?php if($tab === 'logs'): ?>
    <div class="card">
        <h3>IP 日志 (最近100条)</h3>
        <table>
            <tr><th>时间</th><th>UID</th><th>IP</th><th>路径</th></tr>
            <?php foreach($logs as $l): ?>
                <tr>
                    <td><?=$l['created_at']?></td>
                    <td><?=$l['uid'] ?: '-'?></td>
                    <td><?=h($l['ip'])?></td>
                    <td><?=h($l['path'])?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>

<?php page_end(); ?>
