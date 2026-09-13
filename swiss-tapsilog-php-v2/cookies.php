<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Cookie Notice';

include __DIR__ . '/includes/header.php';
?>

<main class="min-h-screen bg-[#fdf8ef]">

    <section class="border-b bg-white">
        <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6">

            <p class="text-sm font-semibold text-[#e8871e]">
                Swiss Tapsilog
            </p>

            <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#2b2118]">
                Cookie Notice
            </h1>

            <p class="mt-3 text-sm text-[#8a7a64]">
                Effective Date: September 13, 2026
            </p>

        </div>
    </section>


    <article class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12">

        <div class="space-y-10 rounded-2xl border border-[#e8ddc9] bg-white p-6 shadow-sm sm:p-10">

            <section>

                <h2 class="text-xl font-bold text-[#2b2118]">
                    1. What Are Cookies?
                </h2>

                <p class="mt-3 leading-7 text-[#7a6a58]">
                    Cookies are small pieces of information stored by your
                    browser when you visit a website. They can help a website
                    remember information, maintain a session, provide security,
                    and support requested functionality.
                </p>

            </section>


            <section>

                <h2 class="text-xl font-bold text-[#2b2118]">
                    2. How Swiss Tapsilog Uses Cookies
                </h2>

                <p class="mt-3 leading-7 text-[#7a6a58]">
                    Swiss Tapsilog may use cookies and similar technologies
                    for the following categories.
                </p>

            </section>


            <!-- Necessary -->
            <section>

                <h2 class="text-lg font-bold text-[#2b2118]">
                    Necessary Cookies
                </h2>

                <p class="mt-3 leading-7 text-[#7a6a58]">
                    These cookies are necessary for the website to function.
                    They may support:
                </p>

                <ul class="mt-4 list-disc space-y-2 pl-6 text-[#7a6a58]">

                    <li>Customer login sessions</li>

                    <li>Security controls</li>

                    <li>CSRF protection</li>

                    <li>Order functionality</li>

                    <li>Account authentication</li>

                    <li>Basic website functionality</li>

                </ul>

                <div class="mt-4 rounded-xl bg-[#fdf8ef] p-4 text-sm text-[#7a6a58]">
                    Necessary cookies cannot normally be disabled through
                    the website's optional cookie preference controls because
                    doing so may prevent core functionality from operating.
                </div>

            </section>


            <!-- Optional -->
            <section>

                <h2 class="text-lg font-bold text-[#2b2118]">
                    Optional Cookies
                </h2>

                <p class="mt-3 leading-7 text-[#7a6a58]">
                    If implemented, optional technologies may include
                    analytics, personalization, advertising, or marketing
                    technologies.
                </p>

                <p class="mt-3 leading-7 text-[#7a6a58]">
                    Optional technologies should only be activated in
                    accordance with applicable requirements and your selected
                    privacy preferences.
                </p>

            </section>


            <!-- Current Implementation -->
            <section>

                <h2 class="text-lg font-bold text-[#2b2118]">
                    Current Website Implementation
                </h2>

                <p class="mt-3 leading-7 text-[#7a6a58]">
                    The ordering system primarily requires session and
                    security functionality. We do not represent that optional
                    analytics or advertising cookies are active unless they
                    are specifically implemented and disclosed.
                </p>

            </section>


            <!-- Choices -->
            <section>

                <h2 class="text-xl font-bold text-[#2b2118]">
                    3. Your Cookie Choices
                </h2>

                <p class="mt-3 leading-7 text-[#7a6a58]">
                    You may manage available optional cookie preferences
                    through our Privacy Choices page.
                </p>

                <a
                    href="privacy-choices.php"
                    class="mt-4 inline-flex rounded-xl bg-[#e8871e] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#c05a11]"
                >
                    Manage Cookie Preferences
                </a>

            </section>


            <!-- Browser -->
            <section>

                <h2 class="text-xl font-bold text-[#2b2118]">
                    4. Browser Controls
                </h2>

                <p class="mt-3 leading-7 text-[#7a6a58]">
                    Most browsers provide controls that allow you to delete
                    or restrict cookies. Disabling necessary cookies may
                    prevent login, ordering, or other functionality from
                    working properly.
                </p>

            </section>


            <!-- Changes -->
            <section>

                <h2 class="text-xl font-bold text-[#2b2118]">
                    5. Changes to This Notice
                </h2>

                <p class="mt-3 leading-7 text-[#7a6a58]">
                    This Cookie Notice may be updated when our website,
                    technology, or cookie practices change.
                </p>

            </section>


            <section>

                <h2 class="text-xl font-bold text-[#2b2118]">
                    6. Contact
                </h2>

                <p class="mt-3 text-[#7a6a58]">
                    Questions about cookies or privacy may be sent to:
                </p>

                <a
                    href="mailto:privacy@allanbertbriones.com"
                    class="mt-2 inline-block font-semibold text-[#e8871e]"
                >
                    privacy@allanbertbriones.com
                </a>

            </section>

        </div>

    </article>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>