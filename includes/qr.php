<?php
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Color\Color;

function generateQrCode(string $token): string {
    if (!is_dir(QR_DIR)) {
        mkdir(QR_DIR, 0755, true);
    }

    $filename = $token . '.png';
    $filepath = QR_DIR . '/' . $filename;

    if (!file_exists($filepath)) {
        $url = APP_URL . '/participant-login.php?token=' . $token;

        $qr = new QrCode(
            data:                 $url,
            encoding:             new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size:                 400,
            margin:               20,
            foregroundColor:      new Color(220, 0, 0),
            backgroundColor:      new Color(255, 255, 255),
        );

        $writer = new PngWriter();
        $result = $writer->write($qr);
        $result->saveToFile($filepath);
    }

    return QR_URL . '/' . $filename;
}

function qrImagePath(string $token): string {
    return QR_DIR . '/' . $token . '.png';
}
