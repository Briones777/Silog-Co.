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

    $stmt = $pdo->prepare("SELECT id, status, payment_method, payment_ref FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new RuntimeException('Order not found.');
    }

    if (strtolower((string)$order['payment_method']) !== 'gcash') {
        throw new RuntimeException('This order is not a GCash order.');
    }

    if (!empty($order['payment_verified_at'])) {
        throw new RuntimeException('This payment is already verified.');
    }

    if ($action === 'verify') {

        $stmt = $pdo->prepare("UPDATE orders SET payment_verified_at = NOW() WHERE id = ?");
        $stmt->execute([$orderId]);

        flash('GCash payment verified for order #' . str_pad((string)$orderId, 5, '0', STR_PAD_LEFT) . '.');

    } elseif ($action === 'decline') {

        $stmt = $pdo->prepare("UPDATE orders SET payment_ref = NULL WHERE id = ?");
        $stmt->execute([$orderId]);

        flash('Reference number cleared. The customer must resubmit it.', 'warning');

    } else {

        throw new RuntimeException('Invalid payment action.');
    }

} catch (Throwable $e) {

    flash($e->getMessage(), 'error');
}

$returnTab = ($order['status'] ?? '') === 'pending' ? 'orders' : 'history';

header('Location: ../vendor.php?tab=' . $returnTab . '#order-' . $orderId);
exit;
