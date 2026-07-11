<style>
    @font-face { font-family: 'Roboto'; font-style: normal; font-weight: normal; src: url('<?= APPPATH ?>Fonts/Roboto-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Roboto Mono'; font-style: normal; font-weight: normal; src: url('<?= APPPATH ?>Fonts/RobotoMono-Regular.ttf') format('truetype'); }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Roboto', sans-serif; }

    .page { width: 100%; }
    .page-break { page-break-before: always; }

    /* Fixed-height CSS table: dompdf's float layout silently drops rows. */
    .grid { display: table; width: 100%; height: 10.5in; table-layout: fixed; }
    .row  { display: table-row; }
    .cell {
        display: table-cell;
        width: 33.33%;
        border: 1px dashed #adb5bd;
        text-align: center;
        vertical-align: middle;
        padding: 0.14in 0.16in;
    }
    .cell.blank { border-color: transparent; }

    .cell .header { color: #6f42c1; font-weight: bold; font-size: 9px; line-height: 1.15; margin-bottom: 7px; }

    /* Barangay / Name: label left, value sits on an underline that fills the
       remaining width so both lines end flush at the same right edge. */
    .cell .field-row { display: table; width: 100%; margin: 4px 0; }
    .cell .field-label {
        display: table-cell;
        width: 1px;            /* shrink-to-fit the label text */
        white-space: nowrap;
        text-align: left;
        font-size: 8px;
        color: #212529;
        padding-right: 3px;
    }
    .cell .field-line {
        display: table-cell;
        border-bottom: 1px solid #212529;
        text-align: left;
        font-size: 8px;
        color: #212529;
    }

    .cell .qr-wrap { margin: 8px 0 6px 0; }
    .cell .qr { width: 1.5in; height: 1.5in; }

    .cell .control-label { font-size: 8px; color: #212529; margin-top: 3px; }
    .cell .control-number { font-family: 'Roboto Mono', monospace; font-size: 15px; letter-spacing: 1px; }
</style>
