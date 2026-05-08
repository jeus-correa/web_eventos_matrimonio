<?php
/**
 * Genera PNG QR con chillerlan/php-qrcode y guarda en /qrcodes/
 */
declare(strict_types=1);

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

function generate_event_qr_png(int $eventId, string $targetUrl): string
{
    $filename = 'event-' . $eventId . '.png';
    $full = qrcodes_dir() . '/' . $filename;

    $options = new QROptions([
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel' => QRCode::ECC_H,
        'scale' => 6,
    ]);

    (new QRCode($options))->render($targetUrl, $full);

    return 'qrcodes/' . $filename;
}
