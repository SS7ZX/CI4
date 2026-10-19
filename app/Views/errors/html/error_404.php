<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc(lang('Errors.custom_404_title')) ?> | <?= esc(lang('App.app_name')) ?></title>
    <style>
        :root { color-scheme: light; --paper: #f4f4ef; --ink: #202521; --muted: #606b64; --accent: #315848; --line: #d7dbd4; }
        * { box-sizing: border-box; }
        body {
            align-items: center;
            background: var(--paper);
            color: var(--ink);
            display: flex;
            font-family: "Segoe UI", Arial, sans-serif;
            justify-content: center;
            margin: 0;
            min-height: 100vh;
            padding: 1.25rem;
        }
        main { background: #fff; border: 1px solid var(--line); max-width: 38rem; padding: clamp(1.5rem, 6vw, 3rem); width: 100%; }
        .eyebrow { color: var(--accent); font-size: .75rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .code { border-bottom: 1px solid var(--line); color: var(--accent); font-size: clamp(3rem, 15vw, 5rem); font-weight: 700; letter-spacing: -.06em; line-height: 1; margin: 1rem 0; padding-bottom: 1rem; }
        h1 { font-size: clamp(1.5rem, 5vw, 2rem); letter-spacing: -.03em; line-height: 1.2; margin: 0; }
        p { color: var(--muted); line-height: 1.7; margin: 1rem 0 1.5rem; }
        a { align-items: center; background: var(--accent); color: #fff; display: inline-flex; font-size: .9rem; font-weight: 600; min-height: 2.75rem; padding: .65rem 1rem; text-decoration: none; }
        a:focus-visible { outline: 3px solid #9a673a; outline-offset: 3px; }
        @media (max-width: 360px) { main { padding: 1.25rem; } }
    </style>
</head>
<body>
    <main>
        <p class="eyebrow"><?= esc(lang('Errors.error_code_404')) ?></p>
        <p class="code" aria-hidden="true">404</p>
        <h1><?= esc(lang('Errors.custom_404_title')) ?></h1>
        <p><?= esc(lang('Errors.custom_404_description')) ?></p>
        <a href="<?= esc(site_url('/')) ?>"><?= esc(lang('Errors.return_home')) ?></a>
    </main>
</body>
</html>
