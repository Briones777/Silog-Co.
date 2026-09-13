<?php require_once __DIR__ . '/includes/auth.php';
require_login();
$__u = current_user();
if ($__u && ($__u['role'] ?? 'customer') === 'vendor') { safe_redirect('/vendor.php'); }
$menu = $pdo->query("SELECT * FROM menu_items WHERE available=1 ORDER BY sort_order")->fetchAll();
$cats = array_values(array_unique(array_column($menu, 'category')));
$covers = array_values(array_filter($menu, fn($x) => !empty($x['cover_url'])));

/*
|----------------------------------------------------------------------
| Deep link: home page "Add" buttons land here with ?add=ID and the
| item is pre-added to the cart so checkout is one tap away.
|----------------------------------------------------------------------
*/
$preAdd = [];
if (isset($_GET['add'])) {
    $addId = (string)(int)$_GET['add'];
    foreach ($menu as $m) {
        if ((string)$m['id'] === $addId) {
            $preAdd = ['id' => $addId, 'name' => (string)$m['name'], 'price' => (float)$m['price']];
            break;
        }
    }
}

/*
|----------------------------------------------------------------------
| Favorites: hearts render when the user has them; ?addfavs=1 loads
| every favorited dish into the cart in one tap (re-order the usuals).
|----------------------------------------------------------------------
*/
$userId = (int)($__u['id'] ?? 0);
$favIds = favorite_ids($userId);
$addingFavs = isset($_GET['addfavs']) && $favIds !== [];

$openNow = accepts_orders();
$hours = store_hours();

$pageTitle = 'Order';
include __DIR__ . '/includes/header.php'; ?>
<main class="mx-auto w-full max-w-6xl px-4 py-8 pb-28 sm:px-6 sm:py-10 lg:pb-10">
    <div class="border-b-4 border-[#2b2118] pb-6">
        <p class="text-[11px] font-semibold uppercase tracking-[.2em] text-[#d9281c]">01 — Menu</p>
        <h1 class="mt-2 font-display text-4xl font-extrabold uppercase leading-[.95] sm:text-5xl">Order Sheet<span
                class="block text-[#7a6a58]">Build your ticket</span></h1>
        <p class="mt-4 max-w-xl text-sm leading-6 text-[#7a6a58]">Tap a menu item to add it. Use the quantity controls
            to adjust your order, then complete the delivery details.</p>
    <?php if (!$openNow): ?>
        <p class="mt-4 inline-flex max-w-xl items-start gap-2 border-l-4 border-[#e8871e] bg-[#fdf3e3] px-4 py-2.5 text-sm leading-6 text-[#8a5a08]">
            <span>⚠️ The kitchen is <b>closed right now</b> (<?= e($hours['open']) ?>–<?= e($hours['close']) ?><?= $hours['paused'] ? ' · orders paused' : '' ?>). You can still build your ticket — checkout unlocks when the store opens.</span>
        </p>
    <?php endif; ?>
    </div>
    <?php if ($addingFavs): ?><p class="mt-4 border-l-4 border-[#e8871e] bg-[#fdf3dd] px-3 py-2 text-sm text-[#6b4a08]">Your usuals were added to the ticket — check quantities and place the order.</p><?php endif; ?>
    <div class="mt-6 flex gap-2 overflow-x-auto pb-2" role="tablist" aria-label="Menu categories">
        <?php foreach ($cats as $i => $cat): ?><button type="button"
                class="cat-tab shrink-0 border px-4 py-2 text-[11px] font-bold uppercase tracking-[.12em] <?= $i === 0 ? 'border-[#2b2118] bg-[#2b2118] text-white' : 'border-[#e8ddc9] bg-white text-[#7a6a58] hover:border-[#2b2118] hover:text-[#2b2118]' ?>"
                data-cat="<?= e($cat) ?>"><?= e($cat) ?></button><?php endforeach; ?></div>
    <?php if ($covers): ?>
        <div class="mt-6 grid gap-px border border-[#2b2118] bg-[#2b2118] sm:grid-cols-2">
            <?php foreach ($covers as $cov): ?>
                <div class="relative h-52 overflow-hidden bg-[#2b2118]">
                    <img src="<?= e($cov['cover_url']) ?>" alt="<?= e($cov['name']) ?>" class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 right-0 flex items-end justify-between gap-3 p-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[.18em] text-white/70"><?= $cov['featured'] ? 'Special' : e($cov['category']) ?></p>
                            <p class="mt-1 font-display text-2xl font-extrabold uppercase leading-none text-white"><?= e($cov['name']) ?></p>
                        </div>
                        <span class="shrink-0 bg-[#d9281c] px-2.5 py-1 font-mono text-sm font-bold text-white">₱<?= number_format((float) $cov['price'], 2) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="grid gap-10 lg:grid-cols-[1fr_380px]">
        <div><?php foreach ($cats as $i => $cat): ?>
                <section class="menu-section <?= $i === 0 ? '' : 'hidden' ?>" data-section="<?= e($cat) ?>">
                    <div class="mb-2 flex items-center justify-between border-b border-[#2b2118] pb-2">
                        <h2 class="text-xs font-bold uppercase tracking-[.18em]"><?= e($cat) ?></h2><span
                            class="font-mono text-[10px] uppercase text-[#7a6a58]">tap + to add</span>
                    </div><?php foreach (array_filter($menu, fn($x) => $x['category'] === $cat) as $item): ?>
                        <div class="menu-row lift flex items-start justify-between gap-4 border-b border-[#e8ddc9] py-5"
                            data-id="<?= e((string) $item['id']) ?>" data-name="<?= e($item['name']) ?>"
                            data-price="<?= e((string) $item['price']) ?>" data-img="<?= e($item['image_url'] ?? '') ?>">
                            <span class="h-16 w-16 shrink-0 overflow-hidden border border-[#e8ddc9] bg-[#f6efe0] sm:h-20 sm:w-20"><img src="<?= e(menu_image_url($item['image_url'] ?? null)) ?>" alt="<?= e($item['name']) ?>"
                                        class="h-full w-full object-cover" loading="lazy"></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline gap-2">
                                    <h3 class="font-display text-base font-bold uppercase leading-none tracking-tight">
                                        <?= e($item['name']) ?></h3><span
                                        class="qty-badge hidden bg-[#d9281c] px-1.5 py-0.5 font-mono text-[10px] font-semibold leading-4 text-white">0×</span>
                                </div>
                                <p class="mt-1.5 max-w-md text-[13px] leading-snug text-[#7a6a58]"><?= e($item['description']) ?>
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2"><span
                                    class="font-mono text-sm font-semibold">₱<?= number_format((float) $item['price'], 2) ?></span><button
                                    type="button" class="fav-btn h-9 w-9 border <?= in_array((int) $item['id'], $favIds, true) ? 'border-[#d9281c] bg-[#fdeeec] text-[#d9281c]' : 'border-[#e8ddc9] bg-white text-[#b3a48e] hover:text-[#d9281c]' ?> text-lg leading-none" data-id="<?= (int) $item['id'] ?>" data-fav="<?= in_array((int) $item['id'], $favIds, true) ? '1' : '0' ?>" aria-label="<?= in_array((int) $item['id'], $favIds, true) ? 'Remove from favorites' : 'Add to favorites' ?>"><?= in_array((int) $item['id'], $favIds, true) ? '♥' : '♡' ?></button><button
                                    type="button" class="add-btn h-9 w-9 bg-[#2b2118] text-xl text-white hover:bg-[#d9281c]"
                                    aria-label="Add <?= e($item['name']) ?>">+</button>
                                <div class="qty-controls hidden items-center border border-[#2b2118]"><button type="button"
                                        class="minus h-9 w-9 hover:bg-[#eef0f2]" aria-label="Decrease">−</button><span
                                        class="qty w-7 text-center font-mono text-xs font-semibold">0</span><button
                                        type="button" class="plus h-9 w-9 hover:bg-[#eef0f2]" aria-label="Increase">+</button>
                                </div>
                            </div>
                        </div><?php endforeach; ?>
                </section><?php endforeach; ?>
        </div>
        <aside class="lg:sticky lg:top-20 lg:self-start">
            <div class="border border-[#2b2118] bg-white">
                <div
                    class="flex items-center justify-between border-b border-[#2b2118] bg-[#2b2118] px-4 py-3 text-white">
                    <span class="text-[11px] font-bold uppercase tracking-[.18em]">Your order</span><button
                        type="button" id="clearCart"
                        class="text-[10px] font-bold uppercase tracking-[.12em] text-white/70 hover:text-white">Clear</button>
                </div>
                <div id="ticketItems" class="px-4"></div>
                <div id="emptyTicket" class="px-4 py-10 text-sm text-[#7a6a58]">
                    <div class="text-xl">🧾</div>
                    <p class="mt-2">Your ticket is empty. Add something from the menu.</p>
                </div>
                <div class="border-t border-[#2b2118] px-4 py-3">
                    <dl class="space-y-1.5 font-mono text-[13px]">
                        <div class="flex justify-between text-[#7a6a58]">
                            <dt>Subtotal</dt>
                            <dd id="subtotal">₱0.00</dd>
                        </div>
                        <div class="flex justify-between text-[#7a6a58]">
                            <dt>Delivery</dt>
                            <dd id="delivery">₱0.00</dd>
                        </div>
                        <div class="flex justify-between border-t border-[#e8ddc9] pt-2 text-base font-bold">
                            <dt class="font-display uppercase">Total</dt>
                            <dd id="total">₱0.00</dd>
                        </div>
                    </dl>
                </div>
                <form action="actions/place_order.php" method="post" id="orderForm"
                    class="border-t border-[#2b2118] px-4 py-4"><input type="hidden" name="csrf"
                        value="<?= e(csrf_token()) ?>"><input type="hidden" name="cart" id="cartInput">
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-[.18em]">Delivery details</p>
                    <div class="space-y-2.5"><input name="customerName" required
                            class="w-full border border-[#e8ddc9] px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]"
                            placeholder="Name" value="<?= e(current_user()['name'] ?? '') ?>"><input name="phone" required
                            type="tel"
                            class="w-full border border-[#e8ddc9] px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]"
                            placeholder="Mobile number"><textarea name="address" required rows="2"
                            class="w-full resize-none border border-[#e8ddc9] px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]"
                            placeholder="Delivery address"></textarea><textarea name="notes" rows="2"
                            class="w-full resize-none border border-[#e8ddc9] px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]"
                            placeholder="Notes (optional)"></textarea></div>
                    <p class="mb-2 mt-4 text-[11px] font-bold uppercase tracking-[.18em]">Payment method</p>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="pay-opt flex cursor-pointer items-center gap-2 border border-[#e8ddc9] px-3 py-2.5 text-sm has-[:checked]:border-[#d9281c] has-[:checked]:bg-[#fdeeec]"><input type="radio" name="payment_method" value="cash" checked class="accent-[#d9281c]">💵 Cash <span class="text-[10px] text-[#7a6a58]">on delivery</span></label>
                        <label class="pay-opt flex cursor-pointer items-center gap-2 border border-[#e8ddc9] px-3 py-2.5 text-sm has-[:checked]:border-[#d9281c] has-[:checked]:bg-[#fdeeec]"><input type="radio" name="payment_method" value="gcash" class="accent-[#d9281c]">📱 GCash</label>
                    </div>
                    <div id="gcashPanel" class="mt-3 hidden border border-[#e8d19a] bg-[#fdf3dd] p-3 text-[13px] leading-5 text-[#6b4a08]">
                        <p class="text-[11px] font-bold uppercase tracking-[.14em]">Pay via GCash now</p>
                        <ol class="mt-2 list-decimal space-y-1 pl-4">
                            <li>Send <b id="gcashAmount">₱0.00</b> to <b><?= e(GCASH_NUMBER) ?></b> (<span class="whitespace-nowrap"><?= e(GCASH_NAME) ?></span>).</li>
                            <li>Copy the <b>13-digit reference number</b> from your GCash receipt.</li>
                            <li>Paste it below — the kitchen verifies it before cooking.</li>
                        </ol>
                        <input name="payment_ref" type="text" inputmode="numeric" maxlength="13"
                            class="mt-2 w-full border border-[#e2cf9c] bg-white px-3 py-2.5 text-sm tracking-[.15em] outline-none focus:border-[#8a5a08]"
                            placeholder="GCash reference no.">
                    </div>
                    <?php if (!$openNow): ?>
                        <div class="mt-4 border border-[#f2cf9e] bg-[#fdf3e3] px-3 py-2.5 text-center text-[13px] text-[#8a5a08]">Checkout is paused — the kitchen opens at <?= e($hours['open']) ?>.</div>
                    <?php else: ?>
                    <button id="placeBtn" type="submit" disabled
                        class="mt-4 h-11 w-full bg-[#d9281c] font-display text-sm font-bold uppercase tracking-[.12em] text-white disabled:cursor-not-allowed disabled:opacity-40">📍
                        Place delivery order</button>
                    <?php endif; ?>
                    <p class="mt-2 text-center text-[11px] text-[#7a6a58]">Cash on delivery, or pay ahead with GCash.</p>
                </form>
            </div>
        </aside>
    </div>
</main>
<!-- Sticky mobile order bar: live total + jump to checkout -->
<div id="mobileBar" class="fixed inset-x-0 bottom-0 z-40 border-t border-[#2b2118] bg-[#fdf8ef]/95 px-4 py-2.5 shadow-[0_-8px_24px_-14px_rgba(43,33,24,.45)] backdrop-blur transition-transform duration-300 lg:hidden">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[10px] font-bold uppercase tracking-[.16em] text-[#7a6a58]"><span id="mbCount">0</span> items · <span id="mbTotal" class="text-[#2b2118]">₱0.00</span></p>
            <p id="mbEmpty" class="text-[11px] text-[#7a6a58]">Your ticket is empty — tap + on any dish</p>
        </div>
        <button type="button" onclick="document.getElementById('orderForm').scrollIntoView({ behavior: 'smooth', block: 'start' })" class="shrink-0 bg-[#d9281c] px-5 py-2.5 font-display text-xs font-bold uppercase tracking-[.12em] text-white">View order →</button>
    </div>
</div>
<script>
    (function () {
        const fmt = n => '₱' + Number(n).toFixed(2);
        const upd = () => {
            try {
                const c = JSON.parse(localStorage.getItem('silog_cart') || '{}'); const v = Object.values(c);
                const count = v.reduce((n, x) => n + (x.qty || 0), 0);
                const sub = v.reduce((n, x) => n + x.price * x.qty, 0);
                document.getElementById('mbCount').textContent = count;
                document.getElementById('mbTotal').textContent = fmt(count ? sub + 45 : 0);
                document.getElementById('mbEmpty').classList.toggle('hidden', count > 0);
            } catch (e) {}
        };
        const bar = document.getElementById('mobileBar');
        window.addEventListener('scroll', () => bar.classList.toggle('translate-y-full', window.scrollY < 140), { passive: true });
        setInterval(upd, 700); upd();
    })();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
    const DELIVERY_FEE = 45; let cart = {}; try { cart = JSON.parse(localStorage.getItem('silog_cart') || '{}') } catch (e) { cart = {} };
    /* Deep link from home "Add" buttons (?add=ID): pre-add the item so checkout is one tap away */
    (function () { const pre = <?= json_encode($preAdd, JSON_UNESCAPED_UNICODE) ?>; if (pre && pre.id && !cart[pre.id]) { cart[pre.id] = { id: pre.id, name: pre.name, price: pre.price, qty: 1, img: (document.querySelector('.menu-row[data-id="' + pre.id + '"]')?.dataset.img) || '' }; try { localStorage.setItem('silog_cart', JSON.stringify(cart)) } catch (e) {} if (window.silogToast) silogToast(pre.name + ' added to your order'); }
        /* Re-order the usuals: load every favorited dish into the ticket. */
        const preFavs = <?= json_encode($addingFavs ? array_map(fn($m) => ['id' => (string)$m['id'], 'name' => $m['name'], 'price' => (float)$m['price'], 'img' => $m['image_url'] ?? ''], array_filter($menu, fn($m) => in_array((int)$m['id'], $favIds, true))) : [], JSON_UNESCAPED_UNICODE) ?>;
        if (Array.isArray(preFavs) && preFavs.length) { let added = 0; preFavs.forEach(f => { if (!cart[f.id]) { cart[f.id] = f; cart[f.id].qty = 1; added++ } }); if (added || Object.keys(cart).length) { try { localStorage.setItem('silog_cart', JSON.stringify(cart)) } catch (e) {} } }
    })();
    const money = n => '₱' + Number(n).toFixed(2); const save = () => { localStorage.setItem('silog_cart', JSON.stringify(cart)); render() };
    function esc(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])) }
    function render() { let subtotal = 0, count = 0; Object.values(cart).forEach(x => { subtotal += x.price * x.qty; count += x.qty }); document.getElementById('subtotal').textContent = money(subtotal); document.getElementById('delivery').textContent = money(count ? DELIVERY_FEE : 0); document.getElementById('total').textContent = money(subtotal + (count ? DELIVERY_FEE : 0)); document.getElementById('cartInput').value = JSON.stringify(Object.values(cart).map(x => ({ id: x.id, qty: x.qty }))); const form = document.getElementById('orderForm'); const isG = form.payment_method.value === 'gcash'; document.getElementById('gcashPanel').classList.toggle('hidden', !isG || !count); document.getElementById('gcashAmount').textContent = money(subtotal + (count ? DELIVERY_FEE : 0)); const refOk = !isG || /^[0-9]{13}$/.test(form.payment_ref.value.trim()); document.getElementById('placeBtn').disabled = !(count && refOk && form.customerName.value.trim() && form.phone.value.trim() && form.address.value.trim()); document.getElementById('emptyTicket').classList.toggle('hidden', count > 0); document.getElementById('ticketItems').innerHTML = Object.values(cart).map(x => `<div class="flex items-center gap-3 border-b border-[#e8ddc9] py-3"><span class="h-12 w-12 shrink-0 overflow-hidden border border-[#e8ddc9] bg-[#f6efe0]"><img src="${x.img ? esc(x.img) : 'assets/placeholder.svg'}" alt="" class="h-full w-full object-cover" onerror="this.src='assets/placeholder.svg'"></span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">${esc(x.name)}</p><p class="font-mono text-[11px] text-[#7a6a58]">${x.qty} × ${money(x.price)}</p></div><div class="flex shrink-0 items-center gap-2"><span class="font-mono text-sm font-semibold">${money(x.price * x.qty)}</span><button type="button" class="remove-ticket text-lg text-[#7a6a58] hover:text-[#d9281c]" data-id="${x.id}" aria-label="Remove">×</button></div></div>`).join(''); document.querySelectorAll('.remove-ticket').forEach(b => b.onclick = () => { delete cart[b.dataset.id]; save() }); document.querySelectorAll('.menu-row').forEach(row => { const q = cart[row.dataset.id]?.qty || 0; row.querySelector('.qty-badge').textContent = q + '×'; row.querySelector('.qty-badge').classList.toggle('hidden', !q); row.querySelector('.add-btn').classList.toggle('hidden', !!q); row.querySelector('.qty-controls').classList.toggle('hidden', !q); row.querySelector('.qty-controls').classList.toggle('flex', !!q); row.querySelector('.qty').textContent = q }); silogSyncCart() }
    document.querySelectorAll('.menu-row').forEach(row => { const id = row.dataset.id, name = row.dataset.name, price = Number(row.dataset.price); row.querySelector('.add-btn').onclick = () => { cart[id] ? cart[id].qty++ : cart[id] = { id, name, price, qty: 1 }; save() }; row.querySelector('.plus').onclick = () => { if (cart[id]) cart[id].qty++; else cart[id] = { id, name, price, qty: 1 }; save() }; row.querySelector('.minus').onclick = () => { if (cart[id]) { cart[id].qty--; if (cart[id].qty <= 0) delete cart[id]; save() } } });
    function activateCat(tab) { document.querySelectorAll('.cat-tab').forEach(t => { t.classList.remove('bg-[#2b2118]', 'text-white', 'border-[#2b2118]'); t.classList.add('bg-white', 'text-[#7a6a58]', 'border-[#e8ddc9]') }); tab.classList.add('bg-[#2b2118]', 'text-white', 'border-[#2b2118]'); tab.classList.remove('bg-white', 'text-[#7a6a58]', 'border-[#e8ddc9]'); document.querySelectorAll('.menu-section').forEach(s => s.classList.toggle('hidden', s.dataset.section !== tab.dataset.cat)) }
    document.querySelectorAll('.cat-tab').forEach(tab => tab.onclick = () => activateCat(tab));
    /* Open the category that contains the deep-linked item — no initial flash */
    (function () { const pre = <?= json_encode($preAdd, JSON_UNESCAPED_UNICODE) ?>; const row = pre && pre.id ? document.querySelector('.menu-row[data-id="' + pre.id + '"]') : null; const tab = row ? document.querySelector('.cat-tab[data-cat="' + row.closest('.menu-section').dataset.section + '"]') : document.querySelector('.cat-tab'); if (tab) activateCat(tab); })();
    document.getElementById('clearCart').onclick = () => { if (Object.keys(cart).length && !confirm('Clear your order?')) return; cart = {}; save() }; document.querySelectorAll('#orderForm input,#orderForm textarea').forEach(x => x.addEventListener('input', render));     /* Heart toggle — submits a tiny form so CSRF + flash come free. */
    document.querySelectorAll('.fav-btn').forEach(b => b.addEventListener('click', () => {
        const f = document.createElement('form');
        f.method = 'post'; f.action = 'actions/favorite.php';
        f.innerHTML = '<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">' +
            '<input type="hidden" name="menu_item_id" value="' + b.dataset.id + '">' +
            '<input type="hidden" name="returnTo" value="order.php' + String(location.search).replace(/"/g, '&quot;') + '">';
        document.body.appendChild(f); f.submit();
    }));
document.querySelectorAll('input[name="payment_method"]').forEach(r => r.addEventListener('change', () => { render(); if (window.silogToast) silogToast(r.value === 'gcash' ? 'GCash selected — send payment, then paste the reference' : 'Cash on delivery selected'); }));
    /* Confirm before sending the kitchen a real order. */
    document.getElementById('orderForm').addEventListener('submit', e => { if (!confirm('Place this delivery order now?')) e.preventDefault(); });
    render();
</script>