<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'My Account';

$user = current_user();

if ($user === null) {
    safe_redirect('/auth.php');
}

$userId = (int)$user['id'];

$favItems = fetch_favorite_items($userId);

/*
|--------------------------------------------------------------------------
| Stats
|--------------------------------------------------------------------------
*/

$totalOrders = 0;
$spent = 0.0;
$activeOrders = 0;

try {

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN status NOT IN ('cancelled') THEN total ELSE 0 END), 0) AS spent,
            SUM(status IN ('pending','confirmed','preparing','out_for_delivery')) AS active_orders
        FROM orders
        WHERE user_id = ?
    ");

    $stmt->execute([$userId]);

    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalOrders = (int)($stats['total_orders'] ?? 0);
    $spent = (float)($stats['spent'] ?? 0);
    $activeOrders = (int)($stats['active_orders'] ?? 0);

} catch (Throwable $e) {

    error_log('account.php stats error: ' . $e->getMessage());
}

$flashMessage = flash();

include __DIR__ . '/includes/header.php';

?>

<main class="min-h-screen bg-[#fdf8ef]">

    <!-- Header -->
    <section class="border-b border-[#e8ddc9] bg-white">

        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">

            <p class="text-sm font-semibold uppercase tracking-wider text-[#d9281c]">
                Customer Account
            </p>

            <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#2b2118] sm:text-4xl">
                My Account
            </h1>

        </div>

    </section>

    <!-- Content -->
    <section class="mx-auto max-w-5xl px-4 py-8 sm:px-6">

        <?php if ($flashMessage): ?>

            <div
                class="mb-6 rounded-xl border px-4 py-3 text-sm
                <?= ($flashMessage['type'] ?? '') === 'error'
                    ? 'border-[#f5cfc9] bg-[#fdeeec] text-[#8f1a11]'
                    : 'border-[#c6d8a8] bg-[#eef3e2] text-[#4a5a1e]'
                ?>"
            >
                <?= e((string)$flashMessage['message']) ?>
            </div>

        <?php endif; ?>

        <div class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">

            <!-- Profile card -->
            <div class="space-y-6">

                <div class="rounded-2xl border border-[#e8ddc9] bg-white p-6 shadow-sm">

                    <div class="flex items-center gap-4">

                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[#2b2118] font-display text-2xl font-extrabold text-[#f2b01e]">
                            <?= e(mb_strtoupper(mb_substr((string)($user['name'] ?? '?'), 0, 1))) ?>
                        </div>

                        <div class="min-w-0">

                            <h2 class="truncate text-xl font-bold text-[#2b2118]">
                                <?= e((string)($user['name'] ?: 'Customer')) ?>
                            </h2>

                                <p class="mt-0.5 break-all text-sm text-[#7a6a58]">
                                    <?= e((string)($user['email'] ?? '')) ?>
                                </p>

                            <?php if (empty($user['email']) && !empty($user['phone'])): ?>

                                <p class="mt-0.5 break-all text-sm text-[#7a6a58]">
                                    <?= e((string)$user['phone']) ?>
                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                    <div class="mt-5 space-y-3 border-t border-[#e8ddc9] pt-5 text-sm">

                        <div class="flex justify-between gap-4">
                            <span class="text-[#8a7a64]">Member since</span>
                            <span class="font-medium text-[#2b2118]">
                                <?= e(!empty($user['created_at'])
                                    ? date('F j, Y', strtotime((string)$user['created_at']))
                                    : 'Unknown') ?>
                            </span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-[#8a7a64]">Account type</span>
                            <span class="font-medium text-[#2b2118]">
                                Registered
                            </span>
                        </div>

                    </div>

                </div>

            </div>

            <!-- Stats + history -->
            <div class="space-y-6">

                <div class="grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-[#e8ddc9] bg-[#e8ddc9] shadow-sm">

                    <div class="bg-white p-5 text-center">

                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#8a7a64]">Orders</p>

                        <p class="mt-1 text-2xl font-bold text-[#2b2118]"><?= $totalOrders ?></p>

                    </div>

                    <div class="bg-white p-5 text-center">

                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#8a7a64]">In progress</p>

                        <p class="mt-1 text-2xl font-bold text-[#d9281c]"><?= $activeOrders ?></p>

                    </div>

                    <div class="bg-white p-5 text-center">

                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#8a7a64]">Total spent</p>

                        <p class="mt-1 text-2xl font-bold text-[#2b2118]"><?= peso($spent) ?></p>

                    </div>

                </div>

                <!-- Favorites: one-tap re-ordering -->
                <div class="rounded-2xl border border-[#e8ddc9] bg-white p-6 shadow-sm">

                    <div class="flex items-center justify-between gap-3">

                        <h2 class="text-lg font-bold text-[#2b2118]">
                            Your favorites
                        </h2>

                        <?php if ($favItems): ?>

                            <a href="order.php?addfavs=1"
                               class="rounded-lg bg-[#d9281c] px-3.5 py-2 text-xs font-bold uppercase tracking-wide text-white transition hover:bg-[#b82016]">
                                Re-order usuals ❤
                            </a>

                        <?php endif; ?>

                    </div>

                    <p class="mt-1 text-sm leading-6 text-[#7a6a58]">
                        Heart dishes on the order sheet and they show up here for one-tap re-ordering.
                    </p>

                    <?php if (!$favItems): ?>

                        <div class="mt-4 rounded-xl bg-[#fdf8ef] p-4 text-sm text-[#7a6a58]">
                            No favorites yet. Tap the ♡ beside any dish on the
                            <a href="order.php" class="font-semibold text-[#d9281c] hover:underline">order sheet</a>
                            to save your usuals.
                        </div>

                    <?php else: ?>

                        <ul class="mt-4 divide-y divide-[#f6efe0]">

                            <?php foreach ($favItems as $favItem): ?>

                                <li class="flex items-center gap-3 py-3">

                                    <span class="h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-[#e8ddc9]">
                                        <img src="<?= e(menu_image_url($favItem['image_url'] ?? null)) ?>"
                                             alt="" class="h-full w-full object-cover" loading="lazy">
                                    </span>

                                    <div class="min-w-0 flex-1">

                                        <p class="truncate font-medium text-[#2b2118]">
                                            <?= e((string)$favItem['name']) ?>
                                        </p>

                                        <p class="text-xs text-[#8a7a64]">
                                            <?= e((string)$favItem['category']) ?>
                                        </p>

                                    </div>

                                    <span class="shrink-0 font-semibold text-[#2b2118]">
                                        <?= peso((float)$favItem['price']) ?>
                                    </span>

                                    <a href="order.php?add=<?= (int)$favItem['id'] ?>"
                                       class="shrink-0 rounded-lg border border-[#2b2118] px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-[#2b2118] transition hover:bg-[#d9281c] hover:border-[#d9281c] hover:text-white">
                                        Add
                                    </a>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>

                </div>

                <!-- Quick links -->
                <div class="grid gap-3 sm:grid-cols-2">

                    <a href="orders.php" class="rounded-xl border border-[#e8ddc9] bg-white px-4 py-3 text-sm font-semibold text-[#2b2118] transition hover:border-[#2b2118]">
                        View my orders →
                    </a>

                    <a href="order.php" class="rounded-xl bg-[#d9281c] px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-[#b82016]">
                        Order food
                    </a>

                </div>

            </div>

        </div>

    </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
