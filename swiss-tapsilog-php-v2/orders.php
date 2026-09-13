<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'My Orders';

$user = current_user();

if ($user === null) {
    safe_redirect('/auth.php');
}

$userId = (int)$user['id'];

/*
|--------------------------------------------------------------------------
| Paged Orders: Active (live) vs History (delivered/cancelled)
|--------------------------------------------------------------------------
*/

const ORDERS_PER_PAGE = 15;

$tab = ($_GET['tab'] ?? 'active') === 'history' ? 'history' : 'active';
$pageNo = request_page();

$active = fetch_orders_page($userId, $pageNo, ORDERS_PER_PAGE, false);
$history = fetch_orders_page($userId, $pageNo, ORDERS_PER_PAGE, true);

$view = $tab === 'history' ? $history : $active;
$orders = $view['orders'];

/*
|--------------------------------------------------------------------------
| Items (with photos) for the orders on this page
|--------------------------------------------------------------------------
*/

$orderItems = [];

if ($orders) {

    $orderIds = array_map(
        static fn(array $order): int => (int)$order['id'],
        $orders
    );

    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

    try {

        $stmt = $pdo->prepare("
            SELECT
                oi.id,
                oi.order_id,
                oi.menu_item_id,
                oi.item_name,
                oi.unit_price,
                oi.qty,
                m.image_url
            FROM order_items oi
            LEFT JOIN menu_items m ON m.id = oi.menu_item_id
            WHERE oi.order_id IN ($placeholders)
            ORDER BY oi.id ASC
        ");

        $stmt->execute($orderIds);

        while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $orderItems[(int)$item['order_id']][] = $item;
        }

    } catch (Throwable $e) {

        error_log('orders.php item load error: ' . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| Status flow for the tracker + live polling
|--------------------------------------------------------------------------
*/

$statusFlow = [
    'pending',
    'confirmed',
    'preparing',
    'out_for_delivery',
    'delivered',
];

$flashMessage = flash();

include __DIR__ . '/includes/header.php';

?>

<main class="min-h-screen bg-[#fdf8ef]">

    <!-- Header -->
    <section class="border-b border-[#e8ddc9] bg-white">

        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">

            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">

                <div>

                    <p class="text-sm font-semibold uppercase tracking-wider text-[#d9281c]">
                        Customer Account
                    </p>

                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#2b2118] sm:text-4xl">
                        My Orders
                    </h1>

                    <p class="mt-2 text-[#7a6a58]">
                        Track live orders and revisit everything you've finished.
                    </p>



                </div>

                <?php if (fetch_favorite_items((int)$user['id'])): ?>

                    <a
                        href="order.php?addfavs=1"
                        class="inline-flex items-center justify-center rounded-xl bg-[#d9281c] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#b82016]"
                    >
                        Re-order usuals ❤
                    </a>

                <?php endif; ?>

                <a
                    href="order.php"
                    class="inline-flex items-center justify-center rounded-xl bg-[#2b2118] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#4a3a28]"
                >
                    Order Again
                </a>

            </div>

        </div>

    </section>


    <!-- Content -->
    <section class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">

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


        <!-- Tabs -->
        <div class="flex flex-wrap gap-2">

            <a
                href="orders.php?tab=active"
                class="rounded-xl px-4 py-2.5 text-sm font-semibold transition
                <?= $tab === 'active'
                    ? 'bg-[#2b2118] text-white'
                    : 'border border-[#e8ddc9] bg-white text-[#4a3a28] hover:border-[#2b2118]'
                ?>"
            >
                Active orders
                <span class="ml-1.5 rounded-full bg-[#d9281c] px-2 py-0.5 text-xs font-bold text-white">
                    <?= (int)$active['total'] ?>
                </span>
            </a>

            <a
                href="orders.php?tab=history"
                class="rounded-xl px-4 py-2.5 text-sm font-semibold transition
                <?= $tab === 'history'
                    ? 'bg-[#2b2118] text-white'
                    : 'border border-[#e8ddc9] bg-white text-[#4a3a28] hover:border-[#2b2118]'
                ?>"
            >
                History
                <span class="ml-1.5 rounded-full bg-[#4a3a28] px-2 py-0.5 text-xs font-bold text-white">
                    <?= (int)$history['total'] ?>
                </span>
            </a>

        </div>

        <p class="mt-3 text-xs text-[#8a7a64]">
            <?= $tab === 'history'
                ? 'Finished orders — delivered and cancelled — kept for your records. Page ' . (int)$view['page'] . ' of ' . (int)$view['pages'] . ', ' . ORDERS_PER_PAGE . ' per page.'
                : 'Orders currently being prepared or on the way. Page ' . (int)$view['page'] . ' of ' . (int)$view['pages'] . ', ' . ORDERS_PER_PAGE . ' per page.'
            ?>
        </p>


        <?php if (!$orders): ?>

            <!-- Empty State -->
            <div class="mt-6 rounded-2xl border border-[#e8ddc9] bg-white px-6 py-16 text-center shadow-sm">

                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#fdf8ef]">

                    <svg
                        class="h-8 w-8 text-[#8a7a64]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 2h12m-8 4a1 1 0 11-2 0 1 1 0 012 0zm8 0a1 1 0 11-2 0 1 1 0 012 0z"
                        />
                    </svg>

                </div>

                <h2 class="mt-5 text-xl font-semibold text-[#2b2118]">
                    <?= $tab === 'history' ? 'No finished orders yet' : "No orders yet" ?>
                </h2>

                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#7a6a58]">
                    <?= $tab === 'history'
                        ? "Delivered and cancelled orders will be kept here for your records."
                        : "You haven't placed an order yet. Browse our menu and place your first order."
                    ?>
                </p>

                <a
                    href="order.php"
                    class="mt-6 inline-flex items-center justify-center rounded-xl bg-[#d9281c] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#b82016]"
                >
                    Browse Menu
                </a>

            </div>

        <?php else: ?>

            <div
                id="ordersList"
                class="mt-6 space-y-6"
            >

                <?php foreach ($orders as $order): ?>

                    <?php

                    $orderId = (int)$order['id'];

                    $status = strtolower(
                        trim(
                            (string)($order['status'] ?? 'pending')
                        )
                    );

                    if (!is_valid_order_status($status)) {
                        $status = 'pending';
                    }

                    $items = $orderItems[$orderId] ?? [];

                    $isCancelled = $status === 'cancelled';
                    $canCancel = $status === 'pending' && $tab === 'active';

                    $currentStep = array_search(
                        $status,
                        $statusFlow,
                        true
                    );

                    if ($currentStep === false) {
                        $currentStep = 0;
                    }

                    ?>

                    <article
                        id="order-<?= $orderId ?>"
                        data-order-id="<?= $orderId ?>"
                        class="overflow-hidden rounded-2xl border border-[#e8ddc9] bg-white shadow-sm"
                    >

                        <!-- Order Header -->
                        <div class="border-b border-[#e8ddc9] px-5 py-5 sm:px-6">

                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

                                <div>

                                    <div class="flex flex-wrap items-center gap-3">

                                        <h2 class="text-lg font-bold text-[#2b2118]">
                                            Order #<?= e(str_pad(
                                                (string)$orderId,
                                                5,
                                                '0',
                                                STR_PAD_LEFT
                                            )) ?>
                                        </h2>

                                        <span
                                            data-status-badge
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= e(order_status_class($status)) ?>"
                                        >
                                            <?= e(order_status_label($status)) ?>
                                        </span>

                                        <?= payment_status_badge($order) ?>

                                    </div>

                                    <p class="mt-1 text-sm text-[#8a7a64]">
                                        <?= e(
                                            date(
                                                'F j, Y \a\t g:i A',
                                                strtotime(
                                                    (string)$order['created_at']
                                                )
                                            )
                                        ) ?>
                                    </p>

                                </div>

                                <div class="text-left sm:text-right">

                                    <p class="text-xs font-medium uppercase tracking-wide text-[#8a7a64]">
                                        Total
                                    </p>

                                    <p class="mt-1 text-xl font-bold text-[#2b2118]">
                                        <?= peso((float)$order['total']) ?>
                                    </p>

                                </div>

                            </div>

                        </div>


                        <?php if (!$isCancelled): ?>

                            <!-- Status Tracker -->
                            <div class="border-b border-[#e8ddc9] px-5 py-6 sm:px-6">

                                <div class="overflow-x-auto">

                                    <div class="min-w-[620px]">

                                        <div class="flex items-start">

                                            <?php foreach ($statusFlow as $index => $step): ?>

                                                <?php

                                                $completed = $index <= $currentStep;

                                                $isCurrent = $index === $currentStep;

                                                ?>

                                                <div class="flex flex-1 items-start">

                                                    <div class="flex min-w-0 flex-1 flex-col items-center">

                                                        <div
                                                            data-status-step="<?= e($step) ?>"
                                                            class="flex h-9 w-9 items-center justify-center rounded-full border-2 text-xs font-bold transition
                                                            <?= $completed
                                                                ? 'border-[#d9281c] bg-[#d9281c] text-white'
                                                                : 'border-[#e8ddc9] bg-white text-[#b3a48e]'
                                                            ?>"
                                                        >

                                                            <?php if ($completed && !$isCurrent): ?>

                                                                <svg
                                                                    class="h-4 w-4"
                                                                    viewBox="0 0 24 24"
                                                                    fill="none"
                                                                    stroke="currentColor"
                                                                    stroke-width="2.5"
                                                                >
                                                                    <path
                                                                        stroke-linecap="round"
                                                                        stroke-linejoin="round"
                                                                        d="m5 12 4 4L19 6"
                                                                    />
                                                                </svg>

                                                            <?php else: ?>

                                                                <?= $index + 1 ?>

                                                            <?php endif; ?>

                                                        </div>

                                                        <span
                                                            class="mt-2 text-center text-xs font-medium
                                                            <?= $isCurrent
                                                                ? 'text-[#2b2118]'
                                                                : 'text-[#8a7a64]'
                                                            ?>"
                                                        >
                                                            <?= e(order_status_label($step)) ?>
                                                        </span>

                                                    </div>

                                                    <?php if ($index < count($statusFlow) - 1): ?>

                                                        <div
                                                            class="mt-4 h-0.5 flex-1
                                                            <?= $index < $currentStep
                                                                ? 'bg-[#d9281c]'
                                                                : 'bg-[#e8ddc9]'
                                                            ?>"
                                                        ></div>

                                                    <?php endif; ?>

                                                </div>

                                            <?php endforeach; ?>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        <?php else: ?>

                            <!-- Cancelled Status -->
                            <div class="border-b border-[#e8ddc9] bg-[#fdf8ef] px-5 py-6 sm:px-6">

                                <div class="flex items-center gap-3">

                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#e8ddc9] text-[#4a3a28]">

                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M6 18 18 6M6 6l12 12"
                                            />
                                        </svg>

                                    </div>

                                    <div>

                                        <p class="font-semibold text-[#2b2118]">
                                            Order cancelled
                                        </p>

                                        <p class="text-sm text-[#7a6a58]">
                                            This order will not be prepared or delivered.
                                        </p>

                                    </div>

                                </div>

                            </div>

                        <?php endif; ?>


                        <!-- Order Items -->
                        <div class="px-5 py-6 sm:px-6">

                            <h3 class="text-sm font-semibold uppercase tracking-wide text-[#8a7a64]">
                                Order Items
                            </h3>

                            <?php if ($items): ?>

                                <div class="mt-4 divide-y divide-[#f6efe0]">

                                    <?php foreach ($items as $item): ?>

                                        <?php
                                        $qty = max(
                                            1,
                                            (int)$item['qty']
                                        );

                                        $unitPrice = (float)$item['unit_price'];

                                        $lineTotal = $unitPrice * $qty;
                                        ?>

                                        <div class="flex items-center gap-4 py-3">

                                            <span class="h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-[#e8ddc9] bg-[#f6efe0]">

                                                <img
                                                    src="<?= e(menu_image_url($item['image_url'] ?? null)) ?>"
                                                    alt="<?= e((string)$item['item_name']) ?>"
                                                    class="h-full w-full object-cover"
                                                    loading="lazy"
                                                >

                                            </span>

                                            <div class="min-w-0 flex-1">

                                                <p class="font-medium text-[#2b2118]">
                                                    <?= e((string)$item['item_name']) ?>
                                                </p>

                                                <p class="mt-1 text-sm text-[#7a6a58]">
                                                    <?= $qty ?> × <?= peso($unitPrice) ?>
                                                </p>

                                            </div>

                                            <p class="shrink-0 font-semibold text-[#2b2118]">
                                                <?= peso($lineTotal) ?>
                                            </p>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php else: ?>

                                <p class="mt-3 text-sm text-[#7a6a58]">
                                    No item details are available for this order.
                                </p>

                            <?php endif; ?>

                        </div>


                        <!-- Delivery Information -->
                        <div class="border-t border-[#e8ddc9] px-5 py-6 sm:px-6">

                            <h3 class="text-sm font-semibold uppercase tracking-wide text-[#8a7a64]">
                                Delivery Information
                            </h3>

                            <dl class="mt-4 grid gap-4 sm:grid-cols-2">

                                <div>

                                    <dt class="text-xs font-medium uppercase tracking-wide text-[#8a7a64]">
                                        Customer
                                    </dt>

                                    <dd class="mt-1 text-sm font-medium text-[#2b2118]">
                                        <?= e((string)$order['customer_name']) ?>
                                    </dd>

                                </div>


                                <div>

                                    <dt class="text-xs font-medium uppercase tracking-wide text-[#8a7a64]">
                                        Phone
                                    </dt>

                                    <dd class="mt-1 text-sm text-[#2b2118]">
                                        <?= e((string)$order['phone']) ?>
                                    </dd>

                                </div>


                                <div class="sm:col-span-2">

                                    <dt class="text-xs font-medium uppercase tracking-wide text-[#8a7a64]">
                                        Delivery Address
                                    </dt>

                                    <dd class="mt-1 text-sm leading-6 text-[#2b2118]">
                                        <?= nl2br(e((string)$order['address'])) ?>
                                    </dd>

                                </div>


                                <?php if (!empty($order['notes'])): ?>

                                    <div class="sm:col-span-2">

                                        <dt class="text-xs font-medium uppercase tracking-wide text-[#8a7a64]">
                                            Notes
                                        </dt>

                                        <dd class="mt-1 text-sm leading-6 text-[#2b2118]">
                                            <?= nl2br(e((string)$order['notes'])) ?>
                                        </dd>

                                    </div>

                                <?php endif; ?>

                            </dl>

                        </div>


                        <!-- Order Summary -->
                        <div class="border-t border-[#e8ddc9] bg-[#fdf8ef] px-5 py-6 sm:px-6">

                            <div class="ml-auto max-w-sm space-y-3">

                                <div class="flex justify-between text-sm">

                                    <span class="text-[#7a6a58]">
                                        Subtotal
                                    </span>

                                    <span class="font-medium text-[#2b2118]">
                                        <?= peso((float)$order['subtotal']) ?>
                                    </span>

                                </div>


                                <div class="flex justify-between text-sm">

                                    <span class="text-[#7a6a58]">
                                        Delivery fee
                                    </span>

                                    <span class="font-medium text-[#2b2118]">
                                        <?= peso((float)$order['delivery_fee']) ?>
                                    </span>

                                </div>


                                <?php if (strtolower((string)($order['payment_method'] ?? 'cash')) === 'gcash'): ?>

                                    <div class="flex justify-between text-sm">

                                        <span class="text-[#7a6a58]">
                                            GCash ref
                                        </span>

                                        <span class="font-mono text-[#2b2118]">
                                            <?= !empty($order['payment_ref']) ? e((string)$order['payment_ref']) : '— awaiting resubmission —' ?>
                                        </span>

                                    </div>

                                <?php endif; ?>


                                <div class="flex justify-between border-t border-[#e8ddc9] pt-3">

                                    <span class="font-semibold text-[#2b2118]">
                                        Total
                                    </span>

                                    <span class="text-lg font-bold text-[#2b2118]">
                                        <?= peso((float)$order['total']) ?>
                                    </span>

                                </div>

                            </div>

                        </div>


                        <!-- Actions -->
                        <?php if ($canCancel): ?>

                            <div class="flex flex-col gap-3 border-t border-[#e8ddc9] px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">

                                <p class="text-sm text-[#7a6a58]">
                                    You can cancel this order while it is still pending.
                                </p>

                                <form
                                    method="post"
                                    action="actions/update_order.php"
                                    onsubmit="return confirm('Are you sure you want to cancel this order?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf"
                                        value="<?= e(csrf_token()) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?= $orderId ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="cancel"
                                    >

                                    <button
                                        type="submit"
                                        class="inline-flex w-full items-center justify-center rounded-xl border border-[#f5cfc9] bg-white px-4 py-2.5 text-sm font-semibold text-[#d9281c] transition hover:bg-[#fdeeec] sm:w-auto"
                                    >
                                        Cancel Order
                                    </button>

                                </form>

                            </div>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

            <?= render_pagination($view['page'], $view['pages'], ['tab' => $tab]) ?>

        <?php endif; ?>

    </section>

</main>


<script>

/*
|--------------------------------------------------------------------------
| Live Order Status
|--------------------------------------------------------------------------
|
| Polls the customer's orders every 5 seconds.
|
*/

(function () {

    const ordersList = document.getElementById('ordersList');

    if (!ordersList) {
        return;
    }

    let polling = false;

    async function refreshOrderStatus() {

        if (polling) {
            return;
        }

        polling = true;

        try {

            const response = await fetch(
                'actions/order_status.php',
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (
                !data ||
                data.success !== true ||
                !Array.isArray(data.orders)
            ) {
                return;
            }

            data.orders.forEach(function (order) {

                const orderElement = document.querySelector(
                    '[data-order-id="' +
                    Number(order.id) +
                    '"]'
                );

                if (!orderElement) {
                    return;
                }

                updateOrderStatus(
                    orderElement,
                    String(order.status || '').toLowerCase()
                );

            });

        } catch (error) {

            /*
             * Silently ignore temporary polling failures.
             * The page remains usable even when the endpoint is unavailable.
             */

        } finally {

            polling = false;

        }

    }


    function updateOrderStatus(orderElement, status) {

        if (!isValidStatus(status)) {
            return;
        }

        /*
         * Update badge.
         */

        const badge = orderElement.querySelector(
            '[data-status-badge]'
        );

        if (badge) {

            badge.textContent = statusLabel(status);

            badge.className =
                'inline-flex rounded-full px-3 py-1 text-xs font-semibold ' +
                statusClass(status);

        }


        /*
         * Update tracker.
         */

        const currentIndex = statusFlow.indexOf(status);

        if (currentIndex >= 0) {

            const steps = orderElement.querySelectorAll(
                '[data-status-step]'
            );

            steps.forEach(function (stepElement, index) {

                const completed = index <= currentIndex;

                const isCurrent = index === currentIndex;

                stepElement.className =
                    'flex h-9 w-9 items-center justify-center rounded-full border-2 text-xs font-bold transition ' +
                    (
                        completed
                            ? 'border-[#d9281c] bg-[#d9281c] text-white'
                            : 'border-[#e8ddc9] bg-white text-[#b3a48e]'
                    );

                if (completed && !isCurrent) {

                    stepElement.innerHTML = `
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m5 12 4 4L19 6"
                            />
                        </svg>
                    `;

                } else {

                    stepElement.textContent = index + 1;

                }

            });


            /*
             * Update connecting lines.
             */

            const tracker = orderElement.querySelector(
                '.min-w-\\\\[620px\\\\]'
            );

            if (tracker) {

                const lines = tracker.querySelectorAll(
                    '.h-0\\\\.5.flex-1'
                );

                lines.forEach(function (line, index) {

                    line.classList.toggle(
                        'bg-[#d9281c]',
                        index < currentIndex
                    );

                    line.classList.toggle(
                        'bg-[#e8ddc9]',
                        index >= currentIndex
                    );

                });

            }

        }

    }


    function isValidStatus(status) {

        return statusFlow.includes(status) ||
            status === 'cancelled';

    }


    const statusFlow = [
        'pending',
        'confirmed',
        'preparing',
        'out_for_delivery',
        'delivered'
    ];


    function statusLabel(status) {

        const labels = {

            pending: 'Placed',

            confirmed: 'Confirmed',

            preparing: 'Preparing',

            out_for_delivery: 'On the Way',

            delivered: 'Delivered',

            cancelled: 'Cancelled'

        };

        return labels[status] ||
            status
                .replaceAll('_', ' ')
                .replace(/\\b\\w/g, function (letter) {
                    return letter.toUpperCase();
                });

    }


    function statusClass(status) {

        const classes = {

            pending:
                'bg-[#fdeecd] text-[#8a5a08] border border-[#f2ddb5]',

            confirmed:
                'bg-[#fde3c8] text-[#8a4a08] border border-[#f2cf9e]',

            preparing:
                'bg-[#fbdcc0] text-[#8a3d08] border border-[#f2c39a]',

            out_for_delivery:
                'bg-[#fdeeec] text-[#8f1a11] border border-[#f5cfc9]',

            delivered:
                'bg-[#e2ecd2] text-[#4a5a1e] border border-[#c6d8a8]',

            cancelled:
                'bg-[#f6efe0] text-[#7a6a58] border border-[#e8ddc9]'

        };

        return classes[status] ||
            'bg-[#f6efe0] text-[#4a3a28] border border-[#e8ddc9]';

    }


    /*
     * Initial refresh.
     */

    refreshOrderStatus();


    /*
     * Refresh every 5 seconds.
     */

    setInterval(
        refreshOrderStatus,
        5000
    );

})();

</script>


<?php

include __DIR__ . '/includes/footer.php';

?>
