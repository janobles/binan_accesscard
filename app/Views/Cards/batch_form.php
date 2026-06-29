<div class="container py-4" id="qr-cards-page">
    <h3 class="mb-3">QR Access Cards</h3>
    <p class="text-muted">Generate printable QR cards for registered heads of family. Leave filters blank to print all active heads.</p>

    <form id="qr-cards-form" class="row g-3" autocomplete="off">
        <?= csrf_field() ?>
        <div class="col-md-4">
            <label class="form-label" for="qr-barangay">Barangay (optional)</label>
            <input type="text" class="form-control" id="qr-barangay" name="barangay">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="qr-sector">Sector ID (optional)</label>
            <input type="number" min="1" class="form-control" id="qr-sector" name="sectorID">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary" id="qr-generate-btn">Generate cards</button>
            <span id="qr-status" class="ms-2 text-muted"></span>
        </div>
    </form>
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
