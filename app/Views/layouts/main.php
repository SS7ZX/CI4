<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= esc(lang('App.home_description')) ?>">
    <meta
        name="csrf-token"
        content="<?= esc(csrf_hash(), 'attr') ?>"
        data-field-name="<?= esc(csrf_token(), 'attr') ?>"
        data-header-name="<?= esc(config(\Config\Security::class)->headerName, 'attr') ?>"
    >
    <title><?= esc($title ?? lang('App.app_name')) ?></title>
    <script src="/assets/js/theme.js"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
    <div class="motion-progress-line" data-motion-progress aria-hidden="true"></div>
    <div class="flex min-h-screen flex-col" x-data="{ menuOpen: false }">
        <a class="sr-only rounded-md bg-paper px-4 py-2 text-accent-900 focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:outline-2 focus:outline-offset-2 focus:outline-accent-700" href="#content"><?= esc(lang('App.skip_content')) ?></a>
        <header class="border-b border-line bg-canvas" data-view-topbar>
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <a class="inline-flex items-center gap-3 rounded-md text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(site_url('/')) ?>">
                    <span class="flex size-9 items-center justify-center rounded-md bg-accent-700 text-sm font-semibold tracking-tight text-white" aria-hidden="true">CV</span>
                    <span class="text-base font-semibold tracking-tight"><?= esc(lang('App.app_name')) ?></span>
                </a>
                <button
                    class="rounded-md border border-line bg-paper px-3 py-2 text-sm font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700 md:hidden"
                    type="button"
                    aria-controls="main-navigation"
                    x-bind:aria-expanded="menuOpen.toString()"
                    x-on:click="menuOpen = !menuOpen"
                >
                    <?= esc(lang('App.menu')) ?>
                </button>
                <div class="ml-auto flex items-center gap-2 md:ml-0">
                    <button
                        class="ui-icon-button"
                        type="button"
                        data-theme-toggle
                        data-label-light="<?= esc(lang('App.theme_light')) ?>"
                        data-label-dark="<?= esc(lang('App.theme_dark')) ?>"
                        aria-label="<?= esc(lang('App.theme_toggle')) ?>"
                        aria-pressed="false"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <circle cx="12" cy="12" r="3.5"></circle>
                            <path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"></path>
                        </svg>
                        <span class="sr-only" data-theme-label><?= esc(lang('App.theme_toggle')) ?></span>
                    </button>
                    <button
                        class="ui-icon-button"
                        type="button"
                        data-command-open
                        aria-label="<?= esc(lang('App.command_open')) ?>"
                        title="<?= esc(lang('App.command_shortcut')) ?>"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <circle cx="11" cy="11" r="6"></circle>
                            <path d="m16 16 4 4"></path>
                        </svg>
                        <span class="sr-only"><?= esc(lang('App.command_open')) ?></span>
                    </button>
                </div>
                <nav
                    class="w-full border-t border-line pt-3 md:w-auto md:border-0 md:pt-0"
                    id="main-navigation"
                    hx-target="#content"
                    hx-select="#content > *"
                    hx-swap="innerHTML transition:true"
                    data-app-navigation
                    x-bind:class="menuOpen ? 'block' : 'hidden md:block'"
                >
                    <a class="nav-item relative inline-flex min-h-10 items-center rounded-md px-3 text-sm font-medium text-muted hover:bg-paper hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700"
                        href="<?= esc(site_url('/#themes')) ?>" data-nav-key="themes">
                        <?= esc(lang('App.nav_themes')) ?>
                    </a>
                    <?php if (auth()->loggedIn()): ?>
                        <a class="nav-item relative inline-flex min-h-10 items-center rounded-md px-3 text-sm font-medium text-muted hover:bg-paper hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700"
                            href="<?= esc(url_to('dashboard')) ?>" hx-boost="true" data-nav-key="dashboard">
                            <?= esc(lang('Dashboard.title')) ?>
                            <?php if (str_starts_with(trim(service('request')->getUri()->getPath(), '/'), 'dashboard')): ?>
                                <span class="nav-active-indicator" aria-hidden="true"></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-item relative inline-flex min-h-10 items-center rounded-md px-3 text-sm font-medium text-muted hover:bg-paper hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700"
                            href="<?= esc(url_to('password-edit')) ?>" hx-boost="true" data-nav-key="password">
                            <?= esc(lang('Auth.changePassword')) ?>
                            <?php if (str_contains(trim(service('request')->getUri()->getPath(), '/'), 'password')): ?>
                                <span class="nav-active-indicator" aria-hidden="true"></span>
                            <?php endif; ?>
                        </a>
                        <form id="logout-form" action="<?= esc(same_origin_url((string) url_to('logout'))) ?>" method="post" class="inline-flex">
                            <?= csrf_field() ?>
                            <button class="inline-flex min-h-10 items-center rounded-md px-3 text-sm font-medium text-muted hover:bg-paper hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit">
                                <?= esc(lang('Auth.logout')) ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <a class="nav-item relative inline-flex min-h-10 items-center rounded-md px-3 text-sm font-medium text-muted hover:bg-paper hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700"
                            href="<?= esc(url_to('login')) ?>" hx-boost="true" data-nav-key="login">
                            <?= esc(lang('Auth.login')) ?>
                            <?php if (trim(service('request')->getUri()->getPath(), '/') === 'login'): ?>
                                <span class="nav-active-indicator" aria-hidden="true"></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-item relative inline-flex min-h-10 items-center rounded-md bg-accent-700 px-3 text-sm font-medium text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900"
                            href="<?= esc(url_to('register')) ?>" hx-boost="true" data-nav-key="register">
                            <?= esc(lang('Auth.register')) ?>
                            <?php if (trim(service('request')->getUri()->getPath(), '/') === 'register'): ?>
                                <span class="nav-active-indicator" aria-hidden="true"></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 sm:py-10" id="content" data-view-content tabindex="-1">
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

        <footer class="mt-12 border-t border-line bg-canvas">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-sm text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <span class="font-semibold text-ink"><?= esc(lang('App.app_name')) ?></span>
                <span><?= esc(lang('App.footer_text')) ?></span>
            </div>
        </footer>
    </div>
    <dialog class="ui-command-dialog" data-command-palette aria-labelledby="command-palette-title">
        <div class="ui-command-shell">
            <div class="border-b border-line px-4 py-3">
                <h2 class="sr-only" id="command-palette-title"><?= esc(lang('App.command_title')) ?></h2>
                <label class="sr-only" for="command-palette-search"><?= esc(lang('App.command_placeholder')) ?></label>
                <input
                    class="ui-command-search"
                    id="command-palette-search"
                    type="search"
                    autocomplete="off"
                    placeholder="<?= esc(lang('App.command_placeholder')) ?>"
                    data-command-input
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls="command-palette-results"
                    aria-expanded="false"
                >
            </div>
            <div class="max-h-80 overflow-y-auto p-2" id="command-palette-results" role="listbox" data-command-list>
                <a class="ui-command-item" id="command-result-themes" role="option" aria-selected="false" tabindex="-1" href="<?= esc(site_url('/#themes')) ?>" data-command-result>
                    <?= esc(lang('App.command_themes')) ?>
                </a>
                <?php if (auth()->loggedIn()): ?>
                    <a class="ui-command-item" id="command-result-dashboard" role="option" aria-selected="false" tabindex="-1" href="<?= esc(url_to('dashboard')) ?>" data-command-result>
                        <?= esc(lang('App.command_dashboard')) ?>
                    </a>
                    <a class="ui-command-item" id="command-result-password" role="option" aria-selected="false" tabindex="-1" href="<?= esc(url_to('password-edit')) ?>" data-command-result>
                        <?= esc(lang('App.command_password')) ?>
                    </a>
                    <button class="ui-command-item w-full text-left" id="command-result-logout" type="button" role="option" aria-selected="false" tabindex="-1" data-command-result data-command-form="logout-form">
                        <?= esc(lang('App.command_logout')) ?>
                    </button>
                <?php else: ?>
                    <a class="ui-command-item" id="command-result-login" role="option" aria-selected="false" tabindex="-1" href="<?= esc(url_to('login')) ?>" data-command-result>
                        <?= esc(lang('App.command_login')) ?>
                    </a>
                    <a class="ui-command-item" id="command-result-register" role="option" aria-selected="false" tabindex="-1" href="<?= esc(url_to('register')) ?>" data-command-result>
                        <?= esc(lang('App.command_register')) ?>
                    </a>
                <?php endif; ?>
                <button class="ui-command-item w-full text-left" id="command-result-theme-toggle" type="button" role="option" aria-selected="false" tabindex="-1" data-command-result data-command-theme>
                    <?= esc(lang('App.theme_toggle')) ?>
                </button>
            </div>
            <p class="border-t border-line px-4 py-2 text-xs text-muted" data-command-empty role="status" aria-live="polite" aria-atomic="true" hidden><?= esc(lang('App.command_empty')) ?></p>
            <p class="border-t border-line px-4 py-2 text-xs text-muted"><?= esc(lang('App.command_hint')) ?></p>
        </div>
    </dialog>
    <div
        class="motion-toast-region"
        data-motion-toast-region
        data-dismiss-label="<?= esc(lang('App.toast_dismiss'), 'attr') ?>"
        aria-live="polite"
        aria-relevant="additions"
    ></div>
    <script defer src="/assets/js/htmx.min.js"></script>
    <script defer src="/assets/js/motion.js"></script>
    <script defer src="/assets/js/motion-shell.js"></script>
    <script defer src="/assets/js/toast.js"></script>
    <script defer src="/assets/js/foundation.js"></script>
    <script defer src="/assets/js/alpine.min.js"></script>
</body>
</html>
