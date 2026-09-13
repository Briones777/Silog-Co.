<?php
require_once __DIR__ . '/includes/auth.php';

$current = current_user();

if ($current) {
    safe_redirect((($current['role'] ?? 'customer') === 'vendor') ? '/vendor.php' : '/order.php');
}

$userId = (int)($_SESSION['reset_user_id'] ?? 0);

if ($userId <= 0) {
    flash('Start the reset again — the code request expired.', 'error');
    safe_redirect('/forgot_password.php');
}

$stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$account = $stmt->fetch();

if (!$account) {
    unset($_SESSION['reset_user_id']);
    safe_redirect('/forgot_password.php');
}

$reset = live_password_reset($userId);
$error = '';
$done = false;
$channel = is_array($reset) ? ($reset['channel'] ?? 'email') : 'email';

/*
|--------------------------------------------------------------------------
| Step 3 — verify code + set new password
|--------------------------------------------------------------------------
|
| The code check and the password swap are two POSTs of the same form:
| first it confirms the code (session-linked, attempt-capped), then it
| completes the reset. Every verify burns an attempt (max 5), and a
| successful reset also clears the customer's cart-clear flag flow so
| nothing else is pending.
|
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    $fresh = live_password_reset($userId);

    if ($fresh === null) {
        $error = 'That code is no longer valid. Request a new one.';
    } elseif (strlen($code) !== 6 || !verify_reset_code($userId, $code)) {
        $left = max(0, RESET_MAX_ATTEMPTS - (int)$fresh['attempts'] - 1);
        $error = 'Wrong or expired code' . ($left > 0 ? " — {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.' : '. Request a new code.');
    } elseif (strlen($password) < 4) {
        $error = 'New password must be at least 4 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        complete_password_reset($userId, $password);
        unset($_SESSION['reset_user_id'], $_SESSION['debug_reset_code']);
        $done = true;
    }
}

$pageTitle = 'Reset password';
include __DIR__ . '/includes/header.php';
$devCode = $_SESSION['debug_reset_code'] ?? null;
?>
<div class="flex min-h-[calc(100vh-56px)] items-center justify-center px-4 py-14"><div class="w-full max-w-sm">
<div class="mb-6 border-b-4 border-[#2b2118] pb-4"><p class="text-[11px] font-semibold uppercase tracking-[.2em] text-[#d9281c]">00 — Access</p><h1 class="mt-2 font-display text-3xl font-extrabold uppercase leading-none">Reset password</h1></div>

<?php if ($done): ?>

<div class="border border-[#2b2118] bg-white p-5 text-center">
    <p class="text-2xl">✅</p>
    <p class="mt-2 font-display text-lg font-extrabold uppercase">Password updated</p>
    <p class="mt-2 text-sm leading-6 text-[#7a6a58]">Sign in with your new password.</p>
    <a href="auth.php" class="mt-4 inline-block bg-[#2b2118] px-5 py-2.5 font-display text-[11px] font-bold uppercase tracking-[.12em] text-white hover:bg-[#d9281c]">Sign in →</a>
</div>

<?php else: ?>

<div class="border border-[#2b2118] bg-white"><form method="post" class="px-5 py-5"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<p class="text-sm leading-6 text-[#7a6a58]">Enter the 6-digit code sent to your <?= $channel === 'sms' ? 'mobile number' : 'email' ?>, then pick a new password.</p>
<?php if (APP_DEBUG && is_string($devCode) && $devCode !== ''): ?><p class="mt-3 border-l-4 border-[#e8871e] bg-[#fdf3dd] px-3 py-2 font-mono text-lg font-bold tracking-[.35em] text-[#6b4a08]" id="devCode">dev code: <?=e($devCode)?></p><?php endif; ?>
<label class="mt-4 block text-[10px] font-bold uppercase tracking-[.15em]">6-digit code<input name="code" required inputmode="numeric" maxlength="6" pattern="[0-9]{6}" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-center font-mono text-xl tracking-[.4em] outline-none focus:border-[#2b2118]" placeholder="000000"></label>
<label class="mt-3 block text-[10px] font-bold uppercase tracking-[.15em]">New password<input name="password" type="password" required minlength="4" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]" placeholder="••••••••"></label>
<label class="mt-3 block text-[10px] font-bold uppercase tracking-[.15em]">Confirm new password<input name="password_confirm" type="password" required minlength="4" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]" placeholder="••••••••"></label>
<?php if($error): ?><p class="mt-3 border-l-2 border-[#d9281c] pl-2 text-sm text-[#d9281c]"><?=e($error)?></p><?php endif; ?>
<button class="mt-4 flex h-11 w-full items-center justify-center bg-[#2b2118] font-display text-xs font-bold uppercase tracking-[.12em] text-white hover:bg-[#d9281c]">Set new password →</button>
</form>
<p class="border-t border-[#2b2118] px-5 py-3 text-center text-[11px]"><a href="forgot_password.php" class="text-[#7a6a58] hover:text-[#d9281c]">Send a new code →</a></p></div>

<?php endif; ?>
</div></div>
<?php include __DIR__.'/includes/footer.php'; ?>
