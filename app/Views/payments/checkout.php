<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<section class="mx-auto max-w-3xl">
    <header class="border-b border-line pb-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('Payments.title')) ?></p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-ink"><?= esc(lang('Payments.title')) ?></h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-muted"><?= esc(lang('Payments.description')) ?></p>
    </header>

    <div class="mt-6 grid gap-6 md:grid-cols-[minmax(0,1fr)_17rem]">
        <div class="space-y-5">
            <?php if (($payment['status'] ?? null) === 'pending'): ?>
                <section class="rounded-md border border-amber-700 bg-amber-50 p-5" role="status">
                    <h2 class="font-semibold text-amber-900"><?= esc(lang('Payments.pending_title')) ?></h2>
                    <p class="mt-2 text-sm leading-6 text-amber-900"><?= esc(lang('Payments.pending_description')) ?></p>
                    <p class="mt-2 text-sm font-semibold text-amber-900"><?= esc(lang('Payments.price_amount', [number_format((int) $priceIdr, 0, ',', '.')])) ?></p>
                </section>
            <?php elseif (($payment['status'] ?? null) === 'approved'): ?>
                <section class="rounded-md border border-green-700 bg-green-50 p-5" role="status">
                    <h2 class="font-semibold text-green-900"><?= esc(lang('Payments.approved_title')) ?></h2>
                    <p class="mt-2 text-sm leading-6 text-green-900"><?= esc(lang('Payments.approved_description')) ?></p>
                    <a class="mt-4 inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-4 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" href="<?= esc($downloadUrl) ?>"><?= esc(lang('Payments.download_action')) ?></a>
                </section>
            <?php elseif (($payment['status'] ?? null) === 'rejected'): ?>
                <section class="rounded-md border border-red-700 bg-red-50 p-5" role="alert">
                    <h2 class="font-semibold text-red-900"><?= esc(lang('Payments.rejected_title')) ?></h2>
                    <p class="mt-2 text-sm leading-6 text-red-900"><?= esc(lang('Payments.rejected_description')) ?></p>
                    <?php if (is_string($payment['review_note'] ?? null) && $payment['review_note'] !== ''): ?>
                        <p class="mt-3 text-sm leading-6 text-red-900"><?= esc($payment['review_note']) ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if (! in_array($payment['status'] ?? null, ['pending', 'approved'], true)): ?>
                <section class="rounded-md border border-line bg-paper p-5">
                    <h2 class="text-lg font-semibold text-ink"><?= esc(lang('Payments.price')) ?></h2>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-ink"><?= esc(lang('Payments.price_amount', [number_format((int) $priceIdr, 0, ',', '.')])) ?></p>
                    <p class="mt-4 text-sm leading-6 text-muted"><?= esc(lang('Payments.pay_instructions')) ?></p>
                    <?php if ($qrConfigured): ?>
                        <img class="mt-5 mx-auto size-64 max-w-full border border-line bg-white object-contain p-2" src="<?= esc($qrUrl) ?>" alt="<?= esc(lang('Payments.qr_image_alt')) ?>">
                    <?php else: ?>
                        <p class="mt-5 rounded-md border border-amber-700 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900" role="status"><?= esc(lang('Payments.qr_not_configured')) ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($qrConfigured && ($payment['status'] ?? null) !== 'pending' && ($payment['status'] ?? null) !== 'approved'): ?>
                <form action="<?= esc($uploadUrl) ?>" method="post" enctype="multipart/form-data" class="rounded-md border border-line bg-paper p-5">
                    <?= csrf_field() ?>
                    <h2 class="text-lg font-semibold text-ink"><?= esc(lang('Payments.submit_proof_title')) ?></h2>
                    <label class="mt-4 block text-sm font-semibold text-ink" for="payment-proof"><?= esc(lang('Payments.proof_label')) ?></label>
                    <input class="mt-2 block min-h-11 w-full rounded-md border border-line bg-white px-3 py-2 text-sm text-ink file:mr-3 file:rounded-sm file:border-0 file:bg-canvas file:px-3 file:py-1.5 file:text-sm file:font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="payment-proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required>
                    <p class="mt-2 text-xs leading-5 text-muted"><?= esc(lang('Payments.proof_help')) ?></p>
                    <button class="mt-4 inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-4 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" type="submit"><?= esc(lang('Payments.proof_submit')) ?></button>
                </form>
            <?php endif; ?>
        </div>

        <aside class="h-fit rounded-md border border-line bg-canvas p-5">
            <h2 class="font-semibold text-ink"><?= esc(lang('Payments.title')) ?></h2>
            <p class="mt-2 break-words text-sm leading-6 text-muted"><?= esc($cvId) ?></p>
            <a class="mt-5 inline-flex min-h-10 items-center text-sm font-semibold text-accent-900 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-step', $cvId, 8)) ?>"><?= esc(lang('Payments.back_to_editor')) ?></a>
        </aside>
    </div>
</section>
<?= $this->endSection() ?>
