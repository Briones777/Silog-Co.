<?php
require_once __DIR__ . '/includes/auth.php';
$currentAuthUser = current_user();
if ($currentAuthUser) {
    safe_redirect((($currentAuthUser['role'] ?? 'customer') === 'vendor') ? '/vendor.php' : '/order.php');
}
$returnTo = $_GET['returnTo'] ?? '/order.php';
if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) $returnTo = '/order.php';
$error = '';

/*
|--------------------------------------------------------------------------
| Sign in / register with email OR phone
|--------------------------------------------------------------------------
|
| There are no guest accounts: ordering requires an account. The same
| form signs in existing customers and registers new ones:
|
|   - Identifier unknown            -> account is created (name required).
|   - Account without a password    -> the password entered here claims
|                                      and secures it (legacy accounts).
|   - Account with a password       -> normal password sign-in.
|
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $identifier = trim($_POST['identifier'] ?? '');
    $password   = (string)($_POST['password'] ?? '');
    $name       = trim($_POST['name'] ?? '');

    $parsed = parse_login_identifier($identifier);

    if ($parsed['error'] !== '') {
        $error = $parsed['error'];
    } else {
        $mode = $parsed['mode'];
        $value = $parsed['value'];
    }

    if ($error === '' && strlen($password) < 4) {
        $error = 'Password must be at least 4 characters.';
    }

    if ($error === '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->execute([$value, $value]);
        $u = $stmt->fetch();

        if ($u && ($u['role'] ?? 'customer') === 'vendor') {
            $error = 'Use the vendor login page for this account.';
        } elseif (!$u) {
            /* New account. */
            if ($name === '') {
                $error = 'Tell us your name so the kitchen knows who is ordering.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    $mode === 'email'
                        ? "INSERT INTO users(name,email,phone,is_guest,role,password_hash) VALUES(?,?,NULL,0,'customer',?)"
                        : "INSERT INTO users(name,email,phone,is_guest,role,password_hash) VALUES(?,NULL,?,0,'customer',?)"
                );
                $stmt->execute([$name, $value, $hash]);
                $id = (int)$pdo->lastInsertId();
            }
        } else {
            $id = (int)$u['id'];
            $existingHash = (string)($u['password_hash'] ?? '');

            if ($existingHash === '') {
                /* Legacy account without a password: claim it with this one. */
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            } elseif (!password_verify($password, $existingHash)) {
                $error = 'Wrong password. Try again';
            }

            /* Fill in contact details / name when missing. */
            if ($error === '') {
                if ($mode === 'email' && empty($u['email'])) {
                    $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$value, $id]);
                } elseif ($mode === 'phone' && empty($u['phone'])) {
                    $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?")->execute([$value, $id]);
                }
                if ($name !== '' && in_array(($u['name'] ?? ''), ['', 'Customer', 'Guest'], true)) {
                    $pdo->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$name, $id]);
                }
            }
        }

        if ($error === '') {
            /*
             * Switching accounts must never inherit the previous
             * customer's ticket: wipe the device cart on next load.
             */
            setcookie('silog_cart_clear', '1', ['expires' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            $_SESSION['user_id'] = $id;
            session_regenerate_id(true);
            header('Location: ' . $returnTo);
            exit;
        }
    }
}
$pageTitle = 'Sign in';
include __DIR__ . '/includes/header.php';
?>
<div class="flex min-h-[calc(100vh-56px)] items-center justify-center px-4 py-14"><div class="w-full max-w-sm">
<div class="mb-6 border-b-4 border-[#2b2118] pb-4"><p class="text-[11px] font-semibold uppercase tracking-[.2em] text-[#d9281c]">00 — Access</p><h1 class="mt-2 font-display text-3xl font-extrabold uppercase leading-none">Sign in</h1></div>
<div class="border border-[#2b2118] bg-white"><form method="post" class="px-5 py-5"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<p class="text-sm leading-6 text-[#7a6a58]">Sign in with your <b>email or mobile number</b> to order. New here? The same form creates your account in one step.</p>
<label class="mt-4 block text-[10px] font-bold uppercase tracking-[.15em]">Email or mobile number<input name="identifier" required class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]" placeholder="you@example.com · 0917 123 4567" value="<?=e($_POST['identifier'] ?? '')?>"></label>
<label class="mt-3 block text-[10px] font-bold uppercase tracking-[.15em]">Name <span class="normal-case tracking-normal text-[#7a6a58]">(new accounts)</span><input name="name" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]" placeholder="Your name"></label>
<label class="mt-3 block text-[10px] font-bold uppercase tracking-[.15em]">Password<input name="password" type="password" required minlength="4" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]" placeholder="••••••••"></label>
<?php if($error): ?><p class="mt-3 border-l-2 border-[#d9281c] pl-2 text-sm text-[#d9281c]"><?=e($error)?></p><?php endif; ?>
<button class="mt-4 flex h-11 w-full items-center justify-center bg-[#2b2118] font-display text-xs font-bold uppercase tracking-[.12em] text-white hover:bg-[#d9281c]">Continue →</button>
<p class="mt-3 text-center text-[11px] leading-5 text-[#7a6a58]">First time signing in with an existing number or email? The password you pick here secures your account.</p>
</form><p class="mt-3 text-center text-xs"><a href="forgot_password.php" class="font-semibold text-[#7a6a58] hover:text-[#d9281c]">Forgot password?</a></p></div>
<p class="mt-4 text-center"><a href="vendor_login.php" class="text-[10px] font-bold uppercase tracking-[.12em] text-[#7a6a58] hover:text-[#d9281c]">Restaurant staff? Vendor login →</a></p>
</div></div>
<?php include __DIR__.'/includes/footer.php'; ?>
