<?php

require_once __DIR__ . '/../includes/auth.php';

require_vendor();
verify_csrf();

$orderId = (int)($_POST['order_id'] ?? 0);
$action  = $_POST['action'] ?? '';

if ($orderId <= 0) {
    flash('Invalid order.', 'error');
    header('Location: ../vendor.php?tab=orders');
    exit;
}

try {

    $stmt = $pdo->prepare("
        SELECT *
        FROM orders
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$orderId]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new RuntimeException('Order not found.');
    }

    $current = strtolower(trim((string)$order['status']));

    $next = [
        'pending'          => 'confirmed',
        'confirmed'        => 'preparing',
        'preparing'        => 'out_for_delivery',
        'out_for_delivery' => 'delivered',
    ];

    if ($action === 'advance') {

        if (!isset($next[$current])) {
            throw new RuntimeException(
                'This order cannot be advanced from its current status.'
            );
        }

        $newStatus = $next[$current];

        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $newStatus,
            $orderId
        ]);

        flash(
            'Order #' .
            str_pad((string)$orderId, 5, '0', STR_PAD_LEFT) .
            ' updated to ' .
            ucfirst(str_replace('_', ' ', $newStatus)) .
            '.',
            'success'
        );

    } elseif ($action === 'cancel') {

        if (in_array(
            $current,
            ['delivered', 'cancelled'],
            true
        )) {
            throw new RuntimeException(
                'This order can no longer be cancelled.'
            );
        }

        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = 'cancelled'
            WHERE id = ?
        ");

        $stmt->execute([$orderId]);

        flash(
            'Order cancelled.',
            'success'
        );

    } else {

        throw new RuntimeException(
            'Invalid order action.'
        );
    }

} catch (Throwable $e) {

    flash(
        $e->getMessage(),
        'error'
    );
}

header(
    'Location: ../vendor.php?tab=orders#order-' .
    $orderId
);

exit;