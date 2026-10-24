<?php
require_once __DIR__ . '/src/init.php';
if(empty($_SESSION['uid'])){ header('Location: index.php?action=login'); exit; }
$uid = (int)$_SESSION['uid'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE uid = ?'); $stmt->execute([$uid]); $urow = $stmt->fetch();
$is_admin = ((int)$urow['is_admin'] === 1);
$msg = '';

// 购买逻辑
if(isset($_GET['buy']) && (int)$_GET['buy'] > 0){
    $item_id = (int)$_GET['buy'];
    $stmt = $pdo->prepare('SELECT * FROM market_items WHERE id = ? AND status = 1'); $stmt->execute([$item_id]); $item = $stmt->fetch();
    if($item){
        if($item['uid'] == $uid) $msg = '不能购买自己的物品。';
        elseif($urow['points'] < $item['price']) $msg = '你的积分不足！';
        else {
            $pdo->beginTransaction();
            try {
                add_points($pdo, $uid, -$item['price'], 'buy_item', $item_id);
                add_points($pdo, $item['uid'], $item['price'], 'sell_item', $item_id);
                $pdo->prepare('UPDATE market_items SET status = 0 WHERE id = ?')->execute([$item_id]);
                $pdo->prepare('INSERT INTO market_logs (item_id, buyer_uid, seller_uid, price) VALUES (?,?,?,?)')->execute([$item_id, $uid, $item['uid'], $item['price']]);
                $pdo->commit();
                $msg = '购买成功！已通知卖家。';
            } catch(Exception $e){ $pdo->rollBack(); $msg = '购买失败，请重试。'; }
        }
    }
}
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])){
    $title = trim($_POST['title']); $price = (int)$_POST['price']; $desc = trim($_POST['desc_text']);
    if($title && $price > 0){
        $pdo->prepare('INSERT INTO market_items (uid, title, desc_text, price) VALUES (?,?,?,?)')->execute([$uid, $title, $desc, $price]);
        header('Location: market.php'); exit;
    }
}
if(isset($_GET['toggle']) && (int)$_GET['toggle'] > 0){
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare('SELECT uid, status FROM market_items WHERE id = ?'); $stmt->execute([$id]); $item = $stmt->fetch();
    if($item && ($item['uid'] == $uid || $is_admin)){
        $new = $item['status'] ? 0 : 1;
        $pdo->prepare('UPDATE market_items SET status = ? WHERE id = ?')->execute([$new, $id]);
    }
    header('Location: market.php'); exit;
}
if(isset($_GET['del']) && $is_admin){
    $pdo->prepare('DELETE FROM market_items WHERE id = ?')->execute([(int)$_GET['del']]);
    header('Location: market.php'); exit;
}
page_head('积分集市'); ?>
<?php if($msg): ?><p class="ok"><?=h($msg)?></p><?php endif; ?>
<div class="card">
    <h3>发布闲置/悬赏 (当前积分: <?=(int)$urow['points']?>)</h3>
    <form method="post">
        <input type="text" name="title" placeholder="物品名称/悬赏内容" required>
        <input type="number" name="price" placeholder="价格(积分)" min="1" required>
        <textarea name="desc_text" placeholder="详细描述..."></textarea>
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
        echo '<div class="md">'.h($m['desc_text']).'</div><div style="margin-top:8px">';
        if($m['status'] && $m['uid'] != $uid) echo '<a href="?buy='.$m['id'].'" class="fl green" onclick="return confirm(\'确定花费 '.$m['price'].' 积分购买？\')">购买</a> ';
        if($m['uid'] == $uid || $is_admin) echo '<a href="?toggle='.$m['id'].'" class="fl gray">'.($m['status']?'下架':'上架').'</a> ';
        echo '</div></div>';
    }
    ?>
</div>
<?php page_end(); ?>
