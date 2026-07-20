<?php
// Self-contained (the layout includes this with no data). Barangay picker comes
// from the canonical source so filter values always match what headsForCards()
// compares against. Two modes: Batch (barangay + control-number range, live
// preview table) and Single card (searchable head or exact control number).
helper('ui');
$barangayList = \App\Support\FamilyProfilingFormV2::barangays();
?>
<div class="sector-management records-scroll-panel" id="control-numbers-page">
    <div class="records-table-controls mb-2">
        <span class="text-muted small">Issue printable QR access cards for registered heads of family. The PDF is the output; the table below previews who will be printed.</span>
    </div>

    <ul class="nav nav-pills segmented-tabs mb-3" id="cn-modes" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" type="button" data-mode="batch" aria-current="page">
                <i class="bi bi-collection" aria-hidden="true"></i> Batch
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" type="button" data-mode="single">
                <i class="bi bi-person-vcard" aria-hidden="true"></i> Single card
            </button>
        </li>
    </ul>

    <div class="card">
        <div class="card-body">
            <!-- BATCH -->
            <div id="cn-panel-batch">
                <form id="cn-batch-form" class="row g-3 align-items-end" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="cn-barangay">Barangay</label>
                        <select class="form-select" id="cn-barangay" name="barangay">
                            <option value="">All barangays</option>
                            <?php foreach ($barangayList as $barangay): ?>
                                <option value="<?= esc($barangay, 'attr') ?>"><?= esc($barangay) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="cn-from">From #</label>
                        <input type="number" min="1" inputmode="numeric" class="form-control" id="cn-from" name="from" placeholder="e.g. 100">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="cn-to">To #</label>
                        <input type="number" min="1" inputmode="numeric" class="form-control" id="cn-to" name="to" placeholder="e.g. 150">
                    </div>
                    <div class="col-12">
                        <span class="text-muted small">Leave all blank to print every active head. Both range bounds are inclusive.</span>
                    </div>
                </form>

                <div class="table-meta mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span id="cn-preview-count" class="fw-semibold" aria-live="polite">Loading preview&hellip;</span>
                    <div class="d-flex align-items-center gap-2">
                        <span id="cn-batch-status" class="text-muted small" aria-live="polite"></span>
                        <button type="submit" form="cn-batch-form" class="<?= btn('generate') ?>" id="cn-batch-btn">
                            <i class="bi bi-printer" aria-hidden="true"></i> <span>Generate cards</span>
                        </button>
                    </div>
                </div>

                <div class="table-responsive mt-2">
                    <table class="table table-sm align-middle mb-0" id="cn-preview-table">
                        <thead>
                            <tr><th scope="col">Control #</th><th scope="col">Head name</th><th scope="col">Barangay</th></tr>
                        </thead>
                        <tbody id="cn-preview-body">
                            <tr><td colspan="3" class="sector-empty-state">Loading&hellip;</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SINGLE -->
            <div id="cn-panel-single" hidden>
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-8 cn-typeahead">
                        <label class="form-label" for="cn-head">Head</label>
                        <input type="text" class="form-control" id="cn-head" placeholder="Type a head name&hellip;" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="cn-head-list">
                        <ul class="list-group cn-typeahead-list shadow-sm" id="cn-head-list" hidden></ul>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="cn-control">Control #</label>
                        <input type="number" min="1" inputmode="numeric" class="form-control" id="cn-control" placeholder="Exact control number">
                    </div>
                    <div class="col-12">
                        <span class="text-muted small">Pick a head from the list OR type an exact control number, then Generate.</span>
                    </div>
                    <div class="col-12 d-flex justify-content-end align-items-center gap-2">
                        <span id="cn-single-status" class="text-muted small" aria-live="polite"></span>
                        <button type="button" class="<?= btn('generate') ?>" id="cn-single-btn">
                            <i class="bi bi-printer" aria-hidden="true"></i> <span>Generate card</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#control-numbers-page .cn-typeahead { position: relative; }
#control-numbers-page .cn-typeahead-list {
    position: absolute; width: 100%; z-index: 5;
    max-height: 16rem; overflow-y: auto;
}
#control-numbers-page .cn-typeahead-list .list-group-item { cursor: pointer; }
</style>

<script>
(function () {
    const maxQuantity = <?= (int) config('QrCardSettings')->maxQuantity ?>;
    const headsUrl    = '<?= site_url('admin/cards/heads') ?>';
    const generateUrl = '<?= site_url('admin/cards/generate') ?>';
    const cardUrlBase = '<?= site_url('admin/cards/card') ?>';

    // ---- mode switch ------------------------------------------------------
    const modeBtns = document.querySelectorAll('#cn-modes [data-mode]');
    const panels = { batch: document.getElementById('cn-panel-batch'), single: document.getElementById('cn-panel-single') };
    modeBtns.forEach((b) => b.addEventListener('click', function () {
        modeBtns.forEach((x) => { x.classList.remove('active'); x.removeAttribute('aria-current'); });
        b.classList.add('active'); b.setAttribute('aria-current', 'page');
        const mode = b.dataset.mode;
        panels.batch.hidden = mode !== 'batch';
        panels.single.hidden = mode !== 'single';
    }));

    // ---- shared helpers ---------------------------------------------------
    function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

    async function download(resp, fallback) {
        const blob = await resp.blob();
        const disposition = resp.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="([^"]+)"/);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = match ? match[1] : fallback;
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
    }

    // ---- batch: live preview ---------------------------------------------
    const batchForm = document.getElementById('cn-batch-form');
    const countEl = document.getElementById('cn-preview-count');
    const bodyEl = document.getElementById('cn-preview-body');
    const batchBtn = document.getElementById('cn-batch-btn');
    const batchStatus = document.getElementById('cn-batch-status');
    let debounce;

    async function refreshPreview() {
        const params = new URLSearchParams({
            barangay: document.getElementById('cn-barangay').value,
            from: document.getElementById('cn-from').value,
            to: document.getElementById('cn-to').value,
        });
        try {
            const resp = await fetch(headsUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!resp.ok) { throw new Error('preview'); }
            const data = await resp.json();
            const count = data.count || 0;
            const rows = data.rows || [];

            if (count === 0) {
                countEl.textContent = 'No heads match — adjust filters.';
                bodyEl.innerHTML = '<tr><td colspan="3" class="sector-empty-state">No heads match — adjust filters.</td></tr>';
                batchBtn.disabled = true;
                return;
            }
            if (count > maxQuantity) {
                countEl.textContent = count + ' cards match, exceeding the max of ' + maxQuantity + ' per batch. Narrow the filters.';
                batchBtn.disabled = true;
            } else {
                countEl.textContent = count + (count === 1 ? ' card will be generated.' : ' cards will be generated.');
                batchBtn.disabled = false;
            }
            let html = rows.map((r) =>
                '<tr><td>' + esc(String(r.controlNo)) + '</td><td>' + esc(r.name) + '</td><td>' + esc(r.barangay) + '</td></tr>'
            ).join('');
            if (count > rows.length) {
                html += '<tr><td colspan="3" class="text-muted small">…and ' + (count - rows.length) + ' more</td></tr>';
            }
            bodyEl.innerHTML = html;
        } catch (e) {
            countEl.textContent = 'Preview unavailable.';
            bodyEl.innerHTML = '<tr><td colspan="3" class="sector-empty-state">Preview unavailable.</td></tr>';
            batchBtn.disabled = true;
        }
    }

    ['cn-barangay', 'cn-from', 'cn-to'].forEach((id) => {
        document.getElementById(id).addEventListener('input', function () {
            clearTimeout(debounce); debounce = setTimeout(refreshPreview, 300);
        });
    });
    refreshPreview();

    batchForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        batchStatus.textContent = 'Generating…';
        batchBtn.disabled = true;
        try {
            const resp = await fetch(generateUrl, {
                method: 'POST', body: new FormData(batchForm),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const fresh = resp.headers.get('X-CSRF-TOKEN');
            if (fresh) { const h = batchForm.querySelector('input[type="hidden"]'); if (h) { h.value = fresh; } }
            if (!resp.ok) {
                const text = await resp.text();
                let msg = 'Generation failed.';
                try { msg = JSON.parse(text).error || msg; } catch (_) {}
                batchStatus.textContent = msg;
                return;
            }
            await download(resp, 'binan-qr-cards.pdf');
            batchStatus.textContent = 'Done.';
        } catch (err) {
            batchStatus.textContent = 'Generation failed. Please try again.';
        } finally {
            refreshPreview();
        }
    });

    // ---- single: autocomplete + generate ---------------------------------
    const headInput = document.getElementById('cn-head');
    const headList = document.getElementById('cn-head-list');
    const controlInput = document.getElementById('cn-control');
    const singleBtn = document.getElementById('cn-single-btn');
    const singleStatus = document.getElementById('cn-single-status');
    let selectedHead = null;
    let acDebounce;

    function clearList() { headList.innerHTML = ''; headList.hidden = true; headInput.setAttribute('aria-expanded', 'false'); }

    headInput.addEventListener('input', function () {
        selectedHead = null;
        const q = headInput.value.trim();
        clearTimeout(acDebounce);
        if (q.length < 2) { clearList(); return; }
        acDebounce = setTimeout(async function () {
            try {
                const resp = await fetch(headsUrl + '?mode=search&q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!resp.ok) { clearList(); return; }
                const data = await resp.json();
                const rows = data.rows || [];
                if (rows.length === 0) { clearList(); return; }
                headList.innerHTML = rows.map((r) =>
                    '<li class="list-group-item list-group-item-action" role="option" ' +
                    'data-id="' + esc(String(r.memberID)) + '">' +
                    esc(r.name) + ' <span class="text-muted small">#' + esc(String(r.controlNo)) +
                    (r.barangay ? ' · ' + esc(r.barangay) : '') + '</span></li>'
                ).join('');
                headList.hidden = false; headInput.setAttribute('aria-expanded', 'true');
            } catch (e) { clearList(); }
        }, 300);
    });

    headList.addEventListener('click', function (e) {
        const li = e.target.closest('[data-id]');
        if (!li) { return; }
        selectedHead = parseInt(li.dataset.id, 10);
        headInput.value = li.textContent.trim();
        controlInput.value = '';
        clearList();
    });

    document.addEventListener('click', function (e) {
        if (!headList.contains(e.target) && e.target !== headInput) { clearList(); }
    });

    singleBtn.addEventListener('click', async function () {
        singleStatus.textContent = '';
        let memberId = selectedHead;

        // Exact control number path: resolve to a head via the heads feed (range
        // collapsed to the single control_no) before hitting the single-card route,
        // so a bad number fails with a clear message instead of a 404 download.
        // control_no is the paper QR number, not necessarily the memberID, so we
        // must look it up rather than assume control === memberID.
        if (!memberId) {
            const control = controlInput.value.trim();
            if (!control) { singleStatus.textContent = 'Pick a head or enter a control number.'; return; }
            try {
                const resp = await fetch(headsUrl + '?from=' + encodeURIComponent(control) + '&to=' + encodeURIComponent(control), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await resp.json();
                const hit = (data.rows || []).find((r) => String(r.controlNo) === control);
                if (!hit) { singleStatus.textContent = 'No head found for control number ' + control + '.'; return; }
                memberId = hit.memberID;
            } catch (e) { singleStatus.textContent = 'Lookup failed. Try again.'; return; }
        }

        singleStatus.textContent = 'Generating…';
        singleBtn.disabled = true;
        try {
            const resp = await fetch(cardUrlBase + '/' + memberId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!resp.ok) { singleStatus.textContent = 'Generation failed.'; return; }
            await download(resp, 'binan-qr-card.pdf');
            singleStatus.textContent = 'Done.';
        } catch (e) {
            singleStatus.textContent = 'Generation failed. Please try again.';
        } finally {
            singleBtn.disabled = false;
        }
    });
})();
</script>
