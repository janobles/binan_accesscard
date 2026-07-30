<?php
/**
 * QR access card issuing page (Admin > Cards).
 *
 * Self-contained: the layout includes it with no data, and the barangay list is read
 * from FamilyProfilingFormV2 so the filter values always match what headsForCards()
 * compares against. Two modes share the page: Batch, which takes a barangay and a
 * control-number range and previews the heads it would print, and Single card, which
 * takes one searchable head or an exact control number. The PDF is the real output;
 * the table only previews who lands in it.
 *
 * Layout constraint worth knowing before editing: the segmented tabs must not sit
 * inside a flex wrapper such as .records-scroll-panel, or the inline-flex tab track
 * stretches to full width.
 */

helper('ui');
$barangayList = \App\Support\FamilyProfilingFormV2::barangays();
?>
<p class="text-muted small mb-3">Issue printable QR access cards for registered heads of family. The PDF is the output; the table below previews who will be printed.</p>

<ul class="nav nav-pills segmented-tabs mb-3" id="cn-modes" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" type="button" data-mode="batch" aria-current="page">
            Batch
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" type="button" data-mode="single">
            Single card
        </button>
    </li>
</ul>

<!-- BATCH -->
<div class="card mb-4 sector-management" id="cn-card-batch">
    <div class="card-header">
        <span><i class="bi bi-collection me-1" aria-hidden="true"></i>Batch generation</span>
    </div>
    <div class="card-body">
        <form id="cn-batch-form" class="row g-3 align-items-end" autocomplete="off">
            <?= csrf_field() ?>
            <div class="col-12 col-md">
                <label class="form-label" for="cn-barangay">Barangay</label>
                <select class="form-select" id="cn-barangay" name="barangay">
                    <option value="">All barangays</option>
                    <?php foreach ($barangayList as $barangay): ?>
                        <option value="<?= esc($barangay, 'attr') ?>"><?= esc($barangay) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="cn-from">From #</label>
                <input type="number" min="1" inputmode="numeric" class="form-control" id="cn-from" name="from" placeholder="e.g. 100">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="cn-to">To #</label>
                <input type="number" min="1" inputmode="numeric" class="form-control" id="cn-to" name="to" placeholder="e.g. 150">
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="<?= btn('generate') ?>" id="cn-batch-btn">
                    <i class="bi bi-printer" aria-hidden="true"></i> Generate cards
                </button>
            </div>
        </form>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
            <span class="text-muted small">Leave all blank to print every active head. Both range bounds are inclusive.</span>
            <span id="cn-batch-status" class="small text-muted" aria-live="polite"></span>
        </div>

        <div class="mt-3">
            <?php /* No page search here: Generate cards prints the whole
                     barangay + range selection, so a keyword that narrowed only
                     the preview would misstate what gets printed. */ ?>
            <?= view('components/table_controls', [
                'showSearch' => false,
                'sizeId' => 'cn-per-page',
                'sizeAction' => null,
                'perPage' => 25,
                'perPageOptions' => [10 => '10', 25 => '25', 50 => '50', 100 => '100'],
            ]) ?>
        </div>

        <div class="table-responsive">
            <table class="table manage-record-table align-middle w-100 mb-0" id="cn-preview-table">
                <thead>
                    <tr><th scope="col">Control #</th><th scope="col">Head name</th><th scope="col">Barangay</th></tr>
                </thead>
                <tbody id="cn-preview-body">
                    <tr><td colspan="3" class="text-muted">Loading&hellip;</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer small text-muted">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100">
            <div class="table-footer-left"><span id="cn-preview-count" aria-live="polite">Loading preview&hellip;</span></div>
            <div class="table-footer-right" id="cn-preview-paging"></div>
        </div>
    </div>
</div>

<!-- SINGLE -->
<div class="card mb-4 sector-management" id="cn-card-single" hidden>
    <div class="card-header">
        <span><i class="bi bi-person-vcard me-1" aria-hidden="true"></i>Single card</span>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md cn-typeahead">
                <label class="form-label" for="cn-head">Head</label>
                <input type="text" class="form-control" id="cn-head" placeholder="Type a head name&hellip;" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="cn-head-list">
                <ul class="list-group cn-typeahead-list shadow-sm" id="cn-head-list" hidden></ul>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="cn-control">Control #</label>
                <input type="number" min="1" inputmode="numeric" class="form-control" id="cn-control" placeholder="Exact number">
            </div>
            <div class="col-6 col-md-auto">
                <button type="button" class="<?= btn('generate') ?>" id="cn-single-btn">
                    <i class="bi bi-printer" aria-hidden="true"></i> Generate card
                </button>
            </div>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
            <span class="text-muted small">Pick a head from the list OR type an exact control number, then Generate.</span>
        </div>
    </div>
</div>

<style>
#cn-modes .nav-link { cursor: pointer; }
.cn-typeahead { position: relative; }
.cn-typeahead-list {
    position: absolute; width: 100%; z-index: 5;
    max-height: 16rem; overflow-y: auto;
}
.cn-typeahead-list .list-group-item { cursor: pointer; }
</style>

<script>
(function () {
    const maxQuantity = <?= (int) config('QrCardSettings')->maxQuantity ?>;
    const headsUrl    = '<?= site_url('admin/cards/heads') ?>';
    const generateUrl = '<?= site_url('admin/cards/generate') ?>';
    const cardUrlBase = '<?= site_url('admin/cards/card') ?>';

    // ---- mode switch ------------------------------------------------------
    const modeBtns = document.querySelectorAll('#cn-modes [data-mode]');
    const cards = { batch: document.getElementById('cn-card-batch'), single: document.getElementById('cn-card-single') };
    modeBtns.forEach((b) => b.addEventListener('click', function () {
        modeBtns.forEach((x) => { x.classList.remove('active'); x.removeAttribute('aria-current'); });
        b.classList.add('active'); b.setAttribute('aria-current', 'page');
        const mode = b.dataset.mode;
        cards.batch.hidden = mode !== 'batch';
        cards.single.hidden = mode !== 'single';
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
    const pagingEl = document.getElementById('cn-preview-paging');
    const perPageEl = document.getElementById('cn-per-page');
    let debounce;
    // The preview is paged by the server (admin/cards/heads), so the whole
    // selection stays available without shipping every row to the browser.
    let previewPage = 1;

    async function refreshPreview() {
        const params = new URLSearchParams({
            barangay: document.getElementById('cn-barangay').value,
            from: document.getElementById('cn-from').value,
            to: document.getElementById('cn-to').value,
            page: String(previewPage),
            per_page: perPageEl ? perPageEl.value : '25',
        });
        try {
            const resp = await fetch(headsUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!resp.ok) { throw new Error('preview'); }
            const data = await resp.json();
            const count = data.count || 0;
            const rows = data.rows || [];
            const page = data.page || 1;
            const totalPages = data.totalPages || 1;
            previewPage = page;
            const perPage = data.perPage || 25;
            const from = (page - 1) * perPage;

            if (count === 0) {
                countEl.textContent = 'No heads match. Adjust the filters.';
                bodyEl.innerHTML = '<tr><td colspan="3" class="text-muted">No heads match the current filters.</td></tr>';
                pagingEl.textContent = '';
                batchBtn.disabled = true;
                return;
            }
            if (count > maxQuantity) {
                batchStatus.textContent = count + ' cards match, over the max of ' + maxQuantity + ' per batch. Narrow the filters.';
                batchBtn.disabled = true;
            } else {
                batchStatus.textContent = count + (count === 1 ? ' card will be generated.' : ' cards will be generated.');
                batchBtn.disabled = false;
            }
            countEl.textContent = 'Showing ' + (from + 1) + ' to ' + (from + rows.length) + ' of ' + count + ' cards';
            bodyEl.innerHTML = rows.map((r) =>
                '<tr><td>' + esc(String(r.controlNo)) + '</td><td>' + esc(r.name) + '</td><td>' + esc(r.barangay) + '</td></tr>'
            ).join('');
            // Same footer markup as every other list (table-paginate.js).
            window.renderTablePaging(pagingEl, page, totalPages);
        } catch (e) {
            countEl.textContent = 'Preview unavailable.';
            bodyEl.innerHTML = '<tr><td colspan="3" class="text-muted">Preview unavailable.</td></tr>';
            pagingEl.textContent = '';
            batchBtn.disabled = true;
        }
    }

    function reloadFromFirstPage() {
        previewPage = 1;
        refreshPreview();
    }

    pagingEl.addEventListener('click', function (event) {
        const link = event.target.closest('[data-paginate-page]');
        event.preventDefault();
        if (!link || link.closest('.page-item').classList.contains('disabled')) {
            return;
        }
        previewPage = parseInt(link.dataset.paginatePage, 10) || 1;
        refreshPreview();
    });

    if (perPageEl) {
        perPageEl.addEventListener('change', reloadFromFirstPage);
    }

    ['cn-barangay', 'cn-from', 'cn-to'].forEach((id) => {
        document.getElementById(id).addEventListener('input', function () {
            clearTimeout(debounce); debounce = setTimeout(reloadFromFirstPage, 300);
        });
    });
    refreshPreview();

    batchForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        window.showToast('Generating…', 'primary');
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
                window.showToast(msg, 'danger');
                return;
            }
            await download(resp, 'binan-qr-cards.pdf');
            window.showToast('Cards generated.', 'success');
        } catch (err) {
            window.showToast('Generation failed. Please try again.', 'danger');
        } finally {
            refreshPreview();
        }
    });

    // ---- single: autocomplete + generate ---------------------------------
    const headInput = document.getElementById('cn-head');
    const headList = document.getElementById('cn-head-list');
    const controlInput = document.getElementById('cn-control');
    const singleBtn = document.getElementById('cn-single-btn');
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
        let memberId = selectedHead;

        // Exact control number path: resolve to a head via the heads feed (range
        // collapsed to the single control_no) before hitting the single-card route,
        // so a bad number fails with a clear message instead of a 404 download.
        // control_no is the paper QR number, not necessarily the memberID, so we
        // must look it up rather than assume control === memberID.
        if (!memberId) {
            const control = controlInput.value.trim();
            if (!control) { window.showToast('Pick a head or enter a control number.', 'warning'); return; }
            try {
                const resp = await fetch(headsUrl + '?from=' + encodeURIComponent(control) + '&to=' + encodeURIComponent(control), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await resp.json();
                const hit = (data.rows || []).find((r) => String(r.controlNo) === control);
                if (!hit) { window.showToast('No head found for control number ' + control + '.', 'warning'); return; }
                memberId = hit.memberID;
            } catch (e) { window.showToast('Lookup failed. Try again.', 'danger'); return; }
        }

        window.showToast('Generating…', 'primary');
        singleBtn.disabled = true;
        try {
            const resp = await fetch(cardUrlBase + '/' + memberId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!resp.ok) { window.showToast('Generation failed.', 'danger'); return; }
            await download(resp, 'binan-qr-card.pdf');
            window.showToast('Card generated.', 'success');
        } catch (e) {
            window.showToast('Generation failed. Please try again.', 'danger');
        } finally {
            singleBtn.disabled = false;
        }
    });
})();
</script>
