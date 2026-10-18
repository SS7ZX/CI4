<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= esc(lang('App.home_description')) ?>">
    <title><?= esc($title ?? lang('App.app_name')) ?></title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/app.css')) ?>">
    <script defer src="<?= esc(base_url('assets/js/alpine.min.js')) ?>"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased">
    <div class="flex min-h-screen flex-col" x-data="{ menuOpen: false }">
        <a class="sr-only rounded-md bg-white px-4 py-2 text-accent-900 focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:outline-2 focus:outline-offset-2 focus:outline-accent-700" href="#content"><?= esc(lang('App.skip_content')) ?></a>
        <header class="border-b border-neutral-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <a class="text-lg font-semibold text-accent-700" href="<?= esc(site_url('/')) ?>">
                    <?= esc(lang('App.app_name')) ?>
                </a>
                <button
                    class="rounded-md border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700 md:hidden"
                    type="button"
                    aria-controls="main-navigation"
                    x-bind:aria-expanded="menuOpen.toString()"
                    x-on:click="menuOpen = !menuOpen"
                >
                    <?= esc(lang('App.menu')) ?>
                </button>
                <nav
                    class="w-full md:w-auto"
                    id="main-navigation"
                    x-bind:class="menuOpen ? 'block' : 'hidden md:block'"
                >
                    <?php if (auth()->loggedIn()): ?>
                        <a class="inline-flex min-h-10 items-center rounded-md px-3 text-sm font-medium text-neutral-700 hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700"
                            href="<?= esc(url_to('dashboard')) ?>">
                            <?= esc(lang('Dashboard.title')) ?>
                        </a>
                        <a class="inline-flex min-h-10 items-center rounded-md px-3 text-sm font-medium text-neutral-700 hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700"
                            href="<?= esc(url_to('password-edit')) ?>">
                            <?= esc(lang('Auth.changePassword')) ?>
                        </a>
                        <form action="<?= esc(url_to('logout')) ?>" method="post" class="inline-flex">
                            <?= csrf_field() ?>
                            <button class="inline-flex min-h-10 items-center rounded-md px-3 text-sm font-medium text-neutral-700 hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit">
                                <?= esc(lang('Auth.logout')) ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <a class="inline-flex min-h-10 items-center rounded-md px-3 text-sm font-medium text-neutral-700 hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700"
                            href="<?= esc(url_to('login')) ?>">
                            <?= esc(lang('Auth.login')) ?>
                        </a>
                        <a class="inline-flex min-h-10 items-center rounded-md px-3 text-sm font-medium text-neutral-700 hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700"
                            href="<?= esc(url_to('register')) ?>">
                            <?= esc(lang('Auth.register')) ?>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 sm:py-10" id="content">
            <?php foreach (['success', 'error', 'warning', 'info', 'message'] as $type): ?>
                <?php $message = session()->getFlashdata($type); ?>
                <?php if (is_string($message) && $message !== ''): ?>
                    <?php
                    $flashStyles = [
                        'success' => 'border-green-700 bg-green-50 text-green-900',
                        'error'   => 'border-red-700 bg-red-50 text-red-900',
                        'warning' => 'border-amber-700 bg-amber-50 text-amber-900',
                        'info'    => 'border-accent-700 bg-accent-50 text-accent-900',
                        'message' => 'border-green-700 bg-green-50 text-green-900',
                    ];
                    ?>
                    <div class="mb-4 rounded-md border px-4 py-3 text-sm <?= esc($flashStyles[$type]) ?>" role="status">
                        <?= esc($message) ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?= $this->renderSection('content') ?>
        </main>

        <footer class="border-t border-neutral-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-4 text-sm text-neutral-600 sm:px-6">
                <?= esc(lang('App.footer_text')) ?>
            </div>
        </footer>
    </div>
</body>
</html>
