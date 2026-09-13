<?php

require_once __DIR__ . '/../includes/auth.php';

require_vendor();
verify_csrf();

/*
|--------------------------------------------------------------------------
| Menu Item Management (add / update / delete + image upload)
|--------------------------------------------------------------------------
|
| Vendor-side CRUD for menu_items. Uploaded photos are validated by real
| image content, given randomized names and stored in assets/uploads/.
| Only the stored filename is written to the database.
|
*/

const MENU_UPLOAD_DIR = __DIR__ . '/../assets/uploads';

function menu_upload_failure(string $message): never
{
    flash($message, 'error');
    header('Location: ../vendor.php?tab=menu');
    exit;
}

/**
 * Validate and move an uploaded photo. Returns the stored filename,
 * or null when no file was chosen.
 */
function store_menu_upload(?array $file, string $context): ?string
{
    if (
        $file === null ||
        !is_array($file) ||
        (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        menu_upload_failure($context . ' upload failed. Please try again.');
    }

    if ((int)$file['size'] <= 0 || (int)$file['size'] > 3 * 1024 * 1024) {
        menu_upload_failure($context . ' must be an image of 3 MB or less.');
    }

    $info = @getimagesize($file['tmp_name']);

    if (
        $info === false ||
        !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)
    ) {
        menu_upload_failure($context . ' must be a JPG, PNG, GIF or WebP image.');
    }

    if (!is_dir(MENU_UPLOAD_DIR) && !mkdir(MENU_UPLOAD_DIR, 0775, true) && !is_dir(MENU_UPLOAD_DIR)) {
        menu_upload_failure('Could not create the upload folder on the server.');
    }

    $ext = image_type_to_extension($info[2], false); // jpeg, png, gif, webp

    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], MENU_UPLOAD_DIR . '/' . $name)) {
        menu_upload_failure('Could not save the ' . strtolower($context) . ' image.');
    }

    return 'assets/uploads/' . $name;
}

/**
 * Delete an uploaded file previously managed through this action.
 */
function delete_menu_upload(?string $url): void
{
    if (
        !is_string($url) ||
        $url === '' ||
        !str_starts_with($url, 'assets/uploads/')
    ) {
        return;
    }

    $path = basename($url); // no traversal: only the basename is used

    $full = realpath(MENU_UPLOAD_DIR . '/' . $path);

    $dir = realpath(MENU_UPLOAD_DIR);

    if (
        $full !== false &&
        $dir !== false &&
        str_starts_with($full, $dir . DIRECTORY_SEPARATOR)
    ) {
        @unlink($full);
    }
}

try {

    $action = $_POST['action'] ?? '';

    /*
    |----------------------------------------------------------------------
    | Add new item
    |----------------------------------------------------------------------
    */
    if ($action === 'add') {

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);

        if ($name === '' || $description === '' || $category === '') {
            menu_upload_failure('Item name, description and category are required.');
        }

        if ($price === false || $price <= 0 || $price > 100000) {
            menu_upload_failure('Please enter a valid price.');
        }

        $image = store_menu_upload($_FILES['image'] ?? null, 'Item photo');

        $stmt = $pdo->prepare("
            INSERT INTO menu_items
                (name, description, price, category, image_url, available, sort_order)
            VALUES
                (?, ?, ?, ?, ?, 1, 0)
        ");
        $stmt->execute([$name, $description, $price, $category, $image]);

        flash('Menu item "' . $name . '" added.');

    /*
    |----------------------------------------------------------------------
    | Update existing item
    |----------------------------------------------------------------------
    */
    } elseif ($action === 'update') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            menu_upload_failure('Invalid menu item.');
        }

        $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            menu_upload_failure('Menu item not found.');
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);

        if ($name === '' || $description === '' || $category === '') {
            menu_upload_failure('Item name, description and category are required.');
        }

        if ($price === false || $price <= 0 || $price > 100000) {
            menu_upload_failure('Please enter a valid price.');
        }

        $image = store_menu_upload($_FILES['image'] ?? null, 'Item photo');

        if ($image !== null) {
            delete_menu_upload($item['image_url'] ?? null);
        }

        $stmt = $pdo->prepare("
            UPDATE menu_items
            SET name = ?,
                description = ?,
                price = ?,
                category = ?,
                image_url = COALESCE(?, image_url)
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $price, $category, $image, $id]);

        flash('Menu item "' . $name . '" updated.');

    /*
    |----------------------------------------------------------------------
    | Delete item (with photo cleanup)
    |----------------------------------------------------------------------
    */
    } elseif ($action === 'delete') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            menu_upload_failure('Invalid menu item.');
        }

        $stmt = $pdo->prepare("SELECT image_url FROM menu_items WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            menu_upload_failure('Menu item not found.');
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
            $stmt->execute([$id]);
        } catch (PDOException $e) {
            // fk_items_menu is ON DELETE RESTRICT: the item was ordered before.
            menu_upload_failure('This item is part of existing orders and cannot be deleted. Hide it instead.');
        }

        /* Favorites cascade via FK, so nothing extra to clean up there. */
        delete_menu_upload($item['image_url'] ?? null);

        flash('Menu item deleted.');

    /*
    |----------------------------------------------------------------------
    | Cover photo upload (featured item shown big on customer pages)
    |----------------------------------------------------------------------
    */
    } elseif ($action === 'set_cover') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            menu_upload_failure('Invalid menu item.');
        }

        $stmt = $pdo->prepare("SELECT id, image_url FROM menu_items WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            menu_upload_failure('Menu item not found.');
        }

        $cover = store_menu_upload($_FILES['cover'] ?? null, 'Cover photo');

        if ($cover === null) {
            menu_upload_failure('Choose a cover photo to upload.');
        }

        $stmt = $pdo->prepare("UPDATE menu_items SET cover_url = ? WHERE id = ?");
        $stmt->execute([$cover, $id]);

        flash('Cover photo updated. It now shows on the customer menu.');

    } else {

        menu_upload_failure('Invalid menu action.');
    }

} catch (Throwable $e) {

    error_log('vendor_menu_item error: ' . $e->getMessage());
    menu_upload_failure('Something went wrong. Please try again.');
}

header('Location: ../vendor.php?tab=menu');
exit;
