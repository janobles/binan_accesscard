// Smoke test for public/assets/js/dashboard/scanner-reports.js's stationsTable
// repaint, the function window.ReportsCharts.update() now uses to rebuild
// #stationsTable's rows from a fresh distribution/reports/stats payload.
//
// This pins the property a retired test used to cover for the old squares
// grid (testLivePollRebuildsSquaresWithTheSameRoleGate, tests/unit/
// DashboardSubstanceViewTest.php): a live-poll repaint must never hand a role
// a control the server withheld. #stationsTable carries data-can-drill-in,
// exactly as Admin/batch-stations-table.php renders it, and a rebuilt row may
// carry data-scanner-id only when that attribute reads "1" - never for a
// role the table itself marked as not allowed to drill in, regardless of
// which scanner the row is for.
//
// This repo has no JS test harness (no package.json, no jsdom) - see
// tests/js/entry-page-gate.smoke.mjs for the pattern this follows and the
// notes at its bottom about how it runs. No delegated/bubbling events are
// exercised here (stationsTable() is called synchronously from update()), so
// this fixture's fake DOM is a plain tree, no click dispatch needed.
//
// Run with: node tests/js/scanner-reports-stations.smoke.mjs
// Exits non-zero (and prints the failure) if the script throws on load, if a
// role marked unable to drill in ever gets a data-scanner-id row, if a role
// marked able to drill in does not get one for every non-TOTAL row, or if the
// TOTAL row is not last.

import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

class FakeNode {
    constructor(tag) {
        this.tagName = tag ? tag.toUpperCase() : null;
        this.nodeType = 1;
        this._attrs = {};
        this.children = [];
        this.parentNode = null;
        this.value = '';
        this._text = '';
        this.className = '';
    }

    get textContent() { return this._text; }
    set textContent(v) { this._text = v; this.children = []; }

    setAttribute(name, value) { this._attrs[name] = String(value); if (name === 'id') { this.id = value; } }
    getAttribute(name) { return name in this._attrs ? this._attrs[name] : null; }
    removeAttribute(name) { delete this._attrs[name]; }
    hasAttribute(name) { return name in this._attrs; }

    appendChild(child) { child.parentNode = this; this.children.push(child); return child; }
    removeChild(child) {
        const at = this.children.indexOf(child);
        if (at !== -1) { this.children.splice(at, 1); }
        child.parentNode = null;
        return child;
    }
    get firstChild() { return this.children[0] || null; }

    // Only what stationsTable() actually needs: find the one <tbody> child.
    querySelector(selector) {
        if (selector !== 'tbody') { throw new Error('fixture only supports querySelector("tbody")'); }
        return this.children.find((c) => c.tagName === 'TBODY') || null;
    }
}

function el(tag, attrs = {}) {
    const node = new FakeNode(tag);
    for (const [k, v] of Object.entries(attrs)) { node.setAttribute(k, v); }
    return node;
}

const tbody = new FakeNode('tbody');
const stationsTable = el('table', { id: 'stationsTable', 'data-can-drill-in': '1' });
stationsTable.appendChild(tbody);

const reportsData = el('script', { id: 'reportsData' });
reportsData.textContent = JSON.stringify({ coverage: { eligible: 0, served: 0, remaining: 0, coverage: 0, voided: 0 } });

const allNodes = [stationsTable, tbody, reportsData];

const documentNode = {
    nodeType: 9,
    createElement(tag) { const n = new FakeNode(tag); allNodes.push(n); return n; },
    getElementById(id) { return allNodes.find((n) => n.id === id) || null; },
    // Only the download button's binding calls this, and this fixture has no
    // such button - nothing to click, so a permanent null is correct.
    querySelector() { return null; },
};

const fakeWindow = {};
fakeWindow.window = fakeWindow;
fakeWindow.document = documentNode;
fakeWindow.Chart = function FakeChart() { /* never constructed: no canvases in this fixture */ };
fakeWindow.getComputedStyle = function () { return { getPropertyValue: function () { return ''; } }; };

const script = readFileSync(new URL('../../public/assets/js/dashboard/scanner-reports.js', import.meta.url), 'utf8');

let threw = null;
try {
    vm.runInNewContext(script, fakeWindow, { filename: 'scanner-reports.js' });
} catch (error) {
    threw = error;
}

assert.equal(threw, null, 'scanner-reports.js must not throw on load: ' + (threw && threw.stack));
assert.notEqual(fakeWindow.ReportsCharts, undefined, 'window.ReportsCharts must be defined after load');

const byScanner = [
    { userID: 1, scanner: 'Scanner1', families: 10, handouts: 10, pace: 2, typicalSeconds: 90, firstTs: 1735700000, lastTs: 1735710000, idleSeconds: 120, bestHour: 9, share: 0.5 },
    { userID: 2, scanner: 'Scanner2', families: 8, handouts: 8, pace: null, typicalSeconds: null, firstTs: null, lastTs: null, idleSeconds: 0, bestHour: null, share: 0.4 },
    { userID: 0, scanner: 'TOTAL', families: 18, handouts: 18, pace: 2, typicalSeconds: 90, firstTs: 1735700000, lastTs: 1735710000, idleSeconds: 120, bestHour: 9, share: 1 },
];

// --- data-can-drill-in="1": every non-TOTAL row must carry data-scanner-id,
// the TOTAL row must not, and TOTAL must render last. ---
fakeWindow.ReportsCharts.update({ byScanner });

assert.equal(tbody.children.length, 3, 'update() should rebuild one row per scanner plus TOTAL.');
assert.equal(tbody.children[0].getAttribute('data-scanner-id'), '1', 'A drillable role must get data-scanner-id on a real station row.');
assert.equal(tbody.children[0].getAttribute('data-scanner-name'), 'Scanner1', 'The row must also carry data-scanner-name.');
assert.equal(tbody.children[1].getAttribute('data-scanner-id'), '2', 'Every non-TOTAL row must carry data-scanner-id when the role may drill in.');
assert.equal(tbody.children[2].hasAttribute('data-scanner-id'), false, 'The TOTAL row must never carry data-scanner-id, drillable role or not.');
assert.equal(tbody.children[2].className, 'is-total', 'The TOTAL row must carry .is-total.');
assert.equal(tbody.children[2], tbody.children[tbody.children.length - 1], 'The TOTAL row must render last.');

// --- data-can-drill-in="0": no row, TOTAL or otherwise, may carry
// data-scanner-id. This is the property the retired
// testLivePollRebuildsSquaresWithTheSameRoleGate pinned for the squares grid;
// stationsTable() must hold it for the table that replaced it. ---
stationsTable.setAttribute('data-can-drill-in', '0');
fakeWindow.ReportsCharts.update({ byScanner });

assert.equal(tbody.children.length, 3, 'A repaint under the closed gate must still render every row.');
for (const row of tbody.children) {
    assert.equal(row.hasAttribute('data-scanner-id'), false, 'No row may carry data-scanner-id while data-can-drill-in="0", including a real scanner\'s row.');
    assert.equal(row.hasAttribute('data-scanner-name'), false, 'No row may carry data-scanner-name while data-can-drill-in="0".');
}

console.log('OK: the stations repaint reads data-can-drill-in fresh on every call, gates data-scanner-id/data-scanner-name on it exactly, and keeps the TOTAL row last.');
