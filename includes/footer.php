<?php
declare(strict_types=1);

$currentYear = (int)date('Y');

$privacyEmail = 'privacy@allanbertbriones.com';
$businessName = 'Swiss Tapsilog';
$developerName = 'Brion Digital Solutions';
?>

</main>

<footer class="border-t bg-[#3a2d1e] bg-[#1d1712] text-[#e8ddc9]">

    <!-- Compliance / Information Footer -->
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">

        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">

            <!-- BRAND -->
            <div class="lg:col-span-1">

                <div class="flex items-center gap-3">
    <img
        src="assets/uploads/logo.png"
        alt="Silog & Co."
        class="h-10 w-10 rounded-xl object-contain"
    >

    <div>
        <p class="font-bold text-white">
            Silog &amp; Co.
        </p>

        <p class="text-xs text-[#8a7a64]">
            Food • Orders • Delivery
        </p>
    </div>
</div>
                <p class="mt-5 max-w-sm text-sm leading-6 text-[#b3a48e]">
                    Thank you for choosing Swiss Tapsilog.
                    We are committed to providing a convenient,
                    secure, and responsible online ordering experience.
                </p>

            </div>


            <!-- CUSTOMER -->
            <div>

                <h3 class="text-sm font-semibold uppercase tracking-wider text-white">
                    Customer
                </h3>

                <ul class="mt-4 space-y-3 text-sm">

                    <li>
                        <a
                            href="order.php"
                            class="transition hover:text-[#f2b01e]"
                        >
                            Order Food
                        </a>
                    </li>

                    <li>
                        <a
                            href="orders.php"
                            class="transition hover:text-[#f2b01e]"
                        >
                            My Orders
                        </a>
                    </li>

                    <li>
                        <a
                            href="account.php"
                            class="transition hover:text-[#f2b01e]"
                        >
                            My Account
                        </a>
                    </li>

                </ul>

            </div>


            <!-- LEGAL -->
            <div>

                <h3 class="text-sm font-semibold uppercase tracking-wider text-white">
                    Legal & Privacy
                </h3>

                <ul class="mt-4 space-y-3 text-sm">

                    <li>
                        <a
                            href="privacy.php"
                            class="transition hover:text-[#f2b01e]"
                        >
                            Privacy Notice
                        </a>
                    </li>

                    <li>
                        <a
                            href="cookies.php"
                            class="transition hover:text-[#f2b01e]"
                        >
                            Cookie Notice
                        </a>
                    </li>

                    <li>
                        <a
                            href="privacy-choices.php"
                            class="transition hover:text-[#f2b01e]"
                        >
                            Privacy Choices
                        </a>
                    </li>

                    <li>
                        <a
                            href="terms.php"
                            class="transition hover:text-[#f2b01e]"
                        >
                            Terms & Conditions
                        </a>
                    </li>

                </ul>

            </div>


            <!-- PRIVACY CONTACT -->
            <div>

                <h3 class="text-sm font-semibold uppercase tracking-wider text-white">
                    Data Privacy
                </h3>

                <p class="mt-4 text-sm leading-6 text-[#b3a48e]">
                    For privacy questions, requests, or concerns,
                    please contact us through our privacy contact.
                </p>

                <a
                    href="mailto:<?= e($privacyEmail) ?>"
                    class="mt-3 inline-flex break-all text-sm font-medium text-[#f2b01e] hover:text-[#f8ce62]"
                >
                    <?= e($privacyEmail) ?>
                </a>

                <p class="mt-4 text-xs leading-5 text-[#8a7a64]">
                    We process personal information in accordance
                    with applicable Philippine data privacy laws
                    and regulations.
                </p>

            </div>

        </div>


        <!-- PRIVACY STATEMENT -->
        <div class="mt-10 rounded-2xl border border-[#4a3a28] bg-[#2b2118]/70 p-5">

            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">

                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#4a3a28] text-[#f2b01e]">
                    🔒
                </div>

                <div>

                    <h3 class="text-sm font-semibold text-white">
                        Your Privacy Matters
                    </h3>

                    <p class="mt-1 text-xs leading-5 text-[#b3a48e]">
                        Swiss Tapsilog respects your privacy and handles
                        personal information in accordance with the
                        principles of transparency, legitimate purpose,
                        and proportionality under the Philippine Data
                        Privacy Act of 2012 (Republic Act No. 10173),
                        its Implementing Rules and Regulations, and
                        applicable issuances of the National Privacy
                        Commission.
                    </p>

                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs">

                        <a
                            href="privacy.php#your-rights"
                            class="font-medium text-[#f2b01e] hover:text-[#f8ce62]"
                        >
                            Your Privacy Rights →
                        </a>

                        <a
                            href="privacy-choices.php"
                            class="font-medium text-[#f2b01e] hover:text-[#f8ce62]"
                        >
                            Manage Privacy Choices →
                        </a>

                        <a
                            href="cookies.php"
                            class="font-medium text-[#f2b01e] hover:text-[#f8ce62]"
                        >
                            Manage Cookies →
                        </a>

                    </div>

                </div>

            </div>

        </div>


        <!-- COPYRIGHT -->
        <div class="mt-8 border-t border-[#4a3a28] pt-6">

            <div class="flex flex-col gap-4 text-center text-xs text-[#8a7a64] sm:flex-row sm:items-center sm:justify-between sm:text-left">

                <div>

                    <p>
                        © <?= $currentYear ?>
                        <?= e($businessName) ?>.
                        All Rights Reserved.
                    </p>

                    <p class="mt-1">
                        All content, branding, software, and system
                        components are protected by applicable laws.
                    </p>

                </div>


                <div class="text-center sm:text-right">

                    <p>
                        Developed by
                        <a
                            href="https://allanbertbriones.com"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-semibold text-[#e8ddc9] transition hover:text-[#f2b01e]"
                        >
                            <?= e($developerName) ?>
                        </a>
                    </p>

                    <p class="mt-1 text-[#a8977e]">
                        Digital Systems & Web Solutions
                    </p>

                </div>

            </div>

        </div>

    </div>

</footer>