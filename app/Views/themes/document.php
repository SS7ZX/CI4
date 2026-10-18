<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(lang('CvRender.preview_title')) ?></title>
    <style>
        @page {
            size: <?= $paper === 'letter' ? 'letter' : 'A4' ?>;
            margin: 0;
        }
        * {
            box-sizing: border-box;
        }
        html,
        body {
            margin: 0;
            min-height: 100%;
        }
        body {
            background: #f1f3f4;
            color: #202124;
            font-family: "CVMaker Sans", "DejaVu Sans", Arial, sans-serif;
        }
        .cv-sheet {
            width: <?= $paper === 'letter' ? '216mm' : '210mm' ?>;
            min-height: <?= $paper === 'letter' ? '279mm' : '297mm' ?>;
            max-width: 100%;
            margin: 20px auto;
            padding: 17mm;
            background: #fff;
        }
        .cv-section h2 {
            page-break-after: avoid;
            break-after: avoid;
        }
        .cv-entry {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        <?= $css ?>
        @media screen and (max-width: 700px) {
            .cv-sheet {
                width: 100%;
                min-height: 0;
                margin: 0;
                padding: 24px;
            }
        }
        @media print {
            body {
                background: #fff;
            }
            .cv-sheet {
                width: 100%;
                min-height: 0;
                margin: 0;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <?= $content ?>
</body>
</html>
