<?php
require_once __DIR__ . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| One-time vendor bootstrap
|--------------------------------------------------------------------------
|
| Sell-to-client flow: deploy the app, open /setup.php ONCE, create the
| owner's vendor login, done — the page locks itself permanently.
|
|   - Fresh install (no vendor account): create the first vendor.
|   - Demo install (vendor@silog.local): claim it — replace the demo
|     email and password with the client's own credentials.
|
| After setup, the flag `setup_complete` is stored and this page refuses
| everything. Remove this file from the server if you want belt and
| braces; the flag alone already locks it.
|
*/

$setupDone = app_setting('setup_complete', '') !== '';

$hasVendor = false;
$stmt = $pdo->query("SELECT id, email FROM users WHERE role = 'vendor' LIMIT 1");
$existingVendor = $stmt->fetch();
$hasVendor = $existingVendor !== false;

$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($setupDone) {
        /* Locked — ignore everything. */
        $error = 'Setup is already completed for this installation.';
    } else {
        verify_csrf();

        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        if ($name === '' || mb_strlen($name) > 120) {
            $error = 'Enter the business or owner name.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address for the vendor login.';
        } elseif (strlen($password) < 8) {
            $error = 'Use at least 8 characters for the vendor password.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $pdo->beginTransaction();

                if ($hasVendor) {
                    /* Claim the existing vendor row (demo hand-over). */
                    $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?")
                        ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), (int)$existingVendor['id']]);
                } else {
                    /* First vendor on a fresh install. */
                    $pdo->prepare("INSERT INTO users(name,email,phone,is_guest,role,password_hash) VALUES(?,?,NULL,0,'vendor',?)")
                        ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                }

                set_app_setting('setup_complete', date('c'));

                $pdo->commit();
                $done = true;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = str_contains($e->getMessage(), 'uq_users_phone') || str_contains($e->getMessage(), 'Duplicate')
                    ? 'That email is already used by another account.'
                    : 'Could not save the vendor account. Try again.';
            }
        }
    }
}

$pageTitle = 'Vendor setup';
include __DIR__ . '/includes/header.php';
?>
<div class="flex min-h-[calc(100vh-56px)] items-center justify-center px-4 py-14"><div class="w-full max-w-sm">
<div class="mb-6 border-b-4 border-[#2b2118] pb-4"><p class="text-[11px] font-semibold uppercase tracking-[.2em] text-[#d9281c]">Vendor access</p><h1 class="mt-2 font-display text-3xl font-extrabold uppercase leading-none"><?= $setupDone ? 'Setup complete' : 'First-time setup' ?></h1></div>

<?php if ($setupDone): ?>

<div class="border border-[#2b2118] bg-white p-5 text-center">
    <p class="text-2xl">🔒</p>
    <p class="mt-2 font-display text-lg font-extrabold uppercase">Already locked</p>
    <p class="mt-2 text-sm leading-6 text-[#7a6a58]">This installation has been set up. Use the vendor login page, or reset the password from there if credentials were lost.</p>
    <a href="vendor_login.php" class="mt-4 inline-block bg-[#2b2118] px-5 py-2.5 font-display text-[11px] font-bold uppercase tracking-[.12em] text-white hover:bg-[#d9281c]">Vendor login →</a>
</div>

<?php elseif ($done): ?>

<div class="border border-[#2b2118] bg-white p-5 text-center">
    <p class="text-2xl">✅</p>
    <p class="mt-2 font-display text-lg font-extrabold uppercase">Vendor account created</p>
    <p class="mt-2 text-sm leading-6 text-[#7a6a58]">Sign in at the vendor desk with the email and password you just chose. This setup page is now permanently locked.</p>
    <a href="vendor_login.php" class="mt-4 inline-block bg-[#2b2118] px-5 py-2.5 font-display text-[11px] font-bold uppercase tracking-[.12em] text-white hover:bg-[#d9281c]">Go to vendor login →</a>
</div>

<?php else: ?>

<div class="border border-[#2b2118] bg-white"><form method="post" class="px-5 py-5"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<p class="text-sm leading-6 text-[#7a6a58]">Create the owner's vendor login for this installation.
<?= $hasVendor ? 'The built-in demo vendor exists — its email and password will be <b>replaced</b> by what you enter here.' : 'No vendor exists yet — this creates the first one.' ?></p>
<label class="mt-4 block text-[10px] font-bold uppercase tracking-[.15em]">Business / owner name<input name="name" required maxlength="120" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]" placeholder="e.g. Swiss Tapsilog" value="<?=e($_POST['name'] ?? '')?>"></label>
<label class="mt-3 block text-[10px] font-bold uppercase tracking-[.15em]">Vendor login email<input name="email" type="email" required class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]" placeholder="owner@business.com" value="<?=e($_POST['email'] ?? ($existingVendor['email'] ?? ''))?>"></label>
<label class="mt-3 block text-[10px] font-bold uppercase tracking-[.15em]">Password <span class="normal-case tracking-normal text-[#7a6a58]">(min 8 characters)</span><input name="password" type="password" required minlength="8" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]" placeholder="••••••••"></label>
<label class="mt-3 block text-[10px] font-bold uppercase tracking-[.15em]">Confirm password<input name="password_confirm" type="password" required minlength="8" class="mt-1 w-full border border-[#e8ddc9] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#2b2118]" placeholder="••••••••"></label>
<?php if($error): ?><p class="mt-3 border-l-2 border-[#d9281c] pl-2 text-sm text-[#d9281c]"><?=e($error)?></p><?php endif; ?>
<button class="mt-4 flex h-11 w-full items-center justify-center bg-[#2b2118] font-display text-xs font-bold uppercase tracking-[.12em] text-white hover:bg-[#d9281c]">Create vendor login →</button>
<p class="mt-3 text-center text-[11px] text-[#7a6a58]">This page locks itself after setup and can only be used once.</p>
</form></div>

<?php endif; ?>
</div></div>
<?php include __DIR__.'/includes/footer.php'; ?>
