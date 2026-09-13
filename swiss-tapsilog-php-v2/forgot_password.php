<?php
require_once __DIR__ . '/includes/auth.php';

$current = current_user();

if ($current) {
    safe_redirect((($current['role'] ?? 'customer') === 'vendor') ? '/vendor.php' : '/order.php');
}

$error = '';
$sent = false;
$maskedDestination = '';

/*
|--------------------------------------------------------------------------
| Step 1 — request a reset code
|--------------------------------------------------------------------------
|
| The confirmation is deliberately identical whether or not the account
| exists, so this page cannot be used to discover who has signed up.
| Throttling happens inside create_password_reset().
|
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $parsed = parse_login_identifier($_POST['identifier'] ?? '');

    if ($parsed['error'] !== '') {
        $error = $parsed['error'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->execute([$parsed['value'], $parsed['value']]);
        $u = $stmt->fetch();

        $sent = true; /* Same response either way. */

        if (
            $u &&
            (string)($u['password_hash'] ?? '') !== ''
        ) {
            $isVendor = ($u['role'] ?? 'customer') === 'vendor';

            /*
             * Vendors may only recover via email — a stolen SIM must not
             * take over the store. Customers may use either channel.
             */
            $channel = $parsed['mode'] === 'phone' ? 'sms' : 'email';
            $destination = $channel === 'sms'
                ? $parsed['value']
                : (string)($u['email'] !== '' ? $u['email'] : $parsed['value']);

            if (!$isVendor || $channel === 'email') {
                $code = create_password_reset((int)$u['id'], $channel, $destination);

                if ($code !== null) {
                    deliver_reset_code($channel, $destination, $code);
                }

                /* Masked preview for the confirmation message. */
                $maskedDestination = $channel === 'sms'
                    ? (preg_replace('/\d(?=\d{2})/', '•', $destination) ?? $destination)
                    : (str_contains($destination, '@')
                        ? str_repeat('•', max(3, (int)strpos($destination, '@') - 2)) . substr($destination, (int)strpos($destination, '@'))
                        : $destination);

                $_SESSION['reset_user_id'] = (int)$u['id'];
            }
        }
    }
}

$pageTitle = 'Forgot password';
include __DIR__ . '/includes/header.php';
?>
<div class="flex min-h-[calc(100vh-56px)] items-center justify-center px-4 py-14"><div class="w-full max-w-sm">
<div class="mb-6 border-b-4 border-[#2b2118] pb-4"><p class="text-[11px] font-semibold uppercase tracking-[.2em] text-[#d9281c]">00 — Access</p><h1 class="mt-2 font-display text-3xl font-extrabold uppercase leading-none">Forgot password</h1></div>

<?php if ($sent): ?>

<div class="border border-[#2b2118] bg-white p-5">
    <p class="text-sm leading-6 text-[#7a6a58]">If that email or number belongs to an account, a <b>6-digit code</b> is on its way
    <?= $maskedDestination !== '' ? 'to <b class="break-all">' . e($maskedDestination) . '</b>' : '' ?>. It expires in 10 minutes.</p>
    <form action="reset_password.php" method="get" class="mt-4">
        <button class="flex h-11 w-full items-center justify-center bg-[#2b2118] font-display text-xs font-bold uppercase tracking-[.12em] text-white hover:bg-[#d9281c]">I have the code →</button>
    </form>
    <p class="mt-3 text-center text-[11px] text-[#7a6a58]">Didn't get it? Check spam, or wait a minute and <a href="forgot_password.php" class="underline hover:no-underline">request a new code</a>.</p>
</div>

<?php else: ?>

<div class="border border-[#2b2118] bg-white"><form method="post" class="px-5 py-5"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<p class="text-sm leading-6 text-[#7a6a58]">Enter the email or mobile number you signed up with and we'll send a 6-digit reset code.</p>
<label class="mt-4 block text-[10px] font-bold uppercase tracking-[.15em]">Email or mobile number<input name="identifier" required class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]" placeholder="you@example.com · 0917 123 4567" value="<?=e($_POST['identifier'] ?? '')?>"></label>
<?php if($error): ?><p class="mt-3 border-l-2 border-[#d9281c] pl-2 text-sm text-[#d9281c]"><?=e($error)?></p><?php endif; ?>
<button class="mt-4 flex h-11 w-full items-center justify-center bg-[#2b2118] font-display text-xs font-bold uppercase tracking-[.12em] text-white hover:bg-[#d9281c]">Send reset code →</button>
</form>
<p class="border-t border-[#2b2118] px-5 py-3 text-center text-[11px]"><a href="auth.php" class="text-[#7a6a58] hover:text-[#d9281c]">← Back to sign in</a></p></div>

<?php endif; ?>
</div></div>
<?php include __DIR__.'/includes/footer.php'; ?>
