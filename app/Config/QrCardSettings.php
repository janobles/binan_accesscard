<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Tunables for the QR access-card generator (batch PDF/ZIP + single card).
 *
 * The control number is derived from a head's memberID, zero-padded to
 * $controlNumberWidth. The QR payload is $qrUrlPrefix prepended to that number
 * (empty prefix = bare number). Every value is .env-overridable with the
 * "qrcardsettings." prefix, e.g.:
 *
 *   qrcardsettings.qrUrlPrefix = "https://app.binan.gov.ph/admin/cards/lookup/"
 */
class QrCardSettings extends BaseConfig
{
    /** Text prepended to each control number to form the QR payload. */
    public string $qrUrlPrefix = '';

    /** QR cards per printed page. The PDF template is a fixed 3x4 grid. */
    public int $cellsPerPage = 12;

    /** Cards per chunk; a batch larger than this is split into several PDFs in a ZIP. */
    public int $cardsPerChunk = 600;

    /** Zero-padded width of a control number ("000042" = width 6). */
    public int $controlNumberWidth = 6;

    /** Hard upper bound on cards generated in a single batch. */
    public int $maxQuantity = 25000;

    /** Filename for a single-chunk batch (served as application/pdf). */
    public string $singlePdfFileName = 'binan-qr-cards.pdf';

    /** sprintf pattern for a multi-chunk ZIP. Two %s: first and last control number. */
    public string $zipFileNamePattern = 'binan-qr-cards-%s-%s.zip';

    /** sprintf pattern for each chunk PDF inside the ZIP. Two %s: first and last control number. */
    public string $chunkPdfNamePattern = 'cards-%s-%s.pdf';
}
