<?php require_once __DIR__ . '/includes/auth.php';
if (current_user() && current_user()['role'] === 'vendor') {
    header('Location: vendor.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $s = $pdo->prepare("SELECT * FROM users WHERE email=? AND role='vendor' LIMIT 1");
    $s->execute([$email]);
    $u = $s->fetch();
    if ($u && $u['password_hash'] && password_verify($password, $u['password_hash'])) {
        /*
         * customer session on top of this vendor login.
         */
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $u['id'];
        /*
         * Vendors never inherit a customer's unfinished ticket left in
         * this browser.
         */
        setcookie('silog_cart_clear', '1', ['expires' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        header('Location: vendor.php');
        exit;
    }
    $error = 'Invalid vendor credentials.';
}
$pageTitle = 'Vendor Login';
include __DIR__ . '/includes/header.php'; ?>
<div class="flex min-h-[calc(100vh-110px)] items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
        <div class="mb-6 border-b-4 border-[#2b2118] pb-4">
            <p class="text-[11px] font-semibold uppercase tracking-[.2em] text-[#d9281c]">VENDOR ACCESS</p>
            <h1 class="mt-2 font-display text-3xl font-extrabold uppercase">Kitchen desk</h1>
        </div>
        <form method="post" class="border border-[#2b2118] bg-white p-5"><input type="hidden" name="csrf"
                value="<?= e(csrf_token()) ?>"><label
                class="block text-[10px] font-bold uppercase tracking-[.15em]">Email<input name="email" type="email"
                    required class="mt-1 w-full border border-[#e8ddc9] px-3 py-2.5 text-sm"
                    value="vendor@silog.local"></label><label
                class="mt-3 block text-[10px] font-bold uppercase tracking-[.15em]">Password<input name="password"
                    type="password" required
                    class="mt-1 w-full border border-[#e8ddc9] px-3 py-2.5 text-sm"></label><?php if ($error): ?>
                <p class="mt-3 border-l-2 border-[#d9281c] pl-2 text-sm text-[#d9281c]"><?= e($error) ?></p>
            <?php endif; ?><button type="submit"
                class="mt-4 h-11 w-full bg-[#2b2118] font-display text-xs font-bold uppercase tracking-[.12em] text-white hover:bg-[#d9281c]">Enter
                vendor desk →</button>
        </form>
        <p class="mt-3 text-center text-[11px]"><a href="forgot_password.php" class="text-[#7a6a58] hover:text-[#d9281c]">Forgot vendor password?</a></p>
        <?php if (app_setting('setup_complete', '') === ''): ?><p class="mt-3 text-center text-[11px] text-[#7a6a58]">Default local demo: vendor@silog.local / vendor123 · <a href="setup.php" class="font-semibold text-[#e8871e] hover:underline">First-time setup</a></p><?php endif; ?>
    </div>
</div><?php include __DIR__ . '/includes/footer.php'; ?>