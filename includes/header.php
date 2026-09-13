<?php require_once __DIR__ . '/auth.php';
$user = current_user();
$pageTitle = $pageTitle ?? 'Silog & Co.';
$isVendor = $user && ($user['role'] ?? 'customer') === 'vendor';
$page = basename($_SERVER['PHP_SELF']);
$vendorTab = $_GET['tab'] ?? 'dashboard';

/*
 * One-shot cart clearance. Set by order placement, sign-in, vendor
 * sign-in, and sign-out so a finished ticket never leaks into the next
 * visit — or into the next customer who uses this browser.
 */
$clearCartNow = false;
if (!empty($_COOKIE['silog_cart_clear'])) {
    $clearCartNow = true;
    if (!headers_sent()) {
        setcookie('silog_cart_clear', '', ['expires' => time() - 42000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
    unset($_COOKIE['silog_cart_clear']);
}
$navLink = fn(string $href, string $label, bool $active) => '<a href="' . $href . '" class="nav-link relative text-[11px] font-semibold uppercase tracking-[.14em] ' . ($active ? 'nav-active text-[#d9281c]' : 'text-[#7a6a58] hover:text-[#2b2118]') . '">' . $label . '</a>';
$mobileTab = fn(string $href, string $label, bool $active) => '<a href="' . $href . '" class="py-3 text-center text-[10px] font-bold uppercase tracking-[.14em] ' . ($active ? 'text-[#d9281c] border-b-2 border-[#d9281c]' : 'text-[#7a6a58]') . '" ' . ($active ? 'aria-current="page"' : '') . '>' . $label . '</a>';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#fdf8ef">
    <title><?= e($pageTitle) ?> — Silog &amp; Co.</title>
    <link rel="icon" type="image/png" href="/assets/uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'Helvetica Neue', 'Arial', 'sans-serif'], display: ['Inter Tight', 'Inter', 'Helvetica Neue', 'Arial', 'sans-serif'], mono: ['IBM Plex Mono', 'ui-monospace', 'monospace'] } } } }</script>
    <style>
        body {
            font-family: Inter, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased
        }

        .font-display {
            font-family: "Inter Tight", Inter, Arial, sans-serif
        }

        ::selection {
            background: #d9281c;
            color: #fff
        }

        html {
            scroll-behavior: smooth
        }

        /* Animated nav underline */
        .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            right: 100%;
            bottom: -6px;
            height: 2px;
            background: #d9281c;
            transition: right .22s ease
        }

        .nav-link:hover::after,
        .nav-link.nav-active::after {
            right: 0
        }

        /* Keyboard focus rings everywhere */
        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        textarea:focus-visible,
        select:focus-visible,
        [tabindex]:focus-visible {
            outline: 2px solid #d9281c;
            outline-offset: 2px
        }

        /* Cart badge pop */
        @keyframes pop {
            0% {
                transform: scale(.6)
            }

            60% {
                transform: scale(1.25)
            }

            100% {
                transform: scale(1)
            }
        }

        .animate-pop {
            animation: pop .28s ease
        }

        /* Toasts */
        @keyframes toast-in {
            from {
                opacity: 0;
                transform: translateY(10px) scale(.97)
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1)
            }
        }

        @keyframes toast-out {
            to {
                opacity: 0;
                transform: translateY(8px)
            }
        }

        .toast {
            animation: toast-in .22s ease;
            pointer-events: auto
        }

        .toast.toast-leaving {
            animation: toast-out .25s ease forwards
        }

        /* Flash dismissal */
        .flash-box {
            transition: opacity .4s ease, transform .4s ease
        }

        .flash-box.flash-hide {
            opacity: 0;
            transform: translateY(-6px)
        }

        /* Card lift for interactive menu rows */
        .lift {
            transition: transform .18s ease, box-shadow .18s ease
        }

        .lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px -14px rgba(43, 33, 24, .45)
        }
    </style>
</head>

<body class="min-h-screen bg-[#fdf8ef] text-[#2b2118]">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50 focus:bg-[#2b2118] focus:px-4 focus:py-2 focus:text-sm focus:text-white">Skip to content</a>
    <header class="sticky top-0 z-30 border-b border-[#2b2118] bg-[#fdf8ef]/95 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6"><a
                href="<?= $isVendor ? 'vendor.php' : 'index.php' ?>" class="flex shrink-0 items-center gap-2.5">
                <img src="assets/uploads/logo.png" alt="Silog & Co." class="h-8 w-8 object-contain"><span
                    class="font-display text-lg font-extrabold uppercase tracking-tight">Silog &amp; Co.</span></a>
            <nav class="hidden items-center gap-5 sm:flex" aria-label="Main">
                <?= $navLink('index.php', 'Home', $page === 'index.php') ?>
                <?php if ($isVendor): ?>
                    <?= $navLink('vendor.php?tab=dashboard', 'Dashboard', $page === 'vendor.php' && $vendorTab === 'dashboard') ?>
                    <?= $navLink('vendor.php?tab=orders', 'Queue', $page === 'vendor.php' && $vendorTab === 'orders') ?>
                    <?= $navLink('vendor.php?tab=history', 'History', $page === 'vendor.php' && $vendorTab === 'history') ?>
                    <?= $navLink('vendor.php?tab=menu', 'Menu', $page === 'vendor.php' && $vendorTab === 'menu') ?>
                <?php else: ?>
                    <?= $navLink('order.php', 'Order', $page === 'order.php') ?>
                    <?= $navLink('orders.php', 'My Orders', $page === 'orders.php') ?>
                    <?= $navLink('account.php', 'Account', $page === 'account.php') ?>
                <?php endif; ?>
            </nav>
            <div class="flex items-center gap-1.5">
                <?php if (!$isVendor): ?><a href="order.php" id="cartChip"
                        class="flex h-9 items-center gap-1 px-2.5 text-[11px] font-semibold uppercase tracking-[.12em] hover:text-[#d9281c]">🛒
                        <span class="hidden sm:inline">Order</span> <span id="cartCount"
                            class="inline-flex min-w-4 justify-center border border-[#2b2118]/30 px-0.5 font-mono text-[10px] leading-4">0</span></a><?php endif; ?><?php if ($user): ?>
                    <form action="actions/logout.php" method="post"><input type="hidden" name="csrf"
                            value="<?= e(csrf_token()) ?>"><button type="submit"
                            class="h-9 px-2.5 text-[11px] font-semibold uppercase tracking-[.12em] hover:text-[#d9281c]">↪
                            <span class="hidden sm:inline">Sign out</span></button></form><?php else: ?><a
                        href="auth.php?returnTo=/order.php"
                        class="border border-[#2b2118] px-3 py-2 text-[11px] font-semibold uppercase tracking-[.12em] hover:bg-[#2b2118] hover:text-white">Sign
                        in</a><?php endif; ?>
            </div>
        </div>
        <div class="border-t border-[#e8ddc9] sm:hidden">
            <nav class="mx-auto grid max-w-6xl grid-cols-4 px-2 <?= $isVendor ? '!grid-cols-5' : '' ?>" aria-label="Mobile tabs">
                <?= $mobileTab('index.php', 'Home', $page === 'index.php') ?>
                <?php if ($isVendor): ?>
                    <?= $mobileTab('vendor.php?tab=dashboard', 'Desk', $page === 'vendor.php' && $vendorTab === 'dashboard') ?>
                    <?= $mobileTab('vendor.php?tab=orders', 'Queue', $page === 'vendor.php' && $vendorTab === 'orders') ?>
                    <?= $mobileTab('vendor.php?tab=history', 'History', $page === 'vendor.php' && $vendorTab === 'history') ?>
                    <?= $mobileTab('vendor.php?tab=menu', 'Menu', $page === 'vendor.php' && $vendorTab === 'menu') ?>
                <?php else: ?>
                    <?= $mobileTab('order.php', 'Order', $page === 'order.php') ?>
                    <?= $mobileTab('orders.php', 'Orders', $page === 'orders.php') ?>
                    <?= $mobileTab('account.php', 'Account', $page === 'account.php') ?>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <?php if ($f = flash()): ?>
        <div class="mx-auto max-w-6xl px-4 pt-4 sm:px-6" id="flashWrap">
            <div
                class="flash-box flex items-start justify-between gap-3 border-l-4 <?= ($f['type'] === 'error' ? 'border-[#d9281c] bg-[#fdeeec] text-[#8f1a11]' : 'border-[#00593d] bg-[#e6f4ef] text-[#00593d]') ?> px-4 py-3 text-sm shadow-sm" role="status">
                <span class="flex items-start gap-2"><span><?= $f['type'] === 'error' ? '⚠️' : '✅' ?></span><span><?= e($f['message']) ?></span></span>
                <button type="button" onclick="this.closest('.flash-box').remove()" class="shrink-0 text-base leading-none opacity-60 hover:opacity-100" aria-label="Dismiss">×</button>
            </div>
        </div>
        <script>
            setTimeout(() => { const w = document.getElementById('flashWrap'); if (w) { const b = w.querySelector('.flash-box'); if (b) { b.classList.add('flash-hide'); setTimeout(() => w.remove(), 450) } } }, 6000);
        </script>
    <?php endif; ?>
    <!-- Toast stack -->
    <div id="toastWrap" class="pointer-events-none fixed bottom-20 right-4 z-50 flex w-72 flex-col gap-2 sm:bottom-6" aria-live="polite"></div>
    <script>
        /* Global cart-badge sync — works from any page, fixed the undefined updateCartCount bug */
        window.silogCartCount = function () {
            try { const c = JSON.parse(localStorage.getItem('silog_cart') || '{}'); return Object.values(c).reduce((n, x) => n + (x.qty || 0), 0) } catch (e) { return 0 }
        };
        window.silogSyncCart = function () {
            const el = document.getElementById('cartCount'); if (!el) return;
            const n = silogCartCount();
            if (el.textContent !== String(n)) el.classList.remove('animate-pop'), void el.offsetWidth, el.classList.add('animate-pop');
            el.textContent = n;
            el.classList.toggle('bg-[#d9281c]', n > 0); el.classList.toggle('text-white', n > 0); el.classList.toggle('border-[#d9281c]', n > 0);
        };
        window.silogToast = function (msg, type = 'ok') {
            const wrap = document.getElementById('toastWrap'); if (!wrap) return;
            const t = document.createElement('div');
            t.className = 'toast flex items-start gap-2 border-l-4 px-3.5 py-2.5 text-[13px] shadow-lg ' + (type === 'error' ? 'border-[#d9281c] bg-[#2b2118] text-[#fdeeec]' : 'border-[#f2b01e] bg-[#2b2118] text-[#fdf8ef]');
            t.innerHTML = '<span>' + (type === 'error' ? '⚠️' : '🍽️') + '</span><span class="min-w-0 break-words"></span>';
            t.lastElementChild.textContent = msg;
            wrap.appendChild(t);
            setTimeout(() => { t.classList.add('toast-leaving'); setTimeout(() => t.remove(), 280) }, 3000);
        };
        silogSyncCart();
        <?php if (!empty($clearCartNow)): ?>
        try { localStorage.removeItem('silog_cart'); } catch (e) {}
        silogSyncCart();
        <?php endif; ?>
        window.addEventListener('storage', e => { if (e.key === 'silog_cart') silogSyncCart() });
    </script>
    <main id="main-content">
