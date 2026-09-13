<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle='Home';
$menu=$pdo->query("SELECT * FROM menu_items WHERE available=1 ORDER BY sort_order")->fetchAll();
$featured=array_values(array_filter($menu,fn($x)=>(int)$x['featured']===1));
$featuredCovers=array_values(array_filter($menu,fn($x)=>!empty($x['cover_url'])));
$favIds=favorite_ids((int)(current_user()['id']??0));
$hours=store_hours();
$favCount=count($favIds);
include __DIR__.'/includes/header.php';
?>
<main class="flex min-h-[calc(100vh-56px)] flex-col">
<div class="relative h-56 overflow-hidden border-b-4 border-[#2b2118] sm:h-72 lg:h-96" role="img" aria-label="Sizzling tapsilog straight from the kitchen">
<img src="assets/uploads/silogbg.png" alt="" class="absolute inset-0 h-full w-full object-cover">
<div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/35 to-transparent"></div>
<div class="absolute bottom-0 left-0 right-0 mx-auto max-w-6xl px-4 pb-6 sm:px-6">
<p class="font-mono text-[11px] font-semibold uppercase tracking-[.2em] text-[#f2b01e]">Fresh off the flat-top</p>
<h2 class="mt-2 max-w-xl font-display text-3xl font-extrabold uppercase leading-[.95] text-white sm:text-5xl">Sizzling tapsilog, while it steams</h2>
<p class="mt-3 max-w-md text-sm leading-6 text-white/85">Garlic rice, runny egg, tapa glazed on the grill — photographed or not, it tastes the same.</p>
</div>
</div>
<section class="border-b-4 border-[#2b2118]">
<div class="mx-auto grid w-full max-w-6xl gap-10 px-4 pb-16 pt-14 sm:px-6 lg:grid-cols-[1.15fr_.85fr] lg:pb-24 lg:pt-20">
<div>
<p class="inline-flex items-center gap-2 border border-[#2b2118] px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[.18em]"><span class="h-2 w-2 bg-[#d9281c]"></span>Tapsilog house — delivery</p>
<h1 class="mt-6 font-display text-6xl font-extrabold uppercase leading-[.92] tracking-tight sm:text-7xl lg:text-8xl">Silog,<span class="block text-[#d9281c]">served hot.</span></h1>
<p class="mt-6 max-w-md text-base leading-7 text-[#7a6a58]">Garlic fried rice, runny eggs, and tapa off the flat-top — ordered in one sheet and delivered while it still steams.</p>
<div class="mt-8 flex flex-wrap items-center gap-3">
<?php if($favCount): ?><a href="order.php?addfavs=1" class="flex h-12 items-center gap-2 bg-[#d9281c] px-6 font-display text-sm font-bold uppercase tracking-[.12em] text-white hover:bg-[#b82016]">Re-order usuals ❤</a><?php endif; ?>
<a href="order.php" class="flex h-12 items-center gap-2 bg-[#2b2118] px-6 font-display text-sm font-bold uppercase tracking-[.12em] text-white hover:bg-[#d9281c]">Order now →</a>
<a href="orders.php" class="flex h-12 items-center border border-[#2b2118] px-6 font-display text-sm font-bold uppercase tracking-[.12em] hover:bg-[#2b2118] hover:text-white">Track an order</a>
</div>
<div class="mt-10 grid max-w-md grid-cols-3 gap-px border border-[#e8ddc9] bg-[#e8ddc9]">
<div class="bg-[#fdf8ef] px-3 py-3"><p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#7a6a58]">Open</p><p class="mt-1 flex items-center gap-1.5 font-mono text-[13px] font-semibold"><span class="inline-block h-2 w-2 rounded-full <?= $hours['open_now'] ? 'bg-[#7da33c]' : 'bg-[#d9281c]' ?>"></span><?= e($hours['open']) ?>–<?= e($hours['close']) ?><?= $hours['open_now'] ? '' : ' · closed' ?></p></div>
<div class="bg-[#fdf8ef] px-3 py-3"><p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#7a6a58]">Delivery</p><p class="mt-1 font-mono text-[13px] font-semibold">₱45.00 flat</p></div>
<div class="bg-[#fdf8ef] px-3 py-3"><p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#7a6a58]">Payment</p><p class="mt-1 font-mono text-[13px] font-semibold">Cash · GCash</p></div>
</div>
</div>
<div class="hidden lg:block"><div class="flex h-full min-h-[420px] flex-col justify-between border border-[#2b2118] bg-white p-6">
<div class="flex items-start justify-between"><span class="block h-24 w-24 overflow-hidden border border-[#2b2118]"><img src="assets/uploads/silogbg.png" alt="Sizzling tapsilog" class="h-full w-full object-cover"></span><span class="font-mono text-[11px] uppercase tracking-[.14em] text-[#7a6a58]">Est. 2026</span></div>
<div><p class="font-display text-[64px] font-extrabold leading-[.85] tracking-tight text-[#e8871e]">SILOG</p><p class="mt-2 text-sm font-semibold uppercase tracking-[.2em] text-[#7a6a58]">Tapa · Sinangag · Itlog</p></div>
<div class="space-y-2 border-t border-[#2b2118] pt-4"><?php foreach(array_slice($featured,0,3) as $item): ?><div class="flex items-center justify-between gap-3 text-sm"><span class="flex min-w-0 items-center gap-2.5"><span class="h-9 w-9 shrink-0 overflow-hidden border border-[#e8ddc9]"><img src="<?=e(menu_image_url($item['image_url'] ?? null))?>" alt="" class="h-full w-full object-cover"></span><span class="truncate font-semibold uppercase tracking-tight"><?=e($item['name'])?></span></span><span class="shrink-0 font-mono text-[#7a6a58]">₱<?=number_format((float)$item['price'],2)?></span></div><?php endforeach; ?></div>
</div></div>
<div class="mt-4"><a href="vendor_login.php" class="text-[10px] font-bold uppercase tracking-[.14em] text-[#7a6a58] hover:text-[#d9281c]">Restaurant staff? Open vendor desk →</a></div>
</div>
</section>
<?php if($featuredCovers): ?>
<section class="border-b-4 border-[#2b2118]"><div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6">
<div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-[11px] font-semibold uppercase tracking-[.2em] text-[#d9281c]">Specials</p><h2 class="mt-2 font-display text-3xl font-extrabold uppercase tracking-tight sm:text-4xl">Straight off the flat-top</h2></div><a href="order.php" class="border border-[#2b2118] px-4 py-2 font-display text-[11px] font-bold uppercase tracking-[.12em] hover:bg-[#2b2118] hover:text-white">Order now →</a></div>
<div class="mt-8 grid gap-px border border-[#2b2118] bg-[#2b2118] sm:grid-cols-2">
<?php foreach($featuredCovers as $cov): ?>
<div class="relative h-64 overflow-hidden bg-[#2b2118] sm:h-72"><img src="<?=e($cov['cover_url'])?>" alt="<?=e($cov['name'])?>" class="absolute inset-0 h-full w-full object-cover"><div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"></div><div class="absolute bottom-0 left-0 right-0 flex items-end justify-between gap-3 p-5"><div><p class="text-[10px] font-bold uppercase tracking-[.18em] text-white/70"><?=$cov['featured']?'House special':e($cov['category'])?></p><p class="mt-1 font-display text-3xl font-extrabold uppercase leading-none text-white"><?=e($cov['name'])?></p><p class="mt-2 max-w-sm text-[13px] leading-snug text-white/85"><?=e($cov['description'])?></p></div><div class="flex shrink-0 flex-col items-end gap-2"><span class="bg-[#d9281c] px-3 py-1.5 font-mono text-base font-bold text-white">₱<?=number_format((float)$cov['price'],2)?></span><a href="order.php?add=<?=$cov['id']?>" class="bg-white px-3 py-1.5 font-display text-[10px] font-bold uppercase tracking-[.12em] text-[#2b2118] hover:bg-[#d9281c] hover:text-white">Add →</a></div></div></div>
<?php endforeach; ?></div>
</div></section>
<?php endif; ?>
<section class="border-b-4 border-[#2b2118]"><div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6">
<div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-[11px] font-semibold uppercase tracking-[.2em] text-[#d9281c]">The board</p><h2 class="mt-2 font-display text-3xl font-extrabold uppercase tracking-tight sm:text-4xl">Today's menu</h2></div><a href="order.php" class="border border-[#2b2118] px-4 py-2 font-display text-[11px] font-bold uppercase tracking-[.12em] hover:bg-[#2b2118] hover:text-white">Full order sheet →</a></div>
<div class="mt-8 grid gap-px border border-[#e8ddc9] bg-[#e8ddc9] sm:grid-cols-2">
<?php foreach($menu as $item): if($item['category']!=='Silog Meals') continue; ?>
<div class="flex gap-4 bg-[#fdf8ef] p-5"><span class="h-20 w-20 shrink-0 overflow-hidden border border-[#e8ddc9] bg-[#f6efe0]"><img src="<?=e(menu_image_url($item['image_url'] ?? null))?>" alt="<?=e($item['name'])?>" class="h-full w-full object-cover" loading="lazy"></span><div class="min-w-0"><div class="flex items-baseline justify-between gap-3"><h3 class="font-display text-base font-bold uppercase tracking-tight"><?=e($item['name'])?></h3><span class="shrink-0 font-mono text-sm font-semibold">₱<?=number_format((float)$item['price'],2)?></span></div><p class="mt-1.5 text-[13px] leading-snug text-[#7a6a58]"><?=e($item['description'])?></p><span class="mt-3 inline-flex items-center gap-1.5"><a href="order.php?add=<?=$item['id']?>" class="lift border border-[#2b2118] bg-white px-3 py-1.5 font-display text-[10px] font-bold uppercase tracking-[.12em] hover:border-[#d9281c] hover:bg-[#d9281c] hover:text-white">Add to order +</a>
<form action="actions/favorite.php" method="post" class="inline"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="menu_item_id" value="<?=$item['id']?>"><input type="hidden" name="returnTo" value="index.php"><button type="submit" class="lift h-[30px] w-[30px] border <?= in_array((int)$item['id'], $favIds, true) ? 'border-[#d9281c] bg-[#fdeeec] text-[#d9281c]' : 'border-[#e8ddc9] bg-white text-[#b3a48e] hover:text-[#d9281c]' ?> text-sm leading-none" aria-label="<?= in_array((int)$item['id'], $favIds, true) ? 'Remove from favorites' : 'Add to favorites' ?>"><?= in_array((int)$item['id'], $favIds, true) ? '♥' : '♡' ?></button></form></span></div></div>
<?php endforeach; ?></div>
</div></section>
<section class="border-b-4 border-[#2b2118]"><div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6">
<p class="text-[11px] font-semibold uppercase tracking-[.2em] text-[#d9281c]">How it works</p><h2 class="mt-2 font-display text-3xl font-extrabold uppercase tracking-tight sm:text-4xl">Three steps to silog</h2>
<div class="mt-8 grid gap-px border border-[#e8ddc9] bg-[#e8ddc9] md:grid-cols-3">
<?php $steps=[['01','Pick from the board','Tap any silog on the order sheet and set your quantity. Prices are final — no surprises at the door.'],['02','Drop your address','One quick delivery form. Flat ₱45.00 anywhere inside the delivery zone, cash or GCash on delivery.'],['03','Track to the door','Watch your order move from placed to confirmed, prepping, on the way, and done.']]; foreach($steps as $s): ?>
<div class="bg-[#fdf8ef] p-6"><span class="font-mono text-5xl font-semibold leading-none text-[#e8871e]"><?=$s[0]?></span><h3 class="mt-4 font-display text-lg font-bold uppercase tracking-tight"><?=e($s[1])?></h3><p class="mt-2 text-sm leading-6 text-[#7a6a58]"><?=e($s[2])?></p></div>
<?php endforeach; ?></div></div></section>
</main>
<?php include __DIR__.'/includes/footer.php'; ?>
