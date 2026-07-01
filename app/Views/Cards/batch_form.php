<?php
// Self-contained (the layout includes this with no data). Barangay + sector
// pickers come from the canonical sources so the filter values always match
// what headsForCards() compares against — a free-text barangay silently matched
// nothing when it differed from the stored value.
$barangayList  = \App\Support\FamilyProfilingFormV2::barangays();
$sectorOptions = (new \App\Models\Lookups\SectorModel())->getSectorOptions();
?>
<div class="sector-management records-scroll-panel" id="qr-cards-page">
    <div class="records-search-panel">
        <form id="qr-cards-form" class="records-search-row" autocomplete="off">
            <?= csrf_field() ?>
            <select class="form-select" id="qr-barangay" name="barangay" aria-label="Barangay">
                <option value="">All barangays</option>
                <?php foreach ($barangayList as $barangay): ?>
                    <option value="<?= esc($barangay, 'attr') ?>"><?= esc($barangay) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" id="qr-sector" name="sectorID" aria-label="Sector">
                <option value="">All sectors</option>
                <?php foreach ($sectorOptions as $sector): ?>
                    <option value="<?= esc((string) ($sector['sectorID'] ?? ''), 'attr') ?>">
                        <?= esc((string) ($sector['name'] ?? '')) ?><?= ! empty($sector['shortcode']) ? ' (' . esc((string) $sector['shortcode']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary records-search-action" id="qr-generate-btn"><i class="bi bi-printer" aria-hidden="true"></i><span>Generate cards</span></button>
        </form>
    </div>

    <div class="table-meta">
        <div class="records-table-controls">
            <span class="text-muted small">Generate printable QR cards for registered heads of family. Leave the filters on "All" to print every active head.</span>
            <span id="qr-status" class="text-muted small"></span>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('qr-cards-form');
    const status = document.getElementById('qr-status');
    const btn = document.getElementById('qr-generate-btn');
    if (!form) { return; }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        status.textContent = 'Generating…';
        btn.disabled = true;
        try {
            const resp = await fetch('<?= site_url('admin/cards/generate') ?>', {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!resp.ok) {
                const text = await resp.text();
                let message = 'Generation failed.';
                try { message = JSON.parse(text).error || message; } catch (_) {}
                status.textContent = message;
                return;
            }
            const blob = await resp.blob();
            const disposition = resp.headers.get('Content-Disposition') || '';
            const match = disposition.match(/filename="([^"]+)"/);
            const filename = match ? match[1] : 'binan-qr-cards.pdf';
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            status.textContent = 'Done.';
        } catch (err) {
            status.textContent = 'Generation failed. Please try again.';
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
