<?php

/*
|--------------------------------------------------------------------------
| Notification delivery credentials
|--------------------------------------------------------------------------
|
| Used by the password-reset flow (includes/auth.php → deliver_reset_code).
| Values fall back to environment variables first, then to the empty
| strings here — with no keys configured, the app mirrors reset codes
| into the session while APP_DEBUG is true (local testing mode).
|
| Email (Resend)  — https://resend.com  → create an API key.
|   • 'resend_from' must be a address on a domain verified in Resend.
|     The default onboarding@resend.dev only delivers to your own
|     Resend account's email — fine for testing, not for clients.
|
| SMS (Vonage)    — https://dashboard.vonage.com → API key + secret.
|   • Philippine numbers are converted to +63 automatically.
|   • 'vonage_from' is the sender ID shown on the phone; keep it short.
|
| You can also set these as real environment variables instead of
| editing this file:
|   RESEND_API_KEY, RESEND_FROM, VONAGE_API_KEY, VONAGE_API_SECRET, VONAGE_FROM
|
*/

return [
    'resend_api_key'    => getenv('RESEND_API_KEY') ?: '',
    'resend_from'       => getenv('RESEND_FROM') ?: 'onboarding@resend.dev',

    'vonage_api_key'    => getenv('VONAGE_API_KEY') ?: '',
    'vonage_api_secret' => getenv('VONAGE_API_SECRET') ?: '',
    'vonage_from'       => getenv('VONAGE_FROM') ?: 'SilogCo',
];
