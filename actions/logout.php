<?php
require_once __DIR__ . '/../includes/auth.php';
verify_csrf();

/*
 * Ending the session is all it takes: there are no guest accounts, so
 * nothing persists on the device after sign-out. The device cart is
 * wiped too, so the next customer starts with an empty ticket.
 */
$_SESSION = [];
session_destroy();
setcookie('silog_cart_clear', '1', ['expires' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);

header('Location: ../index.php');
exit;
