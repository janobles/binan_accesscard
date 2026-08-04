// Live eligibility count for the New Batch modal (Admin/batch-create-modal.php).
// Fires GET distribution/batches/preview on barangay/sector filter changes,
// debounced 300ms, and writes the result into [data-eligible-count]. Also
// blocks the submit button while a count is zero, since a batch covering no
// families is almost always a filter mistake, not an intentional choice.
//
// Connected to:
//   - View   : Admin/batch-create-modal.php - #batchBarangayIds, #batchSectorIds,
//              #eligiblePreview, #newBatchSubmit
//   - Backend: GET distribution/batches/preview
(function (window, document) {
    var PREVIEW_URL = window.batchPreviewUrl || null;
    var debounceTimer = null;
    var currentRequest = 0;

    function selectedValues(select) {
        return Array.prototype.slice.call(select.selectedOptions || []).map(function (opt) {
            return opt.value;
        });
    }

    function buildQuery(barangaySelect, sectorSelect) {
        var params = new URLSearchParams();
        selectedValues(barangaySelect).forEach(function (v) { params.append('barangay_ids[]', v); });
        selectedValues(sectorSelect).forEach(function (v) { params.append('sector_ids[]', v); });
        return params.toString();
    }

    function setCount(countEl, submitBtn, text, blockSubmit) {
        countEl.textContent = text;
        if (submitBtn) {
            submitBtn.disabled = blockSubmit;
        }
    }

    function refreshCount(form) {
        var barangaySelect = form.querySelector('#batchBarangayIds');
        var sectorSelect = form.querySelector('#batchSectorIds');
        var countEl = form.querySelector('[data-eligible-count]');
        var submitBtn = form.querySelector('#newBatchSubmit');

        if (!barangaySelect || !sectorSelect || !countEl || !PREVIEW_URL) {
            return;
        }

        // Checking while the fetch is in flight, not the last known number,
        // so the admin never confirms against a stale count from a prior filter.
        setCount(countEl, submitBtn, 'checking...', true);

        var requestId = ++currentRequest;
        var url = PREVIEW_URL + '?' + buildQuery(barangaySelect, sectorSelect);

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) { throw new Error('preview request failed'); }
                return response.json();
            })
            .then(function (data) {
                if (requestId !== currentRequest) { return; }
                var eligible = parseInt(data && data.eligible, 10) || 0;
                setCount(countEl, submitBtn, String(eligible) + ' families', eligible === 0);
            })
            .catch(function () {
                if (requestId !== currentRequest) { return; }
                // Unknown, not zero and not the last good number - either would
                // mislead the admin about the denominator they are about to confirm.
                setCount(countEl, submitBtn, 'unable to check right now', true);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('newBatchForm');
        if (!form) { return; }

        ['#batchBarangayIds', '#batchSectorIds'].forEach(function (selector) {
            var select = form.querySelector(selector);
            if (!select) { return; }

            select.addEventListener('change', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () { refreshCount(form); }, 300);
            });
        });
    });

    document.addEventListener('shown.bs.modal', function (event) {
        if (event.target && event.target.id === 'newBatchModal') {
            refreshCount(document.getElementById('newBatchForm'));
        }
    });
})(window, document);
