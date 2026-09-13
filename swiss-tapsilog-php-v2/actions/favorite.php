<?php
require_once __DIR__ . '/../includes/auth.php';

require_login();

verify_csrf();

$user = current_user();

if ($user === null) {
    safe_redirect('/auth.php');
}

$userId = (int)$user['id'];
$menuItemId = (int)($_POST['menu_item_id'] ?? 0);

/*
 * Only available items can be favorited.
 */
$stmt = $pdo->prepare("SELECT 1 FROM menu_items WHERE id = ? AND available = 1");
$stmt->execute([$menuItemId]);

if (!$stmt->fetchColumn()) {
    flash('That dish is not on the menu right now.', 'error');
    safe_redirect('/order.php');
}

$favorited = toggle_favorite($userId, $menuItemId);

flash($favorited
    ? 'Added to your favorites ❤️'
    : 'Removed from your favorites');

/*
 * Return to where the user came from (order sheet, home, account).
 * Referers are absolute URLs, so reduce them to a safe internal path.
 */
$returnTo = $_POST['returnTo'] ?? $_SERVER['HTTP_REFERER'] ?? '';

$allowedPages = ['index.php', 'order.php', 'account.php', 'orders.php'];
$returnPath = '';

if (is_string($returnTo) && $returnTo !== '') {
    $parts = parse_url($returnTo);
    $candidate = ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '');

    if (
        $candidate !== '' &&
        in_array(basename($parts['path'] ?? ''), $allowedPages, true)
    ) {
        $returnPath = $candidate;
    }
}

safe_redirect($returnPath !== '' ? $returnPath : '/order.php');
