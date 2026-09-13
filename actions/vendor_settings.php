<?php

require_once __DIR__ . '/../includes/auth.php';

require_vendor();
verify_csrf();

const SETTINGS_UPLOAD_DIR = __DIR__ . '/../assets/uploads';

$returnUrl = '../vendor.php?tab=dashboard';

try {

    $number = trim($_POST['gcash_number'] ?? '');
    $name   = trim($_POST['gcash_name'] ?? '');

    if ($number !== '') {

        $digits = preg_replace('/\D/', '', $number);

        if (strlen($digits) < 10 || strlen($digits) > 13) {
            throw new RuntimeException('GCash number must be 10–13 digits.');
        }

        set_app_setting('gcash_number', $number);
    }

    if ($name !== '') {
        set_app_setting('gcash_name', mb_substr($name, 0, 80));
    }

    /*
    |----------------------------------------------------------------------
    | Store hours + order taking
    |----------------------------------------------------------------------
    */
    $open  = trim($_POST['store_open'] ?? '');
    $close = trim($_POST['store_close'] ?? '');

    foreach ([$open, $close] as $t) {
        if ($t !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)) {
            throw new RuntimeException('Store hours must be valid 24-hour times (HH:MM).');
        }
    }

    set_app_setting('store_open', $open !== '' ? $open : '06:00');
    set_app_setting('store_close', $close !== '' ? $close : '14:00');
    set_app_setting('orders_paused', isset($_POST['orders_paused']) ? '1' : '0');

    /*
    |----------------------------------------------------------------------
    | Optional QR image upload
    |----------------------------------------------------------------------
    */
    $file = $_FILES['gcash_qr'] ?? null;

    if (
        is_array($file) &&
        (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ) {

        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('QR image upload failed. Please try again.');
        }

        if ((int)$file['size'] <= 0) {
            throw new RuntimeException('QR image file is empty. Please choose a valid image.');
        }

        if ((int)$file['size'] > 3 * 1024 * 1024) {
            throw new RuntimeException('QR image must be 3 MB or less.');
        }

        $info = @getimagesize($file['tmp_name']);

        if (
            $info === false ||
            !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)
        ) {
            throw new RuntimeException('QR image must be a JPG, PNG, GIF or WebP file.');
        }

        if (!is_dir(SETTINGS_UPLOAD_DIR) && !mkdir(SETTINGS_UPLOAD_DIR, 0775, true) && !is_dir(SETTINGS_UPLOAD_DIR)) {
            throw new RuntimeException('Could not create the upload folder on the server.');
        }

        $ext = image_type_to_extension($info[2], false);

        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], SETTINGS_UPLOAD_DIR . '/' . $name)) {
            throw new RuntimeException('Could not save the QR image.');
        }

        /* Replace any previous QR file. */
        $old = app_setting('gcash_qr', '');

        if (
            $old !== '' &&
            str_starts_with($old, 'assets/uploads/')
        ) {
            $oldFull = realpath(SETTINGS_UPLOAD_DIR . '/' . basename($old));
            $dir = realpath(SETTINGS_UPLOAD_DIR);

            if (
                $oldFull !== false &&
                $dir !== false &&
                str_starts_with($oldFull, $dir . DIRECTORY_SEPARATOR)
            ) {
                @unlink($oldFull);
            }
        }

        set_app_setting('gcash_qr', 'assets/uploads/' . $name);
    }

    flash('GCash payment settings saved.');

} catch (Throwable $e) {

    flash($e->getMessage(), 'error');
}

header('Location: ' . $returnUrl);
exit;
