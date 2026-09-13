<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Privacy Choices';

include __DIR__ . '/includes/header.php';

$user = current_user();

$analyticsEnabled = false;
$marketingEnabled = false;

/*
|--------------------------------------------------------------------------
| Read latest preferences from database
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT consent_type, granted
        FROM privacy_consents
        WHERE user_id = ?
          AND consent_type IN ('analytics', 'marketing')
        ORDER BY id DESC
    ");

    $stmt->execute([
        (int)$user['id']
    ]);

    $seen = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $type = (string)$row['consent_type'];

        if (isset($seen[$type])) {
            continue;
        }

        $seen[$type] = true;

        if ($type === 'analytics') {
            $analyticsEnabled = (bool)$row['granted'];
        }

        if ($type === 'marketing') {
            $marketingEnabled = (bool)$row['granted'];
        }
    }

} catch (Throwable $e) {

    error_log(
        'Privacy choices load error: ' . $e->getMessage()
    );
}

?>

<main class="min-h-screen bg-[#fdf8ef]">

    <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">

        <div class="mb-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-[#d9281c]">
                Privacy
            </p>

            <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#2b2118]">
                Privacy Choices
            </h1>

            <p class="mt-3 max-w-2xl text-[#7a6a58]">
                Manage optional uses of your personal data and website
                technologies. Changes you make here will be recorded with
                your account.
            </p>
        </div>

        <div class="space-y-6">

            <!-- Necessary -->
            <section class="rounded-2xl border border-[#e8ddc9] bg-white p-6 shadow-sm">

                <div class="flex items-start justify-between gap-6">

                    <div>

                        <h2 class="text-lg font-semibold text-[#2b2118]">
                            Necessary functionality
                        </h2>

                        <p class="mt-2 text-sm leading-6 text-[#7a6a58]">
                            Required for customer accounts, login sessions,
                            security, CSRF protection, ordering, and other
                            essential functionality of Swiss Tapsilog.
                        </p>

                    </div>

                    <span class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-[#4a3a28]">
                        Always on
                    </span>

                </div>

            </section>


            <!-- Analytics -->
            <section class="rounded-2xl border border-[#e8ddc9] bg-white p-6 shadow-sm">

                <div class="flex items-start justify-between gap-6">

                    <div>

                        <h2 class="text-lg font-semibold text-[#2b2118]">
                            Analytics
                        </h2>

                        <p class="mt-2 text-sm leading-6 text-[#7a6a58]">
                            Allows optional measurement of website usage and
                            performance so we can understand how the service
                            is used and improve it.
                        </p>

                    </div>

                    <label class="relative inline-flex cursor-pointer items-center">

                        <input
                            id="analyticsConsent"
                            type="checkbox"
                            class="peer sr-only"
                            <?= $analyticsEnabled ? 'checked' : '' ?>
                        >

                        <span class="h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-[#e8871e] peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#f8e3bd] after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:after:translate-x-full"></span>

                    </label>

                </div>

            </section>


            <!-- Marketing -->
            <section class="rounded-2xl border border-[#e8ddc9] bg-white p-6 shadow-sm">

                <div class="flex items-start justify-between gap-6">

                    <div>

                        <h2 class="text-lg font-semibold text-[#2b2118]">
                            Marketing
                        </h2>

                        <p class="mt-2 text-sm leading-6 text-[#7a6a58]">
                            Allows optional use of your information for
                            promotional communications and marketing activities.
                        </p>

                    </div>

                    <label class="relative inline-flex cursor-pointer items-center">

                        <input
                            id="marketingConsent"
                            type="checkbox"
                            class="peer sr-only"
                            <?= $marketingEnabled ? 'checked' : '' ?>
                        >

                        <span class="h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-[#e8871e] peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#f8e3bd] after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:after:translate-x-full"></span>

                    </label>

                </div>

            </section>


            <!-- Information -->
            <section class="rounded-2xl border border-blue-100 bg-blue-50 p-6">

                <h2 class="font-semibold text-blue-950">
                    Your privacy rights
                </h2>

                <p class="mt-2 text-sm leading-6 text-blue-900">

                    Depending on the applicable circumstances, you may have
                    rights relating to access, correction, objection, erasure
                    or blocking, portability, and other rights under applicable
                    Philippine data protection law.

                </p>

                <a
                    href="privacy.php"
                    class="mt-4 inline-block font-semibold text-blue-700 hover:underline"
                >
                    Read the Privacy Notice →
                </a>

            </section>


            <!-- Buttons -->
            <div class="flex flex-wrap gap-3">

                <button
                    id="savePrivacy"
                    type="button"
                    class="rounded-xl bg-[#1d1712] px-5 py-3 text-sm font-semibold text-white transition hover:bg-gray-800"
                >
                    Save preferences
                </button>

                <button
                    id="rejectOptional"
                    type="button"
                    class="rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-[#2b2118] transition hover:bg-[#fdf8ef]"
                >
                    Reject optional
                </button>

            </div>

            <p
                id="privacyMessage"
                class="hidden rounded-xl p-4 text-sm"
            ></p>

        </div>

    </section>

</main>

<script>

(function () {

    const csrf = <?= json_encode(csrf_token()) ?>;

    const analytics = document.getElementById('analyticsConsent');
    const marketing = document.getElementById('marketingConsent');
    const saveButton = document.getElementById('savePrivacy');
    const rejectButton = document.getElementById('rejectOptional');
    const message = document.getElementById('privacyMessage');

    function showMessage(text, success = true) {

        message.textContent = text;

        message.className =
            'rounded-xl p-4 text-sm ' +
            (
                success
                    ? 'bg-[#fdf3e3] text-[#9a4d0f] border border-[#f2ddb5]'
                    : 'bg-red-50 text-red-800 border border-red-200'
            );

    }

    async function savePreferences(analyticsValue, marketingValue) {

        saveButton.disabled = true;
        rejectButton.disabled = true;

        const formData = new FormData();

        formData.append('csrf', csrf);
        formData.append(
            'analytics',
            analyticsValue ? '1' : '0'
        );

        formData.append(
            'marketing',
            marketingValue ? '1' : '0'
        );

        try {

            const response = await fetch(
                'actions/save_privacy_preferences.php',
                {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message ||
                    'Unable to save your preferences.'
                );
            }

            /*
             * Keep a local copy so the browser can immediately know
             * which optional technologies may be activated.
             */

            localStorage.setItem(
                'swiss_tapsilog_privacy',
                JSON.stringify({
                    analytics: analyticsValue,
                    marketing: marketingValue,
                    savedAt: new Date().toISOString()
                })
            );

            showMessage(
                'Your privacy preferences have been saved.'
            );

        } catch (error) {

            showMessage(
                error.message ||
                'Unable to save your preferences.',
                false
            );

        } finally {

            saveButton.disabled = false;
            rejectButton.disabled = false;

        }

    }

    saveButton.addEventListener('click', function () {

        savePreferences(
            analytics.checked,
            marketing.checked
        );

    });

    rejectButton.addEventListener('click', function () {

        analytics.checked = false;
        marketing.checked = false;

        savePreferences(false, false);

    });

})();

</script>

<?php include __DIR__ . '/includes/footer.php'; ?>