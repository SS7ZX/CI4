<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?= esc(lang('Auth.passwordResetSubject')) ?></title>
</head>
<body>
    <p>Halo,</p>
    <p>Untuk mengatur kata sandi CV Maker yang baru, buka tautan berikut:</p>
    <p><a href="<?= esc($resetUrl) ?>"><?= esc(lang('Auth.resetPassword')) ?></a></p>
    <p>Tautan berlaku selama <?= esc((string) $lifetimeMinutes) ?> menit. Jika Anda tidak meminta reset ini, abaikan email ini dan kata sandi lama tetap berlaku.</p>
</body>
</html>
