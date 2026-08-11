<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Tunables for the QR access-card generator (batch PDF/ZIP + single card).
 *
 * The control number is the head's bare memberID with no leading zeros
 * ($controlNumberWidth = 1 = effectively no padding, since every memberID ≥ 1
 * already has ≥ 1 digit). The QR payload is $qrUrlPrefix prepended to that
 * number (empty prefix = bare number). Every value is .env-overridable with the
 * "qrcardsettings." prefix, e.g.:
 *
 *   qrcardsettings.qrUrlPrefix = "https://app.binan.gov.ph/cards/lookup/"
 */
class QrCardSettings extends BaseConfig
{
    /** Text prepended to each control number to form the QR payload. */
    public string $qrUrlPrefix = "";

    /** QR cards per printed page. The PDF template is a fixed 3x4 grid. */
    public int $cellsPerPage = 12;

    /**
     * Cards per chunk; a batch larger than this is split into several PDFs in a
     * ZIP. One chunk is one dompdf render, and dompdf holds the whole document
     * plus every embedded QR image until output, costing about 0.22 MB per card
     * (1200 cards peaked at 268 MB). 1000 keeps a render inside half of
     * QrCardPdfGenerator's 512 MB ceiling; larger chunks exhaust it mid-render
     * and the request dies with no file at all.
     */
    public int $cardsPerChunk = 1000;

    /** Width of a control number (width 1 = bare memberID, no leading zeros). */
    public int $controlNumberWidth = 1;

    /** Hard upper bound on cards generated in a single batch. */
    public int $maxQuantity = 25000;

    /** Filename for a single-chunk batch (served as application/pdf). */
    public string $singlePdfFileName = "binan-qr-cards.pdf";

    /** sprintf pattern for a multi-chunk ZIP. Two %s: first and last control number. */
    public string $zipFileNamePattern = "binan-qr-cards-%s-%s.zip";

    /** sprintf pattern for each chunk PDF inside the ZIP. Two %s: first and last control number. */
    public string $chunkPdfNamePattern = "cards-%s-%s.pdf";
}
