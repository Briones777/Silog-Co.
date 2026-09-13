<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();
verify_csrf();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Your session has expired. Please sign in again.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Privacy versions
|--------------------------------------------------------------------------
|
| Increase these versions whenever you materially change the corresponding
| privacy/terms documents.
|
*/

$privacyVersion = '1.0';
$termsVersion   = '1.0';

$analytics = isset($_POST['analytics']) && $_POST['analytics'] === '1';
$marketing = isset($_POST['marketing']) && $_POST['marketing'] === '1';

try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Record a consent/acknowledgment event
    |--------------------------------------------------------------------------
    */

    $record = $pdo->prepare("
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
            CASE WHEN ? = 1 THEN NOW() ELSE NULL END,
            CASE WHEN ? = 1 THEN NULL ELSE NOW() END
        )
    ");

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    */

    $record->execute([
        $userId,
        'analytics',
        $privacyVersion,
        $analytics ? 1 : 0,
        $analytics ? 1 : 0,
        $analytics ? 1 : 0
    ]);

    /*
    |--------------------------------------------------------------------------
    | Marketing
    |--------------------------------------------------------------------------
    */

    $record->execute([
        $userId,
        'marketing',
        $privacyVersion,
        $marketing ? 1 : 0,
        $marketing ? 1 : 0,
        $marketing ? 1 : 0
    ]);

    /*
    |--------------------------------------------------------------------------
    | Privacy notice acknowledgment
    |--------------------------------------------------------------------------
    |
    | This is an acknowledgment that the customer has been provided the
    | privacy notice. It is NOT treated as blanket consent to all processing.
    |
    */

    $record->execute([
        $userId,
        'privacy_notice',
        $privacyVersion,
        1,
        1,
        0
    ]);

    /*
    |--------------------------------------------------------------------------
    | Terms acceptance
    |--------------------------------------------------------------------------
    */

    $record->execute([
        $userId,
        'terms',
        $termsVersion,
        1,
        1,
        0
    ]);

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Store current preferences in session
    |--------------------------------------------------------------------------
    */

    $_SESSION['privacy_preferences'] = [
        'analytics' => $analytics,
        'marketing' => $marketing,
        'saved_at' => date('c')
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Your privacy preferences have been saved.'
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Privacy preference error: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to save your privacy preferences.'
    ]);
}