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
        border: 1px dashed #999;
        text-align: center;
        vertical-align: middle;
        padding: 6px;
    }
    .cell .header { color: #6f42c1; font-weight: bold; font-size: 9px; }
    .cell .qr { width: 1.4in; height: 1.4in; }
    .cell .control { font-family: 'Roboto Mono', monospace; font-size: 15px; }
    .cell .name { font-size: 10px; }
    .cell .barangay { font-size: 8px; color: #333; }
    .cell.blank { border-color: transparent; }
</style>
