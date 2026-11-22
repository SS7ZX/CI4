<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?= esc(lang('Auth.passwordResetSubject')) ?></title>
</head>
<body>
    <p><?= esc(lang('Auth.passwordResetEmailGreeting')) ?></p>
    <p><?= esc(lang('Auth.passwordResetEmailInstructions')) ?></p>
    <p><a href="<?= esc($resetUrl) ?>"><?= esc(lang('Auth.resetPassword')) ?></a></p>
    <p><?= esc(lang('Auth.passwordResetEmailExpiry', [(string) $lifetimeMinutes])) ?></p>
</body>
</html>
