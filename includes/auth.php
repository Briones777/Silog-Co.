<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Swiss Tapsilog
| Shared Authentication / Security Helpers
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

const DELIVERY_FEE = 45.00;

const PRIVACY_NOTICE_VERSION = '1.0';
const TERMS_VERSION = '1.0';

/*
|--------------------------------------------------------------------------
| GCash Payment (manual verification)
|--------------------------------------------------------------------------
*/

const GCASH_NUMBER = '0917 123 4567';

const GCASH_NAME = 'Silog & Co.';

/**
 * Read one editable store setting, falling back to the code default.
 */
function app_setting(string $key, string $fallback = ''): string
{
    global $pdo;

    try {

        $stmt = $pdo->prepare("SELECT v FROM app_settings WHERE k = ? LIMIT 1");
        $stmt->execute([$key]);

        $v = $stmt->fetchColumn();

        return ($v === false || $v === null || $v === '')
            ? $fallback
            : (string)$v;

    } catch (Throwable $e) {

        return $fallback;
    }
}

/**
 * Persist one editable store setting.
 */
function set_app_setting(string $key, string $value): bool
{
    global $pdo;

    try {

        $stmt = $pdo->prepare("
            INSERT INTO app_settings (k, v) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE v = VALUES(v)
        ");

        return $stmt->execute([$key, $value]);

    } catch (Throwable $e) {

        error_log('set_app_setting() error: ' . $e->getMessage());

        return false;
    }
}

/**
 * Effective GCash receiving details shown at checkout.
 *
 * @return array{number: string, name: string, qr: ?string}
 */
function gcash_settings(): array
{
    return [
        'number' => app_setting('gcash_number', GCASH_NUMBER),
        'name'   => app_setting('gcash_name', GCASH_NAME),
        'qr'     => app_setting('gcash_qr', ''),
    ];
}

function payment_method_label(string $method): string
{
    return strtolower(trim($method)) === 'gcash' ? 'GCash' : 'Cash';
}


/*
|--------------------------------------------------------------------------
| Session Security
|--------------------------------------------------------------------------
|
| These settings must be configured before session_start() in a fresh
| production deployment. We keep the existing session architecture here
| to avoid breaking the current application.
|
*/


/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    if (
        empty($_SESSION['csrf']) ||
        !is_string($_SESSION['csrf'])
    ) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}


function verify_csrf(): void
{
    $sessionToken = $_SESSION['csrf'] ?? '';
    $requestToken = $_POST['csrf'] ?? '';

    if (
        !is_string($sessionToken) ||
        !is_string($requestToken) ||
        $sessionToken === '' ||
        $requestToken === '' ||
        !hash_equals($sessionToken, $requestToken)
    ) {
        http_response_code(419);

        exit('Invalid request token.');
    }
}


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

function current_user(): ?array
{
    global $pdo;

    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                email,
                role,
                phone,
                created_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$userId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            unset($_SESSION['user_id']);

            return null;
        }

        return $user;

    } catch (Throwable $e) {

        error_log(
            'current_user() error: ' . $e->getMessage()
        );

        return null;
    }
}


/*
|--------------------------------------------------------------------------
| Customer Authentication
|--------------------------------------------------------------------------
*/

function require_login(): void
{
    if (current_user() !== null) {
        return;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '/order.php';

    /*
     * Prevent an external redirect.
     */
    if (
        !is_string($requestUri) ||
        $requestUri === '' ||
        !str_starts_with($requestUri, '/') ||
        str_starts_with($requestUri, '//')
    ) {
        $requestUri = '/order.php';
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';

    $base = rtrim(
        str_replace(
            '\\',
            '/',
            dirname(dirname($script))
        ),
        '/'
    );

    if ($base === '.' || $base === '/') {
        $base = '';
    }

    header(
        'Location: ' .
        $base .
        '/auth.php?returnTo=' .
        urlencode($requestUri)
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Vendor Authentication
|--------------------------------------------------------------------------
*/

function require_vendor(): void
{
    $user = current_user();

    if ($user === null) {
        header('Location: vendor_login.php');

        exit;
    }

    if (($user['role'] ?? 'customer') !== 'vendor') {
        http_response_code(403);

        exit('Vendor access required.');
    }
}


/*
|--------------------------------------------------------------------------
| Login / Session Helpers
|--------------------------------------------------------------------------
*/

function login_user(int $userId): void
{
    if ($userId <= 0) {
        throw new InvalidArgumentException(
            'Invalid user ID.'
        );
    }

    /*
     * Prevent session fixation.
     */
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;

    /*
     * Generate a fresh CSRF token for the authenticated session.
     */
    $_SESSION['csrf'] = bin2hex(random_bytes(32));

    /*
     * Clear temporary authentication state.
     */
    unset(
        $_SESSION['auth_error'],
        $_SESSION['auth_email']
    );
}


function logout_user(): void
{
    /*
     * Remove all application session data.
     */
    $_SESSION = [];

    /*
     * Remove the session cookie.
     */
    if (
        ini_get('session.use_cookies')
    ) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool)$params['secure'],
            (bool)$params['httponly']
        );
    }

    session_destroy();
}


/*
|--------------------------------------------------------------------------
| Customer Favorites
|--------------------------------------------------------------------------
|
| Signed-in customers can heart menu items and re-order their usuals
| with one tap. There are no guest accounts: ordering requires an
| account created with an email address or phone number.
|
*/

/**
 * Menu item ids the given user has favorited.
 *
 * @return array<int, int>
 */
function favorite_ids(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT menu_item_id FROM favorites WHERE user_id = ?');
        $stmt->execute([$userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {

        error_log('favorite_ids() error: ' . $e->getMessage());

        return [];
    }
}

/**
 * Favorite or unfavorite a menu item.
 *
 * Returns true when the item is favorited after the call.
 */
function toggle_favorite(int $userId, int $menuItemId): bool
{
    if ($userId <= 0 || $menuItemId <= 0) {
        return false;
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM favorites WHERE user_id = ? AND menu_item_id = ?'
        );
        $stmt->execute([$userId, $menuItemId]);

        if ($stmt->fetchColumn()) {
            $pdo->prepare(
                'DELETE FROM favorites WHERE user_id = ? AND menu_item_id = ?'
            )->execute([$userId, $menuItemId]);

            return false;
        }

        $pdo->prepare(
            'INSERT IGNORE INTO favorites (user_id, menu_item_id) VALUES (?, ?)'
        )->execute([$userId, $menuItemId]);

        return true;

    } catch (Throwable $e) {

        error_log('toggle_favorite() error: ' . $e->getMessage());

        return false;
    }
}

/**
 * Full menu rows for the user's favorites, joined so availability and
 * photos come along for the re-order flow.
 *
 * @return array<int, array<string, mixed>>
 */
function fetch_favorite_items(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT m.*
            FROM favorites f
            JOIN menu_items m ON m.id = f.menu_item_id
            WHERE f.user_id = ?
            ORDER BY m.sort_order, m.name
        ");
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {

        error_log('fetch_favorite_items() error: ' . $e->getMessage());

        return [];
    }
}


/*
|--------------------------------------------------------------------------
| Login Identifier Parsing
|--------------------------------------------------------------------------
*/

/**
 * Normalize a customer sign-in identifier (email or mobile number).
 *
 * @return array{mode: 'email'|'phone'|null, value: string, error: string}
 */
function parse_login_identifier(string $identifier): array
{
    $identifier = trim($identifier);

    if ($identifier === '') {
        return ['mode' => null, 'value' => '', 'error' => 'Enter your email address or mobile number.'];
    }

    if (str_contains($identifier, '@')) {
        if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return ['mode' => null, 'value' => '', 'error' => 'Please enter a valid email address.'];
        }

        return ['mode' => 'email', 'value' => strtolower($identifier), 'error' => ''];
    }

    $digits = preg_replace('/\D/', '', $identifier) ?? '';

    if (strlen($digits) < 10 || strlen($digits) > 13) {
        return ['mode' => null, 'value' => '', 'error' => 'Enter a valid mobile number (10–13 digits) or an email address.'];
    }

    return ['mode' => 'phone', 'value' => $digits, 'error' => ''];
}


/*
|--------------------------------------------------------------------------
| Password Reset (email / SMS one-time codes)
|--------------------------------------------------------------------------
|
| Six-digit one-time codes, hashed at rest, expiring in 10 minutes,
| capped at 5 verification attempts, and replaced (never stacked) so
| only the newest code is ever valid. Flip APP_DEBUG to false in
| production and plug a real email/SMS provider into
| deliver_reset_code().
|
*/

const APP_DEBUG = true;

/**
 * Is the store accepting orders right now?
 *
 * Combines the vendor's configured hours (default 06:00–14:00) with
 * the manual pause toggle. Handles overnight windows (close < open).
 */
function accepts_orders(): bool
{
    if (app_setting('orders_paused', '0') === '1') {
        return false;
    }

    $open = app_setting('store_open', '06:00');
    $close = app_setting('store_close', '14:00');

    $open = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $open) ? $open : '06:00';
    $close = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $close) ? $close : '14:00';

    $now = (int)date('Hi');
    $o = (int)str_replace(':', '', $open);
    $c = (int)str_replace(':', '', $close);

    if ($o === $c) {
        return true; /* Same time = 24 hours. */
    }

    if ($o < $c) {
        return $now >= $o && $now < $c;
    }

    return $now >= $o || $now < $c; /* Overnight window. */
}

/**
 * Store-hours summary for display.
 *
 * @return array{open: string, close: string, paused: bool, open_now: bool}
 */
function store_hours(): array
{
    return [
        'open' => app_setting('store_open', '06:00'),
        'close' => app_setting('store_close', '14:00'),
        'paused' => app_setting('orders_paused', '0') === '1',
        'open_now' => accepts_orders(),
    ];
}

const RESET_CODE_TTL_MINUTES = 10;

const RESET_MAX_ATTEMPTS = 5;

const RESET_THROTTLE_SECONDS = 60;

/**
 * Create (or replace) the outstanding reset code for an account.
 * Returns the plain 6-digit code for delivery, or null when throttled.
 */
function create_password_reset(int $userId, string $channel, string $destination): ?string
{
    if ($userId <= 0 || !in_array($channel, ['email', 'sms'], true) || $destination === '') {
        return null;
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT created_at FROM password_resets WHERE user_id = ?');
        $stmt->execute([$userId]);
        $existing = $stmt->fetchColumn();

        if ($existing !== false && $existing !== null) {
            $last = strtotime((string)$existing);

            if ($last !== false && (time() - $last) < RESET_THROTTLE_SECONDS) {
                return null;
            }
        }

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);

        $pdo->prepare('
            INSERT INTO password_resets(user_id, code_hash, channel, destination, expires_at)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
        ')->execute([
            $userId,
            hash('sha256', $code),
            $channel,
            $destination,
            RESET_CODE_TTL_MINUTES,
        ]);

        return $code;

    } catch (Throwable $e) {

        error_log('create_password_reset() error: ' . $e->getMessage());

        return null;
    }
}

/**
 * The live (unexpired, unconsumed) reset row for an account, if any.
 */
function live_password_reset(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare('
            SELECT user_id, code_hash, channel, destination, attempts, expires_at
            FROM password_resets
            WHERE user_id = ?
              AND used_at IS NULL
              AND expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([$userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;

    } catch (Throwable $e) {

        error_log('live_password_reset() error: ' . $e->getMessage());

        return null;
    }
}

/**
 * Verify a submitted code against the outstanding reset. Every check
 * burns an attempt so guessing is capped at RESET_MAX_ATTEMPTS.
 */
function verify_reset_code(int $userId, string $code): bool
{
    $reset = live_password_reset($userId);

    if ($reset === null || (int)$reset['attempts'] >= RESET_MAX_ATTEMPTS) {
        return false;
    }

    global $pdo;

    $pdo->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE user_id = ?')
        ->execute([$userId]);

    return hash_equals((string)$reset['code_hash'], hash('sha256', $code));
}

/**
 * Consume the reset and set the new password.
 */
function complete_password_reset(int $userId, string $newPassword): bool
{
    global $pdo;

    try {
        $pdo->beginTransaction();

        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ?')
            ->execute([$userId]);

        $pdo->commit();

        return true;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('complete_password_reset() error: ' . $e->getMessage());

        return false;
    }
}

/**
 * Deliver a reset code to the customer.
 *
 * While APP_DEBUG is on the code is mirrored into the session (shown
 * on the reset page) and the error log so local testing can finish.
 * Before production: set APP_DEBUG = false and replace the TODOs with
 * a real email provider (Resend, PHPMailer, ...) and SMS gateway
 * (Twilio, Semaphore, ...).
 */
function deliver_reset_code(string $channel, string $destination, string $code): void
{
    if (APP_DEBUG) {
        $_SESSION['debug_reset_code'] = $code;
        error_log("[dev] reset code for {$destination}: {$code}");
        return;
    }

    if ($channel === 'sms') {
        // TODO: SMS gateway integration (Twilio / Semaphore).
        error_log("sms to {$destination}: reset code {$code}");
        return;
    }

    // TODO: email provider integration (Resend / PHPMailer).
    error_log("mail to {$destination}: reset code {$code}");
}


/*
|--------------------------------------------------------------------------
| Privacy Consent
|--------------------------------------------------------------------------
*/

/**
 * Record a privacy consent event.
 *
 * This creates a historical record instead of overwriting previous
 * consent decisions.
 */
function record_privacy_consent(
    int $userId,
    string $consentType,
    bool $granted,
    ?string $version = null
): bool {
    global $pdo;

    if ($userId <= 0) {
        return false;
    }

    $allowedTypes = [
        'privacy_notice',
        'terms',
        'analytics',
        'marketing',
    ];

    if (!in_array($consentType, $allowedTypes, true)) {
        return false;
    }

    if ($version === null || $version === '') {
        $version = match ($consentType) {
            'terms' => TERMS_VERSION,
            default => PRIVACY_NOTICE_VERSION,
        };
    }

    try {

        $stmt = $pdo->prepare("
            INSERT INTO privacy_consents
            (
                user_id,
                consent_type,
                consent_version,
                granted,
                granted_at,
                withdrawn_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                CASE
                    WHEN ? = 1 THEN NOW()
                    ELSE NULL
                END,
                CASE
                    WHEN ? = 1 THEN NULL
                    ELSE NOW()
                END
            )
        ");

        $value = $granted ? 1 : 0;

        return $stmt->execute([
            $userId,
            $consentType,
            $version,
            $value,
            $value,
            $value,
        ]);

    } catch (Throwable $e) {

        error_log(
            'record_privacy_consent() error: ' .
            $e->getMessage()
        );

        return false;
    }
}


/**
 * Get the latest recorded consent decision for a user.
 */
function get_privacy_consent(
    int $userId,
    string $consentType
): ?array {
    global $pdo;

    if ($userId <= 0) {
        return null;
    }

    $allowedTypes = [
        'privacy_notice',
        'terms',
        'analytics',
        'marketing',
    ];

    if (!in_array($consentType, $allowedTypes, true)) {
        return null;
    }

    try {

        $stmt = $pdo->prepare("
            SELECT
                id,
                user_id,
                consent_type,
                consent_version,
                granted,
                granted_at,
                withdrawn_at,
                created_at
            FROM privacy_consents
            WHERE user_id = ?
              AND consent_type = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            $userId,
            $consentType
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;

    } catch (Throwable $e) {

        error_log(
            'get_privacy_consent() error: ' .
            $e->getMessage()
        );

        return null;
    }
}


/**
 * Determine whether the user currently has a granted consent.
 */
function has_privacy_consent(
    int $userId,
    string $consentType
): bool {
    $consent = get_privacy_consent(
        $userId,
        $consentType
    );

    return $consent !== null
        && (int)$consent['granted'] === 1;
}


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

function flash(
    ?string $message = null,
    string $type = 'success'
): ?array {

    /*
     * Store message.
     */
    if ($message !== null) {

        $allowedTypes = [
            'success',
            'error',
            'warning',
            'info',
        ];

        if (!in_array($type, $allowedTypes, true)) {
            $type = 'info';
        }

        $_SESSION['flash'] = [
            'message' => $message,
            'type' => $type,
        ];

        return null;
    }

    /*
     * Retrieve and immediately remove message.
     */
    $flash = $_SESSION['flash'] ?? null;

    unset($_SESSION['flash']);

    return is_array($flash)
        ? $flash
        : null;
}


/*
|--------------------------------------------------------------------------
| Output Escaping
|--------------------------------------------------------------------------
*/

function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

/**
 * Current page number (1-based) from the request.
 */
function request_page(): int
{
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

    if ($page === false || $page < 1) {
        $page = 1;
    }

    return min($page, 1000);
}

/**
 * Build a same-tab URL with a different page number.
 */
function page_url(int $page, array $extraParams = []): string
{
    $params = array_merge($_GET, $extraParams);
    $params['page'] = $page;

    $qs = http_build_query($params);

    return basename((string)($_SERVER['PHP_SELF'] ?? '')) . ($qs === '' ? '' : '?' . $qs);
}

/**
 * Windowed page numbers like 1 … 4 5 6 … 12.
 *
 * @return list<int|string>
 */
function page_window(int $page, int $pages, int $radius = 1): array
{
    if ($pages <= 7) {
        return range(1, max(1, $pages));
    }

    $window = [1];

    $start = max(2, $page - $radius);
    $end = min($pages - 1, $page + $radius);

    if ($start > 2) {
        $window[] = '…';
    }

    for ($i = $start; $i <= $end; $i++) {
        $window[] = $i;
    }

    if ($end < $pages - 1) {
        $window[] = '…';
    }

    $window[] = $pages;

    return $window;
}

/**
 * Render the pagination control (styled to the current warm palette).
 */
function render_pagination(int $page, int $pages, array $extraParams = []): string
{
    if ($pages <= 1) {
        return '';
    }

    $link = static fn($p): string => e(page_url((int)$p, $extraParams));

    $html = '<nav class="mt-6 flex flex-wrap items-center justify-center gap-1.5" aria-label="Pagination">';

    if ($page > 1) {
        $html .= '<a href="' . $link($page - 1) . '" class="border border-[#e8ddc9] bg-white px-3 py-2 text-[10px] font-bold uppercase tracking-[.12em] text-[#4a3a28] hover:border-[#2b2118]">&larr; Prev</a>';
    }

    foreach (page_window($page, $pages) as $p) {
        if ($p === '…') {
            $html .= '<span class="px-2 py-2 text-xs text-[#8a7a64]">…</span>';
            continue;
        }

        if ((int)$p === $page) {
            $html .= '<span aria-current="page" class="border border-[#2b2118] bg-[#2b2118] px-3 py-2 text-[10px] font-bold uppercase tracking-[.12em] text-white">' . (int)$p . '</span>';
        } else {
            $html .= '<a href="' . $link((int)$p) . '" class="border border-[#e8ddc9] bg-white px-3 py-2 text-[10px] font-bold uppercase tracking-[.12em] text-[#4a3a28] hover:border-[#2b2118]">' . (int)$p . '</a>';
        }
    }

    if ($page < $pages) {
        $html .= '<a href="' . $link($page + 1) . '" class="border border-[#e8ddc9] bg-white px-3 1px-0 text-[10px] font-bold uppercase tracking-[.12em] text-[#4a3a28] hover:border-[#2b2118]">Next &rarr;</a>';
    }

    $html .= '</nav>';

    $html .= '<p class="mt-3 text-center text-[11px] text-[#8a7a64]">Page ' . $page . ' of ' . $pages . '</p>';

    return $html;
}

/**
 * Fetch one page of orders plus total page count.
 *
 * Pass $userId > 0 to scope to one customer, or 0/null for all orders
 * (vendor view). $finished: true = delivered/cancelled only, false =
 * live orders only, null = everything. $payment: 'gcash', 'cash',
 * 'gcash_unverified' or null for all.
 *
 * @return array{orders: list<array>, pages: int, total: int, page: int}
 */
function fetch_orders_page(
    int $userId,
    int $page,
    int $perPage = 15,
    ?bool $finished = null,
    ?string $payment = null
): array {
    $where = [];
    $params = [];

    if ($userId > 0) {
        $where[] = 'user_id = ?';
        $params[] = $userId;
    }

    if ($finished === true) {
        $where[] = "status IN ('delivered','cancelled')";
    } elseif ($finished === false) {
        $where[] = "status NOT IN ('delivered','cancelled')";
    }

    if ($payment === 'gcash_unverified') {
        $where[] = "payment_method = 'gcash' AND payment_verified_at IS NULL";
    } elseif ($payment === 'gcash') {
        $where[] = "payment_method = 'gcash'";
    } elseif ($payment === 'cash') {
        $where[] = "payment_method = 'cash'";
    }

    $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);

    global $pdo;

    try {

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $whereSql");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);

        /* LIMIT/OFFSET are ints we computed ourselves; inline them to
         * avoid native-prepare binding quirks across PDO drivers. */
        $offset = max(0, ($page - 1) * $perPage);

        $stmt = $pdo->prepare("
            SELECT id, customer_name, phone, address, notes,
                   subtotal, delivery_fee, total, status,
                   payment_method, payment_ref, payment_verified_at,
                   former_guest_user_id, created_at
            FROM orders
            WHERE $whereSql
            ORDER BY id DESC
            LIMIT $perPage OFFSET $offset
        ");

        $stmt->execute($params);

        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ['orders' => $orders, 'pages' => $pages, 'total' => $total, 'page' => $page];

    } catch (Throwable $e) {

        error_log('fetch_orders_page() error: ' . $e->getMessage());

        return ['orders' => [], 'pages' => 1, 'total' => 0, 'page' => 1];
    }
}


/*
|--------------------------------------------------------------------------
| Menu Images
|--------------------------------------------------------------------------
*/

/**
 * Image URL for a menu item row, falling back to the branded placeholder.
 */
function menu_image_url(?string $url): string
{
    $url = trim((string)$url);

    if (
        $url !== '' &&
        !str_contains($url, '..') &&
        (str_starts_with($url, 'assets/') || str_starts_with($url, 'http'))
    ) {
        return $url;
    }

    return 'assets/placeholder.svg';
}


/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
*/

function peso(float|int|string $amount): string
{
    return '₱' . number_format(
        (float)$amount,
        2
    );
}


/*
|--------------------------------------------------------------------------
| Order Status
|--------------------------------------------------------------------------
*/

function order_status_label(string $status): string
{
    $status = strtolower(trim($status));

    return match ($status) {

        'pending' =>
            'Placed',

        'confirmed' =>
            'Confirmed',

        'preparing' =>
            'Preparing',

        'out_for_delivery' =>
            'On the Way',

        'delivered' =>
            'Delivered',

        'cancelled' =>
            'Cancelled',

        default =>
            ucwords(
                str_replace(
                    '_',
                    ' ',
                    $status
                )
            ),
    };
}


/*
|--------------------------------------------------------------------------
| Order Status CSS
|--------------------------------------------------------------------------
*/

function order_status_class(string $status): string
{
    $status = strtolower(trim($status));

    return match ($status) {

        'pending' =>
            'bg-[#fdeecd] text-[#8a5a08] border border-[#f2ddb5]',

        'confirmed' =>
            'bg-[#e6f4ef] text-[#00593d] border border-[#b2e2d2]',

        'preparing' =>
            'bg-[#fbdcc0] text-[#8a3d08] border border-[#f2c39a]',

        'out_for_delivery' =>
            'bg-[#fdeeec] text-[#8f1a11] border border-[#f5cfc9]',

        'delivered' =>
            'bg-[#e2ecd2] text-[#4a5a1e] border border-[#c6d8a8]',

        'cancelled' =>
            'bg-[#f6efe0] text-[#7a6a58] border border-[#e8ddc9]',

        default =>
            'bg-[#f6efe0] text-[#4a3a28] border border-[#e8ddc9]',
    };
}


/*
|--------------------------------------------------------------------------
| Payment Badge
|--------------------------------------------------------------------------
*/

/**
 * Compact payment badge for an order row (cash / GCash state).
 */
function payment_status_badge(array $order): string
{
    $method = strtolower((string)($order['payment_method'] ?? 'cash'));

    if ($method !== 'gcash') {
        return '<span class="px-2 py-0.5 text-[10px] font-bold uppercase border border-[#e8ddc9] bg-[#f6efe0] text-[#7a6a58]">Cash on delivery</span>';
    }

    if (!empty($order['payment_verified_at'])) {
        return '<span class="px-2 py-0.5 text-[10px] font-bold uppercase border border-[#b2e2d2] bg-[#e6f4ef] text-[#00593d]">GCash paid</span>';
    }

    return '<span class="px-2 py-0.5 text-[10px] font-bold uppercase border border-[#f2ddb5] bg-[#fdeecd] text-[#8a5a08]">GCash — verifying</span>';
}


/*
|--------------------------------------------------------------------------
| Order Status Flow
|--------------------------------------------------------------------------
*/

function next_order_status(string $status): ?string
{
    $status = strtolower(trim($status));

    return match ($status) {

        'pending' =>
            'confirmed',

        'confirmed' =>
            'preparing',

        'preparing' =>
            'out_for_delivery',

        'out_for_delivery' =>
            'delivered',

        default =>
            null,
    };
}


/*
|--------------------------------------------------------------------------
| Order Status Validation
|--------------------------------------------------------------------------
*/

function is_valid_order_status(string $status): bool
{
    return in_array(
        strtolower(trim($status)),
        [
            'pending',
            'confirmed',
            'preparing',
            'out_for_delivery',
            'delivered',
            'cancelled',
        ],
        true
    );
}


/*
|--------------------------------------------------------------------------
| Safe Redirect
|--------------------------------------------------------------------------
*/

function safe_redirect(
    string $location,
    string $fallback = '/order.php'
): never {

    /*
     * Only allow internal paths.
     */
    if (
        $location === '' ||
        !str_starts_with($location, '/') ||
        str_starts_with($location, '//')
    ) {
        $location = $fallback;
    }

    header('Location: ' . $location);

    exit;
}


/*
|--------------------------------------------------------------------------
| JSON Response Helper
|--------------------------------------------------------------------------
*/

function json_response(
    array $data,
    int $statusCode = 200
): never {

    http_response_code($statusCode);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}