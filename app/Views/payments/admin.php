<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<section class="mx-auto max-w-5xl">
    <header class="border-b border-line pb-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('Payments.admin_title')) ?></p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-ink"><?= esc(lang('Payments.admin_title')) ?></h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-muted"><?= esc(lang('Payments.admin_description')) ?></p>
    </header>

    <section class="mt-6 rounded-md border border-line bg-paper p-5">
        <h2 class="text-lg font-semibold text-ink"><?= esc(lang('Payments.qr_setup_title')) ?></h2>
        <?php if ($qrConfigured): ?>
            <img class="mt-4 size-56 border border-line bg-white object-contain p-2" src="<?= esc($qrUrl) ?>" alt="<?= esc(lang('Payments.qr_image_alt')) ?>">
        <?php endif; ?>
        <form action="<?= esc($qrUploadUrl) ?>" method="post" enctype="multipart/form-data" class="mt-4 max-w-xl">
            <?= csrf_field() ?>
            <label class="block text-sm font-semibold text-ink" for="payment-qr"><?= esc(lang('Payments.qr_upload_label')) ?></label>
            <input class="mt-2 block min-h-11 w-full rounded-md border border-line bg-white px-3 py-2 text-sm text-ink file:mr-3 file:rounded-sm file:border-0 file:bg-canvas file:px-3 file:py-1.5 file:text-sm file:font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="payment-qr" name="qr_image" type="file" accept="image/jpeg,image/png,image/webp" required>
            <p class="mt-2 text-xs leading-5 text-muted"><?= esc(lang('Payments.qr_setup_help')) ?></p>
            <button class="mt-4 inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-4 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" type="submit"><?= esc(lang('Payments.qr_upload_action')) ?></button>
        </form>
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-semibold tracking-tight text-ink"><?= esc(lang('Payments.admin_title')) ?></h2>
        <?php if ($pendingPayments === []): ?>
            <p class="mt-4 rounded-md border border-line bg-canvas p-5 text-sm text-muted" role="status"><?= esc(lang('Payments.admin_empty')) ?></p>
        <?php else: ?>
            <ul class="mt-4 space-y-4">
                <?php foreach ($pendingPayments as $payment): ?>
                    <li class="rounded-md border border-line bg-paper p-5">
                        <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_14rem]">
                            <div>
                                <h3 class="font-semibold text-ink"><?= esc(lang('Payments.admin_payment_for')) ?>: <?= esc($payment['cv_title']) ?></h3>
                                <p class="mt-2 text-sm text-muted"><?= esc(lang('Payments.admin_user_id')) ?>: <?= esc((string) $payment['user_id']) ?></p>
                                <p class="mt-1 text-sm text-muted"><?= esc(lang('Payments.admin_amount')) ?>: <?= esc(lang('Payments.price_amount', [number_format((int) $payment['amount_idr'], 0, ',', '.')])) ?></p>
                                <a class="mt-3 inline-flex min-h-10 items-center text-sm font-semibold text-accent-900 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc($proofUrl((string) $payment['id'])) ?>" target="_blank" rel="noopener noreferrer"><?= esc(lang('Payments.admin_open_proof')) ?></a>
                            </div>
                            <div class="flex items-start justify-center">
                                <img class="max-h-56 max-w-full border border-line object-contain" src="<?= esc($proofUrl((string) $payment['id'])) ?>" alt="<?= esc(lang('Payments.admin_open_proof')) ?>">
                            </div>
                        </div>
                        <form action="<?= esc($reviewUrl((string) $payment['id'])) ?>" method="post" class="mt-5 border-t border-line pt-4">
                            <?= csrf_field() ?>
                            <label class="block text-sm font-semibold text-ink" for="review-note-<?= esc($payment['id'], 'attr') ?>"><?= esc(lang('Payments.admin_note_label')) ?></label>
                            <input class="mt-2 min-h-11 w-full rounded-md border border-line bg-white px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="review-note-<?= esc($payment['id'], 'attr') ?>" name="review_note" type="text" maxlength="500" aria-describedby="review-help-<?= esc($payment['id'], 'attr') ?>">
                            <p class="mt-1 text-xs text-muted" id="review-help-<?= esc($payment['id'], 'attr') ?>"><?= esc(lang('Payments.admin_note_help')) ?></p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <button class="inline-flex min-h-10 items-center rounded-md bg-accent-700 px-4 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" type="submit" name="status" value="approved"><?= esc(lang('Payments.admin_approve')) ?></button>
                                <button class="inline-flex min-h-10 items-center rounded-md border border-red-700 px-4 text-sm font-semibold text-red-900 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="submit" name="status" value="rejected"><?= esc(lang('Payments.admin_reject')) ?></button>
                            </div>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</section>
<?= $this->endSection() ?>
