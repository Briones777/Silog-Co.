<?php require_once __DIR__.'/includes/auth.php'; require_vendor();
$tab=$_GET['tab']??'dashboard';if(!in_array($tab,['dashboard','orders','history','menu'],true))$tab='dashboard';
$stats=$pdo->query("SELECT COUNT(*) total, SUM(status='pending') pending, SUM(status IN ('confirmed','preparing')) active, SUM(status='delivered') delivered, SUM(payment_method='gcash' AND payment_verified_at IS NULL AND status NOT IN ('delivered','cancelled')) gcash_waiting FROM orders")->fetch();
$menu=$pdo->query("SELECT * FROM menu_items ORDER BY category,sort_order,id")->fetchAll();
$cats=$pdo->query("SELECT DISTINCT category FROM menu_items ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$editId=(int)($_GET['edit']??0);
$editItem=null;
if($editId>0){$s=$pdo->prepare("SELECT * FROM menu_items WHERE id=? LIMIT 1");$s->execute([$editId]);$editItem=$s->fetch()?:null;if(!$editItem){$editId=0;}}

/*
|--------------------------------------------------------------------------
| Paged order lists: queue = live orders, history = delivered/cancelled
|--------------------------------------------------------------------------
*/
const ORDERS_PER_PAGE = 15;
$pageNo = request_page();

/* Optional payment filter on the status board. */
$payFilter = $_GET['pay'] ?? '';
if (!in_array($payFilter, ['', 'gcash', 'cash', 'gcash_unverified'], true)) { $payFilter = ''; }

$queue = fetch_orders_page(0, $pageNo, ORDERS_PER_PAGE, false, $payFilter !== '' ? $payFilter : null);
$history = fetch_orders_page(0, $pageNo, ORDERS_PER_PAGE, true);
$gcashSettings = gcash_settings();
$queueTotalAll = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('delivered','cancelled')")->fetchColumn();
$gcashWaiting = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_method='gcash' AND payment_verified_at IS NULL AND status NOT IN ('delivered','cancelled')")->fetchColumn();

/* Items (with photos) for whichever page of orders we display */
$pageOrderIds = array_map(fn($o)=>(int)$o['id'], array_merge($queue['orders'], $history['orders']));
$pageItems = [];
if ($pageOrderIds) {
    $ph = implode(',', array_fill(0, count($pageOrderIds), '?'));
    $st = $pdo->prepare("SELECT oi.*, m.image_url FROM order_items oi LEFT JOIN menu_items m ON m.id=oi.menu_item_id WHERE oi.order_id IN ($ph) ORDER BY oi.id");
    $st->execute($pageOrderIds);
    foreach ($st->fetchAll() as $it) { $pageItems[(int)$it['order_id']][] = $it; }
}

/*
|--------------------------------------------------------------------------
| Reports (history tab): daily / monthly operations, financial audit, sales
|--------------------------------------------------------------------------
*/
$report = $_GET['r'] ?? 'orders';
if (!in_array($report, ['orders','daily','monthly','audit','sales'], true)) { $report = 'orders'; }

$finishedWhere = "status IN ('delivered','cancelled')";

$dailyRows = $monthlyRows = $audit = $hourly = $statusShare = $topItems = [];
if ($tab === 'history' && in_array($report, ['daily','monthly','audit','sales'], true)) {

    if ($report === 'daily' || $report === 'sales') {
        $dailyRows = $pdo->query("
            SELECT DATE(created_at) d,
                   COUNT(*) orders,
                   SUM(total) gross,
                   SUM(status='delivered') delivered,
                   SUM(status='cancelled') cancelled,
                   SUM(status='delivered')*45.00 delivery_fees,
                   SUM(CASE WHEN status='delivered' THEN total ELSE 0 END) net_sales
            FROM orders WHERE $finishedWhere
            GROUP BY DATE(created_at) ORDER BY d DESC LIMIT 31
        ")->fetchAll();
    }

    if ($report === 'monthly' || $report === 'sales') {
        $monthlyRows = $pdo->query("
            SELECT DATE_FORMAT(created_at,'%Y-%m') m,
                   COUNT(*) orders,
                   SUM(total) gross,
                   SUM(status='delivered') delivered,
                   SUM(status='cancelled') cancelled,
                   SUM(status='delivered')*45.00 delivery_fees,
                   SUM(CASE WHEN status='delivered' THEN total ELSE 0 END) net_sales,
                   SUM(CASE WHEN status='delivered' THEN total ELSE 0 END)/NULLIF(SUM(status='delivered'),0) avg_ticket
            FROM orders WHERE $finishedWhere
            GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY m DESC LIMIT 24
        ")->fetchAll();
    }

    if ($report === 'audit') {
        $audit = $pdo->query("
            SELECT COUNT(*) finished_orders,
                   SUM(status='delivered') delivered,
                   SUM(status='cancelled') cancelled,
                   COALESCE(SUM(CASE WHEN status='delivered' THEN total ELSE 0 END),0) collected,
                   COALESCE(SUM(CASE WHEN status='delivered' THEN delivery_fee ELSE 0 END),0) delivery_fees,
                   COALESCE(SUM(CASE WHEN status='delivered' AND payment_method='gcash' THEN total ELSE 0 END),0) gcash_sales,
                   COALESCE(SUM(CASE WHEN status='delivered' AND payment_method='cash' THEN total ELSE 0 END),0) cash_sales,
                   COALESCE(SUM(CASE WHEN status='delivered' THEN total - delivery_fee ELSE 0 END),0) food_sales,
                   COALESCE(SUM(CASE WHEN status='cancelled' THEN total ELSE 0 END),0) cancelled_value,
                   COALESCE(AVG(CASE WHEN status='delivered' THEN total END),0) avg_ticket
            FROM orders WHERE $finishedWhere
        ")->fetch();
    }

    if ($report === 'sales') {
        $hourly = $pdo->query("
            SELECT HOUR(created_at) h, COUNT(*) orders, COALESCE(SUM(total),0) gross
            FROM orders WHERE $finishedWhere
            GROUP BY HOUR(created_at) ORDER BY h
        ")->fetchAll();

        $statusShare = $pdo->query("
            SELECT status, COUNT(*) c FROM orders WHERE $finishedWhere GROUP BY status
        ")->fetchAll();

        $topItems = $pdo->query("
            SELECT oi.item_name, SUM(oi.qty) qty, SUM(oi.qty*oi.unit_price) revenue
            FROM order_items oi JOIN orders o ON o.id=oi.order_id
            WHERE o.status='delivered'
            GROUP BY oi.item_name ORDER BY revenue DESC LIMIT 8
        ")->fetchAll();
    }
}

$pageTitle = 'Vendor — ' . ucfirst($tab);
$page = 'vendor.php';
include __DIR__.'/includes/header.php'; ?>
<main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
<div class="relative h-44 overflow-hidden border border-[#2b2118] sm:h-56" role="img" aria-label="Sizzling tapsilog from the kitchen">
<img src="assets/uploads/silogbg.png" alt="" class="absolute inset-0 h-full w-full object-cover">
<div class="absolute inset-0 bg-gradient-to-r from-black/75 via-black/45 to-black/15"></div>
<div class="absolute bottom-0 left-0 right-0 flex flex-wrap items-end justify-between gap-4 p-5 sm:p-7">
<div>
<p class="text-[11px] font-semibold uppercase tracking-[.2em] text-[#f2b01e]">Vendor Frontend</p>
<h1 class="mt-2 font-display text-3xl font-extrabold uppercase leading-[.95] text-white sm:text-5xl">Order status dashboard<span class="block text-white/75">Cook, deliver, get paid</span></h1>
</div>
<a href="vendor.php?tab=orders" class="border border-white/70 bg-black/30 px-4 py-2 font-display text-[11px] font-bold uppercase tracking-[.12em] text-white hover:bg-[#d9281c] hover:border-[#d9281c]">Open status board →</a>
</div>
</div>

<!-- Store status: hours + order taking -->
<?php $hours = store_hours(); ?>
<div class="mt-4 border p-4 <?= $hours['open_now'] ? 'border-[#c6d8a8] bg-[#f2f6e9]' : 'border-[#f2cf9e] bg-[#fdf3e3]' ?>">
<div class="flex flex-wrap items-center justify-between gap-3">
<div>
<p class="text-[10px] font-bold uppercase tracking-[.15em] <?= $hours['open_now'] ? 'text-[#4a5a1e]' : 'text-[#8a5a08]' ?>">Store status</p>
<p class="mt-1 text-sm <?= $hours['open_now'] ? 'text-[#4a5a1e]' : 'text-[#8a5a08]' ?>"><b><?= $hours['paused'] ? 'Paused — not taking orders' : ($hours['open_now'] ? 'OPEN — accepting orders' : 'CLOSED — outside store hours') ?></b> · <?= e($hours['open']) ?>–<?= e($hours['close']) ?></p>
</div>
<button type="button" onclick="document.getElementById('storeHours').classList.toggle('hidden')" class="border px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.12em] <?= $hours['open_now'] ? 'border-[#4a5a1e] text-[#4a5a1e] hover:bg-[#4a5a1e] hover:text-white' : 'border-[#8a5a08] text-[#8a5a08] hover:bg-[#8a5a08] hover:text-white' ?>">Edit</button>
</div>
<div id="storeHours" class="mt-4 hidden border-t pt-4 <?= $hours['open_now'] ? 'border-[#c6d8a8]' : 'border-[#f2cf9e]' ?>">
<form method="post" action="actions/vendor_settings.php" class="grid gap-3 sm:grid-cols-4">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label class="block text-[10px] font-bold uppercase tracking-[.14em] text-[#2b2118]">Opens<input type="time" name="store_open" value="<?=e($hours['open'])?>" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm text-[#2b2118]"></label>
<label class="block text-[10px] font-bold uppercase tracking-[.14em] text-[#2b2118]">Closes<input type="time" name="store_close" value="<?=e($hours['close'])?>" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm text-[#2b2118]"></label>
<label class="flex items-center gap-2 text-sm text-[#2b2118] sm:pt-6"><input type="checkbox" name="orders_paused" value="1" <?=$hours['paused'] ? 'checked' : ''?> class="accent-[#d9281c]"> Pause orders</label>
<button type="submit" class="bg-[#2b2118] px-4 py-2.5 font-display text-[10px] font-bold uppercase tracking-[.12em] text-white sm:self-end">Save store status</button>
</form>
</div>
</div>

<!-- GCash receiving settings -->
<div class="mt-4 border border-[#b2e2d2] bg-[#e6f4ef] p-4">
<div class="flex flex-wrap items-center justify-between gap-3">
<div>
<p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#00593d]">GCash receiving details</p>
<p class="mt-1 text-sm text-[#00593d]"><b><?= e($gcashSettings['number']) ?></b> · <?= e($gcashSettings['name']) ?><?= $gcashSettings['qr'] ? ' · QR uploaded ✓' : ' · no QR yet' ?></p>
</div>
<button type="button" onclick="document.getElementById('gcashSettings').classList.toggle('hidden')" class="border border-[#00593d] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.12em] text-[#00593d] hover:bg-[#00593d] hover:text-white">Edit</button>
</div>
<div id="gcashSettings" class="hidden mt-4 border-t border-[#b2e2d2] pt-4">
<form method="post" action="actions/vendor_settings.php" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-3">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label class="block text-[10px] font-bold uppercase tracking-[.14em] text-[#00593d]">GCash number<input name="gcash_number" value="<?=e($gcashSettings['number'])?>" class="mt-1 w-full border border-[#b2e2d2] bg-white px-3 py-2.5 text-sm text-[#2b2118]"></label>
<label class="block text-[10px] font-bold uppercase tracking-[.14em] text-[#00593d]">Account name<input name="gcash_name" value="<?=e($gcashSettings['name'])?>" class="mt-1 w-full border border-[#b2e2d2] bg-white px-3 py-2.5 text-sm text-[#2b2118]"></label>
<label class="block text-[10px] font-bold uppercase tracking-[.14em] text-[#00593d]">QR image (optional)<input type="file" name="gcash_qr" accept="image/*" class="mt-1 w-full text-sm text-[#2b2118]"></label>
<button type="submit" class="bg-[#00593d] px-4 py-2.5 font-display text-[10px] font-bold uppercase tracking-[.12em] text-white sm:self-end">Save settings</button>
</form>
<p class="mt-2 text-[11px] text-[#00593d]">These details appear on the customer checkout when GCash is selected.</p>
</div>
</div>
<div class="mt-6 grid gap-px border border-[#e8ddc9] bg-[#e8ddc9] sm:grid-cols-2 lg:grid-cols-4"><a href="vendor.php?tab=orders" class="bg-white p-4 hover:bg-[#f6efe0]"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#7a6a58]">New (pending)</p><p class="mt-1 font-mono text-2xl font-bold"><?=number_format((int)($stats['pending']??0))?></p></a><a href="vendor.php?tab=orders" class="bg-white p-4 hover:bg-[#f6efe0]"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#7a6a58]">Cooking / on the way</p><p class="mt-1 font-mono text-2xl font-bold"><?=number_format((int)($stats['active']??0))?></p></a><a href="vendor.php?tab=orders" class="bg-white p-4 hover:bg-[#f6efe0]"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#7a6a58]">Awaiting payment check</p><p class="mt-1 font-mono text-2xl font-bold"><?=number_format((int)($stats['gcash_waiting']??0))?></p></a><a href="vendor.php?tab=history" class="bg-white p-4 hover:bg-[#f6efe0]"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#7a6a58]">Delivered</p><p class="mt-1 font-mono text-2xl font-bold"><?=number_format((int)($stats['delivered']??0))?></p></a></div>
<?php $paycheck=$pdo->query("SELECT o.id, o.total, o.payment_ref, o.customer_name FROM orders o WHERE o.payment_method='gcash' AND o.payment_verified_at IS NULL AND o.status NOT IN ('delivered','cancelled') ORDER BY o.id LIMIT 4")->fetchAll(); if($paycheck): ?><div class="mt-4 border border-[#f2ddb5] bg-[#fdeecd] p-4"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#8a5a08]">GCash payments to check</p><ul class="mt-2 space-y-1.5"><?php foreach($paycheck as $p):?><li class="flex flex-wrap items-center justify-between gap-2 text-sm"><span><span class="font-mono text-xs font-bold">#<?=str_pad((string)$p['id'],5,'0',STR_PAD_LEFT)?></span> <?=e($p['customer_name'])?> — <b>₱<?=number_format((float)$p['total'],2)?></b></span><span class="flex items-center gap-2"><code class="bg-white px-2 py-1 font-mono text-xs"><?=e((string)$p['payment_ref'])?></code><form method="post" action="actions/vendor_payment.php" class="flex gap-1.5"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=$p['id']?>"><button name="action" value="verify" class="bg-[#00593d] px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-[.1em] text-white">✓ Verify</button></form></span></li><?php endforeach;?></ul></div><?php endif; ?>
<div class="mt-6 border border-[#2b2118] bg-white"><div class="flex overflow-x-auto border-b border-[#2b2118]"><a href="vendor.php?tab=dashboard" class="shrink-0 px-4 py-3 text-[11px] font-bold uppercase tracking-[.14em] <?=($tab==='dashboard'?'bg-[#2b2118] text-white':'text-[#7a6a58] hover:text-[#2b2118]')?>">Dashboard</a><a href="vendor.php?tab=orders" class="shrink-0 px-4 py-3 text-[11px] font-bold uppercase tracking-[.14em] <?=($tab==='orders'?'bg-[#2b2118] text-white':'text-[#7a6a58] hover:text-[#2b2118]')?>">Queue (<?= (int)$queue['total'] ?>)</a><a href="vendor.php?tab=history" class="shrink-0 px-4 py-3 text-[11px] font-bold uppercase tracking-[.14em] <?=($tab==='history'?'bg-[#2b2118] text-white':'text-[#7a6a58] hover:text-[#2b2118]')?>">History (<?= (int)$history['total'] ?>)</a><a href="vendor.php?tab=menu" class="shrink-0 px-4 py-3 text-[11px] font-bold uppercase tracking-[.14em] <?=($tab==='menu'?'bg-[#2b2118] text-white':'text-[#7a6a58] hover:text-[#2b2118]')?>">Menu</a></div>

<?php /* ------------------------------------------------ DASHBOARD ---- */
if($tab==='dashboard'): ?><div class="p-5 sm:p-6"><div class="flex items-center justify-between"><div><p class="text-[10px] font-bold uppercase tracking-[.16em] text-[#7a6a58]">Latest tickets</p><h2 class="mt-1 font-display text-2xl font-extrabold uppercase">Order queue</h2></div><a href="vendor.php?tab=orders" class="border border-[#2b2118] px-3 py-2 font-display text-[10px] font-bold uppercase tracking-[.12em]">View all</a></div><div class="mt-5 space-y-2"><?php foreach(array_slice($queue['orders'],0,8) as $o):?><a href="vendor.php?tab=orders#order-<?=$o['id']?>" class="flex items-center justify-between gap-3 border border-[#e8ddc9] p-3 hover:border-[#2b2118]"><div><span class="font-mono text-xs font-bold">#<?=str_pad((string)$o['id'],5,'0',STR_PAD_LEFT)?></span><span class="ml-2 text-sm font-semibold"><?=e($o['customer_name'])?></span><p class="mt-1 text-[11px] text-[#7a6a58]"><?=date('M j, g:i A',strtotime($o['created_at']))?></p></div><span class="px-2 py-1 text-[10px] font-bold uppercase <?=order_status_class($o['status'])?>"><?=e(order_status_label($o['status']))?></span></a><?php endforeach; if(!$queue['orders']):?><p class="text-sm text-[#7a6a58]">No live orders right now.</p><?php endif;?></div></div>

<?php /* ------------------------------------------------ QUEUE ---- */
elseif($tab==='orders'): ?><div class="p-5 sm:p-6"><div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-[10px] font-bold uppercase tracking-[.16em] text-[#7a6a58]">Kitchen + delivery</p><h2 class="mt-1 font-display text-2xl font-extrabold uppercase">Order queue</h2><p class="mt-1 text-[11px] text-[#7a6a58]"><?= (int)$queue['total'] ?> live orders · page <?= $queue['page'] ?> of <?= $queue['pages'] ?> · <?= ORDERS_PER_PAGE ?> per page</p></div><button type="button" onclick="location.reload()" class="border border-[#2b2118] px-3 py-2 font-display text-[10px] font-bold uppercase tracking-[.12em]">Refresh ↻</button></div>
<div class="mt-5 flex flex-wrap gap-1.5">
<a href="vendor.php?tab=orders" class="border px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.12em] <?=($payFilter===''?'border-[#2b2118] bg-[#2b2118] text-white':'border-[#e8ddc9] text-[#7a6a58] hover:border-[#2b2118] hover:text-[#2b2118]')?>">All (<?= (int)$queueTotalAll ?>)</a>
<a href="vendor.php?tab=orders&amp;pay=gcash_unverified" class="border px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.12em] <?=($payFilter==='gcash_unverified'?'border-[#f2ddb5] bg-[#fdeecd] text-[#8a5a08]':'border-[#e8ddc9] text-[#7a6a58] hover:border-[#2b2118] hover:text-[#2b2118]')?>">GCash to verify (<?= (int)$gcashWaiting ?>)</a>
<a href="vendor.php?tab=orders&amp;pay=gcash" class="border px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.12em] <?=($payFilter==='gcash'?'border-[#b2e2d2] bg-[#e6f4ef] text-[#00593d]':'border-[#e8ddc9] text-[#7a6a58] hover:border-[#2b2118] hover:text-[#2b2118]')?>">All GCash</a>
<a href="vendor.php?tab=orders&amp;pay=cash" class="border px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.12em] <?=($payFilter==='cash'?'border-[#2b2118] bg-[#2b2118] text-white':'border-[#e8ddc9] text-[#7a6a58] hover:border-[#2b2118] hover:text-[#2b2118]')?>">Cash</a>
</div>
<div class="mt-5 space-y-4"><?php foreach($queue['orders'] as $o):?><article id="order-<?=$o['id']?>" class="border border-[#2b2118]"><div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#2b2118] bg-[#fdf8ef] px-4 py-3"><div><span class="font-mono text-xs font-bold">#<?=str_pad((string)$o['id'],5,'0',STR_PAD_LEFT)?></span><span class="ml-2 text-sm font-semibold"><?=e($o['customer_name'])?></span></div><span class="px-2 py-1 text-[10px] font-bold uppercase <?=order_status_class($o['status'])?>"><?=e(order_status_label($o['status']))?></span></div><div class="grid gap-5 p-4 md:grid-cols-[1fr_auto]"><div><ul class="space-y-1"><?php foreach($pageItems[(int)$o['id']] ?? [] as $it):?><li class="flex items-center gap-3"><span class="h-9 w-9 shrink-0 overflow-hidden border border-[#e8ddc9] bg-[#f6efe0]"><img src="<?=e(menu_image_url($it['image_url'] ?? null))?>" alt="" class="h-full w-full object-cover"></span><span class="flex-1 text-sm"><span class="font-mono text-[11px] text-[#7a6a58]"><?=$it['qty']?>×</span> <?=e($it['item_name'])?></span><span class="font-mono">₱<?=number_format((float)$it['unit_price']*$it['qty'],2)?></span></li><?php endforeach;?></ul><div class="mt-4 border-t border-[#e8ddc9] pt-3 text-[12px] leading-5"><p><b>Phone:</b> <?=e($o['phone'])?></p><p><b>Address:</b> <?=nl2br(e($o['address']))?></p><?php if($o['notes']):?><p class="mt-1 border-l-2 border-[#d9281c] pl-2"><b>Note:</b> <?=e($o['notes'])?></p><?php endif;?></div></div><div class="min-w-[230px] text-right"><p class="font-mono text-[11px] text-[#7a6a58]">Total</p><p class="font-display text-2xl font-extrabold">₱<?=number_format((float)$o['total'],2)?></p><div class="mt-2"><?= payment_status_badge($o) ?></div><?php if(strtolower((string)$o['payment_method'])==='gcash' && empty($o['payment_verified_at']) && !empty($o['payment_ref'])):?><code class="mt-2 block bg-[#f6efe0] px-2 py-1 font-mono text-xs">Ref: <?=e((string)$o['payment_ref'])?></code><form method="post" action="actions/vendor_payment.php" class="mt-2 flex gap-1.5"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=$o['id']?>"><button name="action" value="verify" class="flex-1 bg-[#00593d] px-2 py-2 text-[10px] font-bold uppercase tracking-[.1em] text-white">✓ Paid</button><button name="action" value="decline" onclick="return confirm('Clear this reference and ask the customer to resubmit?')" class="flex-1 border border-[#e8ddc9] px-2 py-2 text-[10px] font-bold uppercase tracking-[.1em] text-[#7a6a58]">✕ Ref</button></form><?php elseif(strtolower((string)$o['payment_method'])==='gcash' && empty($o['payment_verified_at'])):?><p class="mt-1 text-[10px] uppercase tracking-[.1em] text-[#8a5a08]">Awaiting customer's GCash ref</p><?php endif;?><form method="post" action="actions/vendor_order.php" class="mt-4 space-y-2"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=$o['id']?>"><button name="action" value="advance" type="submit" class="w-full bg-[#d9281c] px-3 py-2.5 font-display text-[10px] font-bold uppercase tracking-[.12em] text-white hover:bg-[#b82016]"><?php $next=['pending'=>'Confirm order','confirmed'=>'Start preparing','preparing'=>'Out for delivery','out_for_delivery'=>'Mark delivered'];echo $next[$o['status']]??'Advance';?> →</button><button name="action" value="cancel" type="submit" onclick="return confirm('Cancel this order?')" class="w-full border border-[#2b2118] px-3 py-2.5 font-display text-[10px] font-bold uppercase tracking-[.12em]">Cancel order</button></form></div></div></article><?php endforeach; if(!$queue['orders']):?><div class="border border-[#e8ddc9] bg-[#fdf8ef] p-8 text-center text-sm text-[#7a6a58]">The queue is empty. New orders land here automatically.</div><?php endif;?></div>
<?= render_pagination($queue['page'], $queue['pages'], array_merge(['tab'=>'orders'], $payFilter !== '' ? ['pay'=>$payFilter] : [])) ?>
</div>

<?php /* ------------------------------------------------ HISTORY + REPORTS ---- */
elseif($tab==='history'): ?><div class="p-5 sm:p-6">
<div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-[10px] font-bold uppercase tracking-[.16em] text-[#7a6a58]">Completed + cancelled</p><h2 class="mt-1 font-display text-2xl font-extrabold uppercase">Order history</h2><p class="mt-1 text-[11px] text-[#7a6a58]"><?= (int)$history['total'] ?> finished orders · page <?= $history['page'] ?> of <?= $history['pages'] ?></p></div></div>
<div class="mt-4 flex flex-wrap gap-1.5 border-b border-[#e8ddc9] pb-3"><?php foreach(['orders'=>'Order history','daily'=>'Daily report','monthly'=>'Monthly report','audit'=>'Financial audit','sales'=>'Sales report'] as $rk=>$rl):?><a href="vendor.php?tab=history&amp;r=<?=$rk?>" class="border px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.12em] <?=($report===$rk?'border-[#2b2118] bg-[#2b2118] text-white':'border-[#e8ddc9] text-[#7a6a58] hover:border-[#2b2118] hover:text-[#2b2118])')?>"><?=$rl?></a><?php endforeach;?></div>

<?php if($report==='orders'): ?>
<div class="mt-5 space-y-3"><?php foreach($history['orders'] as $o):?><article class="border border-[#e8ddc9]"><div class="flex flex-wrap items-center justify-between gap-2 bg-[#fdf8ef] px-4 py-3"><div><span class="font-mono text-xs font-bold">#<?=str_pad((string)$o['id'],5,'0',STR_PAD_LEFT)?></span><span class="ml-2 text-sm font-semibold"><?=e($o['customer_name'])?></span><span class="ml-2 text-[11px] text-[#7a6a58]"><?=date('M j, Y g:i A',strtotime($o['created_at']))?></span></div><div class="flex flex-wrap items-center gap-2"><span class="px-2 py-1 text-[10px] font-bold uppercase <?=order_status_class($o['status'])?>"><?=e(order_status_label($o['status']))?></span><?= payment_status_badge($o) ?><span class="font-display font-extrabold">₱<?=number_format((float)$o['total'],2)?></span></div></div><details class="px-4 py-3"><summary class="cursor-pointer text-[11px] font-bold uppercase tracking-[.12em] text-[#7a6a58] hover:text-[#2b2118]">Items &amp; delivery details</summary><ul class="mt-3 space-y-1"><?php foreach($pageItems[(int)$o['id']] ?? [] as $it):?><li class="flex items-center gap-3 text-sm"><span class="h-8 w-8 shrink-0 overflow-hidden border border-[#e8ddc9]"><img src="<?=e(menu_image_url($it['image_url'] ?? null))?>" alt="" class="h-full w-full object-cover"></span><span class="flex-1"><span class="font-mono text-[11px] text-[#7a6a58]"><?=$it['qty']?>×</span> <?=e($it['item_name'])?></span><span class="font-mono">₱<?=number_format((float)$it['unit_price']*$it['qty'],2)?></span></li><?php endforeach;?></ul><div class="mt-3 border-t border-[#e8ddc9] pt-2 text-[12px] leading-5 text-[#7a6a58]"><p><b class="text-[#2b2118]">Phone:</b> <?=e($o['phone'])?></p><p><b class="text-[#2b2118]">Address:</b> <?=nl2br(e($o['address']))?></p><?php if($o['notes']):?><p><b class="text-[#2b2118]">Note:</b> <?=e($o['notes'])?></p><?php endif;?></div></details></article><?php endforeach; if(!$history['orders']):?><div class="border border-[#e8ddc9] bg-[#fdf8ef] p-8 text-center text-sm text-[#7a6a58]">No finished orders yet. Delivered and cancelled orders move here from the queue.</div><?php endif;?></div>
<?= render_pagination($history['page'], $history['pages'], ['tab'=>'history','r'=>'orders']) ?>

<?php elseif($report==='daily'): ?>
<div class="mt-5 overflow-x-auto"><table class="w-full min-w-[720px] border-collapse text-sm"><thead><tr class="border-b border-[#2b2118] text-left text-[10px] font-bold uppercase tracking-[.12em]"><th class="px-3 py-3">Date</th><th class="px-3 py-3">Orders</th><th class="px-3 py-3">Delivered</th><th class="px-3 py-3">Cancelled</th><th class="px-3 py-3">Gross</th><th class="px-3 py-3">Delivery fees</th><th class="px-3 py-3">Net sales</th></tr></thead><tbody><?php foreach($dailyRows as $d):?><tr class="border-b border-[#e8ddc9]"><td class="px-3 py-3 font-medium"><?=e(date('M j, Y', strtotime($d['d'])))?></td><td class="px-3 py-3 font-mono"><?= (int)$d['orders'] ?></td><td class="px-3 py-3 font-mono text-green-800"><?= (int)$d['delivered'] ?></td><td class="px-3 py-3 font-mono text-[#d9281c]"><?= (int)$d['cancelled'] ?></td><td class="px-3 py-3 font-mono">₱<?=number_format((float)$d['gross'],2)?></td><td class="px-3 py-3 font-mono">₱<?=number_format((float)$d['delivery_fees'],2)?></td><td class="px-3 py-3 font-mono font-bold">₱<?=number_format((float)$d['net_sales'],2)?></td></tr><?php endforeach; if(!$dailyRows):?><tr><td colspan="7" class="px-3 py-8 text-center text-sm text-[#7a6a58]">No finished orders in the last 31 days.</td></tr><?php endif;?></tbody></table></div>
<?php if($dailyRows): ?><div class="mt-6 border border-[#e8ddc9] bg-[#fdf8ef] p-4"><h3 class="text-[11px] font-bold uppercase tracking-[.12em] text-[#7a6a58]">Daily net sales — last 14 days</h3><div class="mt-3 h-64"><canvas id="chartDaily"></canvas></div></div><?php endif; ?>

<?php elseif($report==='monthly'): ?>
<div class="mt-5 overflow-x-auto"><table class="w-full min-w-[760px] border-collapse text-sm"><thead><tr class="border-b border-[#2b2118] text-left text-[10px] font-bold uppercase tracking-[.12em]"><th class="px-3 py-3">Month</th><th class="px-3 py-3">Orders</th><th class="px-3 py-3">Delivered</th><th class="px-3 py-3">Cancelled</th><th class="px-3 py-3">Gross</th><th class="px-3 py-3">Delivery fees</th><th class="px-3 py-3">Net sales</th><th class="px-3 py-3">Avg ticket</th></tr></thead><tbody><?php foreach($monthlyRows as $m):?><tr class="border-b border-[#e8ddc9]"><td class="px-3 py-3 font-medium"><?=e(date('F Y', strtotime($m['m'].'-01')))?></td><td class="px-3 py-3 font-mono"><?= (int)$m['orders'] ?></td><td class="px-3 py-3 font-mono text-green-800"><?= (int)$m['delivered'] ?></td><td class="px-3 py-3 font-mono text-[#d9281c]"><?= (int)$m['cancelled'] ?></td><td class="px-3 py-3 font-mono">₱<?=number_format((float)$m['gross'],2)?></td><td class="px-3 py-3 font-mono">₱<?=number_format((float)$m['delivery_fees'],2)?></td><td class="px-3 py-3 font-mono font-bold">₱<?=number_format((float)$m['net_sales'],2)?></td><td class="px-3 py-3 font-mono">₱<?=number_format((float)$m['avg_ticket'],2)?></td></tr><?php endforeach; if(!$monthlyRows):?><tr><td colspan="8" class="px-3 py-8 text-center text-sm text-[#7a6a58]">No finished orders on record yet.</td></tr><?php endif;?></tbody></table></div>
<?php if($monthlyRows): ?><div class="mt-6 border border-[#e8ddc9] bg-[#fdf8ef] p-4"><h3 class="text-[11px] font-bold uppercase tracking-[.12em] text-[#7a6a58]">Monthly net sales</h3><div class="mt-3 h-64"><canvas id="chartMonthly"></canvas></div></div><?php endif; ?>

<?php elseif($report==='audit'): $a=$audit?:[]; ?>
<div class="mt-5 grid gap-px border border-[#e8ddc9] bg-[#e8ddc9] sm:grid-cols-3">
<div class="bg-white p-5"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#7a6a58]">Cash collected (delivered)</p><p class="mt-1 font-display text-3xl font-extrabold text-[#2b2118]">₱<?=number_format((float)($a['collected']??0),2)?></p></div>
<div class="bg-white p-5"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#7a6a58]">Food sales (excl. fees)</p><p class="mt-1 font-display text-3xl font-extrabold text-[#2b2118]">₱<?=number_format((float)($a['food_sales']??0),2)?></p></div>
<div class="bg-white p-5"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#7a6a58]">Delivery fees earned</p><p class="mt-1 font-display text-3xl font-extrabold text-[#2b2118]">₱<?=number_format((float)($a['delivery_fees']??0),2)?></p></div>
<div class="bg-white p-5"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#7a6a58]">Finished orders</p><p class="mt-1 font-display text-3xl font-extrabold"><?= (int)($a['finished_orders']??0) ?></p></div>
<div class="bg-white p-5"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#7a6a58]">Cancelled value</p><p class="mt-1 font-display text-3xl font-extrabold text-[#d9281c]">₱<?=number_format((float)($a['cancelled_value']??0),2)?></p></div>
<div class="bg-white p-5"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#7a6a58]">Average ticket</p><p class="mt-1 font-display text-3xl font-extrabold">₱<?=number_format((float)($a['avg_ticket']??0),2)?></p></div>
</div>
<div class="mt-4 grid gap-px border border-[#e8ddc9] bg-[#e8ddc9] sm:grid-cols-2"><div class="bg-white p-4"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#7a6a58]">Collected via GCash</p><p class="mt-1 font-display text-2xl font-extrabold text-[#00593d]">₱<?=number_format((float)($a['gcash_sales']??0),2)?></p></div><div class="bg-white p-4"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#7a6a58]">Collected in cash</p><p class="mt-1 font-display text-2xl font-extrabold">₱<?=number_format((float)($a['cash_sales']??0),2)?></p></div></div>
<div class="mt-6 border border-[#e8ddc9] bg-[#fdf8ef] p-4"><h3 class="text-[11px] font-bold uppercase tracking-[.12em] text-[#7a6a58]">Financial audit breakdown</h3><div class="mt-3 h-64"><canvas id="chartAudit"></canvas></div></div>
<p class="mt-4 text-[11px] leading-5 text-[#7a6a58]">Audit basis: delivered orders count as collected revenue (cash/GCash on delivery). Cancelled orders are reported separately and never counted toward sales. Delivery fees are computed at the flat ₱45.00 rate stored on each delivered order.</p>

<?php else: /* sales */ ?>
<?php $peak = null; foreach($hourly as $h){ if($peak===null || (int)$h['orders']>(int)$peak['orders']) $peak=$h; } ?>
<div class="mt-5 grid gap-4 md:grid-cols-2">
<div class="border border-[#e8ddc9] bg-[#fdf8ef] p-4"><h3 class="text-[11px] font-bold uppercase tracking-[.12em] text-[#7a6a58]">Sales by hour of day</h3><div class="mt-3 h-60"><canvas id="chartHourly"></canvas></div><?php if($peak):?><p class="mt-2 text-[11px] text-[#7a6a58]">Peak hour: <b class="text-[#2b2118]"><?= (int)$peak['h'] ?>:00</b> — <?= (int)$peak['orders'] ?> finished orders.</p><?php endif;?></div>
<div class="border border-[#e8ddc9] bg-[#fdf8ef] p-4"><h3 class="text-[11px] font-bold uppercase tracking-[.12em] text-[#7a6a58]">Order outcome share</h3><div class="mt-3 h-60"><canvas id="chartShare"></canvas></div></div>
</div>
<div class="mt-4 border border-[#e8ddc9] bg-white p-4"><h3 class="text-[11px] font-bold uppercase tracking-[.12em] text-[#7a6a58]">Best sellers by revenue (delivered)</h3><table class="mt-3 w-full text-sm"><thead><tr class="border-b border-[#e8ddc9] text-left text-[10px] font-bold uppercase tracking-[.12em]"><th class="py-2">Item</th><th class="py-2">Qty sold</th><th class="py-2">Revenue</th></tr></thead><tbody><?php foreach($topItems as $t):?><tr class="border-b border-[#f6efe0]"><td class="py-2 font-medium"><?=e($t['item_name'])?></td><td class="py-2 font-mono"><?= (int)$t['qty'] ?></td><td class="py-2 font-mono font-bold">₱<?=number_format((float)$t['revenue'],2)?></td></tr><?php endforeach; if(!$topItems):?><tr><td colspan="3" class="py-6 text-center text-sm text-[#7a6a58]">No delivered orders yet.</td></tr><?php endif;?></tbody></table></div>
<?php endif; ?>
</div>

<?php /* ------------------------------------------------ MENU ---- */
else: ?><div class="p-5 sm:p-6">
<div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-[10px] font-bold uppercase tracking-[.16em] text-[#7a6a58]">Catalog control</p><h2 class="mt-1 font-display text-2xl font-extrabold uppercase">Menu</h2></div><div class="flex items-center gap-2"><span class="font-mono text-[11px] text-[#7a6a58]"><?=count($menu)?> items</span><a href="vendor.php?tab=menu&amp;add=1" class="bg-[#d9281c] px-4 py-2 font-display text-[10px] font-bold uppercase tracking-[.12em] text-white hover:bg-[#b82016]">+ Add menu</a></div></div>
<div id="menuItemForm" class="<?=($editItem||($_GET['add']??'')==='1'?'':'hidden')?> mt-5 border border-[#2b2118] bg-[#fdf8ef] p-5">
<div class="flex items-center justify-between"><h3 class="font-display text-sm font-bold uppercase tracking-[.12em]"><?=$editItem?'Edit item':'New menu item'?></h3><a href="vendor.php?tab=menu" class="text-[10px] font-bold uppercase tracking-[.12em] text-[#7a6a58] hover:text-[#d9281c]">Close ×</a></div>
<form method="post" action="actions/vendor_menu_item.php" enctype="multipart/form-data" class="mt-4 grid gap-4 md:grid-cols-[1fr_180px]">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="<?=$editItem?'update':'add'?>"><?php if($editItem):?><input type="hidden" name="id" value="<?=$editItem['id']?>"><?php endif;?>
<div class="space-y-3">
<label class="block text-[10px] font-bold uppercase tracking-[.15em]">Item name<input name="name" required value="<?=e($editItem['name']??'')?>" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]"></label>
<label class="block text-[10px] font-bold uppercase tracking-[.15em]">Description<textarea name="description" required rows="2" class="mt-1 w-full resize-none border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]"><?=e($editItem['description']??'')?></textarea></label>
<div class="grid grid-cols-2 gap-3">
<label class="block text-[10px] font-bold uppercase tracking-[.15em]">Category<input name="category" required list="categoryList" value="<?=e($editItem['category']??'')?>" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]"><datalist id="categoryList"><?php foreach($cats as $c):?><option value="<?=e($c)?>"><?php endforeach;?></datalist></label>
<label class="block text-[10px] font-bold uppercase tracking-[.15em]">Price (₱)<input name="price" required type="number" step="0.01" min="0.01" value="<?=e($editItem?(string)$editItem['price']:'')?>" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]"></label>
</div>
</div>
<div class="space-y-2">
<p class="text-[10px] font-bold uppercase tracking-[.15em]">Photo</p>
<label class="block cursor-pointer border border-[#e8ddc9] bg-white"><img id="itemImgPreview" src="<?=e(menu_image_url($editItem['image_url'] ?? null))?>" alt="" class="h-32 w-full object-cover"><span class="block border-t border-[#e8ddc9] px-2 py-2 text-center text-[10px] font-bold uppercase tracking-[.12em] text-[#7a6a58]">Choose image</span><input type="file" name="image" accept="image/*" class="hidden" onchange="document.getElementById('itemImgPreview').src=window.URL.createObjectURL(this.files[0])"></label>
<p class="text-[10px] leading-4 text-[#7a6a58]">JPG/PNG/GIF/WebP up to 3&nbsp;MB. Shown beside the item on the customer menu.</p>
</div>
<div class="md:col-span-2 flex flex-wrap gap-2">
<button type="submit" class="bg-[#2b2118] px-5 py-2.5 font-display text-[10px] font-bold uppercase tracking-[.12em] text-white hover:bg-[#d9281c]"><?=$editItem?'Save changes':'Add item'?></button>
<a href="vendor.php?tab=menu" class="border border-[#e8ddc9] px-5 py-2.5 font-display text-[10px] font-bold uppercase tracking-[.12em] text-[#7a6a58] hover:border-[#2b2118] hover:text-[#2b2118]">Cancel</a>
</div>
</form>
</div>
<div class="mt-5 overflow-x-auto"><table class="w-full min-w-[760px] border-collapse text-sm"><thead><tr class="border-b border-[#2b2118] text-left text-[10px] font-bold uppercase tracking-[.12em]"><th class="px-3 py-3">Item</th><th class="px-3 py-3">Category</th><th class="px-3 py-3">Price</th><th class="px-3 py-3">Availability</th><th class="px-3 py-3">Action</th></tr></thead><tbody><?php foreach($menu as $m):?><tr class="border-b border-[#e8ddc9]">
<td class="px-3 py-3"><div class="flex items-center gap-3"><span class="h-12 w-12 shrink-0 overflow-hidden border border-[#e8ddc9] bg-[#f6efe0]"><img src="<?=e(menu_image_url($m['image_url'] ?? null))?>" alt="" class="h-full w-full object-cover"></span><div><b><?=e($m['name'])?></b><?php if(!empty($m['cover_url'])):?><span class="ml-2 border border-[#e8871e] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-[.1em] text-[#e8871e]">Cover</span><?php endif;?><div class="text-[11px] text-[#7a6a58]"><?=e($m['description'])?></div></div></div></td>
<td class="px-3 py-3"><?=e($m['category'])?></td>
<td class="px-3 py-3 font-mono">₱<?=number_format((float)$m['price'],2)?></td>
<td class="px-3 py-3"><span class="text-[10px] font-bold uppercase <?=((int)$m['available']?'text-green-800':'text-[#8a7a64]')?>" style="<?=((int)$m['available']?'':'')?>"><?=((int)$m['available']?'Available':'Hidden')?></span></td>
<td class="px-3 py-3"><div class="flex flex-wrap items-center gap-1.5">
<a href="vendor.php?tab=menu&amp;edit=<?=$m['id']?>" class="border border-[#2b2118] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.1em] hover:bg-[#2b2118] hover:text-white">Edit</a>
<form method="post" action="actions/vendor_menu_item.php" enctype="multipart/form-data" class="flex items-center gap-1.5"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="set_cover"><input type="hidden" name="id" value="<?=$m['id']?>"><label class="cursor-pointer border border-[#e8871e] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.1em] text-[#e8871e] hover:bg-[#e8871e] hover:text-white"><?=$m['cover_url']?'Cover ↻':'Cover +'?><input type="file" name="cover" accept="image/*" class="hidden" onchange="this.form.submit()"></label></form>
<form method="post" action="actions/vendor_menu_item.php"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$m['id']?>"><button name="action" value="toggle" type="submit" class="border border-[#2b2118] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.1em] hover:bg-[#2b2118] hover:text-white"><?=((int)$m['available']?'Hide':'Show')?></button></form>
<form method="post" action="actions/vendor_menu_item.php" onsubmit="return confirm('Delete this item permanently?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$m['id']?>"><button name="action" value="delete" type="submit" class="border border-[#e8ddc9] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.1em] text-[#d9281c] hover:border-[#d9281c]">Delete</button></form>
</div></td>
</tr><?php endforeach;?></tbody></table></div>
</div><?php endif; ?></div></main>

<?php if($tab==='history' && (($report==='daily'&&$dailyRows)||$report==='monthly'&&$monthlyRows||$report==='audit'||$report==='sales')): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const RED='#d9281c',GOLD='#f2b01e',ORANGE='#e8871e',BROWN='#2b2118',GRID='#e8ddc9';
  Chart.defaults.font.family='Inter,Arial,sans-serif';
  Chart.defaults.color='#7a6a58';
  const money=v=>'₱'+Number(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
  const bars=(ctx,labels,dataset)=>new Chart(ctx,{type:'bar',data:{labels,datasets:dataset},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:dataset.length>1},tooltip:{callbacks:{label:c=>c.dataset.label+': '+money(c.parsed.y)}}},scales:{x:{grid:{color:GRID}},y:{grid:{color:GRID},ticks:{callback:v=>money(v)}}}}});

  <?php if($report==='daily'&&$dailyRows): ?>
  const daily=<?=json_encode(array_reverse(array_slice($dailyRows,0,14)),JSON_UNESCAPED_UNICODE)?>;
  bars(document.getElementById('chartDaily'),daily.map(r=>r.d.slice(5)),[
    {label:'Net sales',data:daily.map(r=>+r.net_sales),backgroundColor:RED},
    {label:'Delivery fees',data:daily.map(r=>+r.delivery_fees),backgroundColor:GOLD}
  ]);
  <?php endif; ?>

  <?php if($report==='monthly'&&$monthlyRows): ?>
  const monthly=<?=json_encode(array_reverse($monthlyRows),JSON_UNESCAPED_UNICODE)?>;
  bars(document.getElementById('chartMonthly'),monthly.map(r=>r.m),[
    {label:'Net sales',data:monthly.map(r=>+r.net_sales),backgroundColor:RED},
    {label:'Delivery fees',data:monthly.map(r=>+r.delivery_fees),backgroundColor:GOLD}
  ]);
  <?php endif; ?>

  <?php if($report==='audit'): $a=$audit?:[]; ?>
  new Chart(document.getElementById('chartAudit'),{type:'bar',data:{
    labels:['Food sales','Delivery fees','Cancelled value'],
    datasets:[{data:[<?= (float)($a['food_sales']??0) ?>,<?= (float)($a['delivery_fees']??0) ?>,<?= (float)($a['cancelled_value']??0) ?>],backgroundColor:[RED,GOLD,'#b3a48e']}]
  },options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>money(c.parsed.y)}}},scales:{x:{grid:{color:GRID}},y:{grid:{color:GRID},ticks:{callback:v=>money(v)}}}}});
  <?php endif; ?>

  <?php if($report==='sales'): ?>
  const hourly=<?=json_encode($hourly,JSON_UNESCAPED_UNICODE)?>;
  const labels=hourly.map(r=>String(r.h).padStart(2,'0')+':00');
  bars(document.getElementById('chartHourly'),labels,[{label:'Gross sales',data:hourly.map(r=>+r.gross),backgroundColor:ORANGE}]);
  const share=<?=json_encode($statusShare,JSON_UNESCAPED_UNICODE)?>;
  new Chart(document.getElementById('chartShare'),{type:'doughnut',data:{
    labels:share.map(r=>r.status.replaceAll('_',' ')),
    datasets:[{data:share.map(r=>+r.c),backgroundColor:[RED,ORANGE,GOLD,BROWN,'#b3a48e']}]
  },options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
  <?php endif; ?>
})();
</script>
<?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
