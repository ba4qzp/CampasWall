<?php
require_once __DIR__ . '/src/init.php';
if(empty($_SESSION['uid'])){ header('Location: index.php?action=login'); exit; }
$uid = (int)$_SESSION['uid'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE uid = ?'); $stmt->execute([$uid]); $urow = $stmt->fetch();
$is_admin = ((int)$urow['is_admin'] === 1);
$msg = ''; $err = '';

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ★ 购买逻辑 (原 ?buy=)
if($action === 'buy_item' && $id > 0){
    $stmt = $pdo->prepare('SELECT * FROM market_items WHERE id = ? AND status = 1'); $stmt->execute([$id]); $item = $stmt->fetch();
    if($item){
        if($item['uid'] == $uid) $msg = '不能购买自己的物品。';
        elseif($urow['points'] < $item['price']) $msg = '你的积分不足！';
        else {
            $pdo->beginTransaction();
            try {
                add_points($pdo, $uid, -$item['price'], 'buy_item', $id);
                add_points($pdo, $item['uid'], $item['price'], 'sell_item', $id);
                $pdo->prepare('UPDATE market_items SET status = 0 WHERE id = ?')->execute([$id]);
                $pdo->prepare('INSERT INTO market_logs (item_id, buyer_uid, seller_uid, price) VALUES (?,?,?,?)')->execute([$id, $uid, $item['uid'], $item['price']]);
                $notify_msg = '你的集市物品「' . $item['title'] . '」已被卖出！获得 ' . $item['price'] . ' 积分。';
                $pdo->prepare('INSERT INTO user_actions (uid, admin_uid, action, detail) VALUES (?,?,?,?)')->execute([$item['uid'], $uid, '集市售出', $notify_msg]);
                $pdo->commit(); $msg = '购买成功！已通知卖家。';
            } catch(Exception $e){ $pdo->rollBack(); $msg = '购买失败，请重试。'; }
        }
    }
    header('Location: market.php'); exit;
}

// ★ 上架/下架逻辑 (原 ?toggle=)
if($action === 'change_status' && $id > 0){
    $stmt = $pdo->prepare('SELECT uid, status FROM market_items WHERE id = ?'); $stmt->execute([$id]); $item = $stmt->fetch();
    if($item && ($item['uid'] == $uid || $is_admin)){
        $pdo->prepare('UPDATE market_items SET status = 1 - status WHERE id = ?')->execute([$id]);
    }
    header('Location: market.php'); exit;
}

// ★ 删除逻辑 (原 ?del=)
if($action === 'remove_market' && $id > 0 && $is_admin){
    $pdo->prepare('DELETE FROM market_items WHERE id = ?')->execute([$id]);
    header('Location: market.php'); exit;
}

// 发布物品逻辑
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])){
    $title = trim($_POST['title']); $price = (int)$_POST['price']; $desc = trim($_POST['desc_text']);
    $image_path = '';
    
    if(!empty($_FILES['image']['name'])){
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, ['jpg','jpeg','png','webp','gif']) && $_FILES['image']['size'] < 2*1024*1024){
            $fn = 'market_'.bin2hex(random_bytes(8)).'.'.$ext;
            if(move_uploaded_file($_FILES['image']['tmp_name'], __DIR__.'/uploads/'.$fn)){ $image_path = 'uploads/'.$fn; }
        } else { $err = '图片格式不对或超过2MB'; }
    }

    if($title && $price > 0){
        $pdo->prepare('INSERT INTO market_items (uid, title, desc_text, price, image_path) VALUES (?,?,?,?,?)')->execute([$uid, $title, $desc, $price, $image_path]);
        header('Location: market.php'); exit;
    } else { $err = '请填写标题和价格'; }
}

page_head('积分集市'); ?>

<?php if($msg): ?><p class="ok"><?=h($msg)?></p><?php endif; ?>
<?php if($err): ?><p class="err"><?=h($err)?></p><?php endif; ?>

<div class="card">
    <h3>发布闲置/悬赏 (当前积分: <?=(int)$urow['points']?>)</h3>
    <!-- ★ 加上了 enctype，文件才能传上去 -->
    <form method="post" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="物品名称/悬赏内容" required>
        <input type="number" name="price" placeholder="价格(积分)" min="1" required>
        <textarea name="desc_text" placeholder="详细描述..."></textarea>
        <label>上传图片 (最大 2MB)</label>
        <input type="file" name="image" accept="image/*">
        <button type="submit" name="add_item" value="1">发布</button>
    </form>
</div>

<div class="card">
    <h3>集市列表</h3>
    <?php
    $stmt = $pdo->query('SELECT m.*, u.username, u.nickname FROM market_items m JOIN users u ON m.uid = u.uid ORDER BY m.id DESC');
    while($m = $stmt->fetch()){
        $st = $m['status'] ? '<span class="badge-ok">在售</span>' : '<span class="badge-warn">已下架/已售出</span>';
        echo '<div class="post"><div class="meta"><b>'.h($m['title']).'</b> - '.$m['price'].'积分 '.$st.' <span class="hint">卖家: '.h(show_name($m)).'</span></div>';
        if($m['image_path']) echo '<img class="post-img" src="'.h($m['image_path']).'" style="max-height:200px;object-fit:cover;">';
        echo '<div class="md">'.h($m['desc_text']).'</div><div style="margin-top:8px">';
        
        // ★ 所有的链接都改成了标准的 ?action=xxx&id=xxx 形式
        if($m['status'] && $m['uid'] != $uid) echo '<a href="?action=buy_item&id='.$m['id'].'" class="fl green" onclick="return confirm(\'花费 '.$m['price'].' 积分购买？\')">购买</a> ';
        if($m['uid'] == $uid || $is_admin) echo '<a href="?action=change_status&id='.$m['id'].'" class="fl gray">'.($m['status']?'下架':'上架').'</a> ';
        if($is_admin) echo '<a href="?action=remove_market&id='.$m['id'].'" class="fl red" onclick="return confirm(\'删除？\')">删除</a>';
        
        echo '</div></div>';
    }
    ?>
</div>
<?php page_end(); ?>
