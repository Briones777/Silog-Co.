<?php

require_once __DIR__ . '/../includes/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Your session has expired.'
    ]);

    exit;
}

try {

    /*
     * The orders table has no updated_at column; selecting it threw a
     * SQL error and made every live-status poll fail with a 500.
     */
    $stmt = $pdo->prepare("
        SELECT
            id,
            status,
            created_at
        FROM orders
        WHERE user_id = ?
        ORDER BY id DESC
    ");

    $stmt->execute([$userId]);

    $orders = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $orders[] = [
            'id' => (int)$row['id'],
            'status' => strtolower(trim((string)$row['status'])),
            'created_at' => $row['created_at'] ?? null
        ];
    }

    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to retrieve order status.'
    ]);
}