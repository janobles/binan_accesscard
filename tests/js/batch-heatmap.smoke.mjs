// Smoke test for public/assets/js/dashboard/batch-heatmap.js: window.BatchHeatmap
// must select a day from either control without letting one write the other,
// recompute the four KPI cards from the #reportsData payload (never scrape the
// DOM), fall back to all days when the selected day is missing from a fresh
// payload, and preserve the current selection across a render(). This repo has
// no JS test harness (no package.json, no jsdom) - see
// tests/js/entry-page-gate.smoke.mjs for the pattern this follows and the notes
// at its bottom about how it runs. Rather than add a dependency, this builds a
// minimal DOM by hand, just enough to exercise the module.
//
// Unlike entry-page-gate.smoke.mjs's fixture, batch-heatmap.js delegates its
// listeners (grid clicks, strip clicks) from ancestor elements rather than
// binding to each row - the whole point of delegation is that a rebuilt row
// still answers a click without a fresh bind. So this fixture's fake DOM
// implements click bubbling (dispatch() walks the node up through parentNode,
// invoking each ancestor's listeners for the event type), which
// entry-page-gate's fixture does not need and does not have.
//
// Run with: node tests/js/batch-heatmap.smoke.mjs
// Exits non-zero (and prints the failure) if the script throws during load, if
// selecting a day from either control fails to update the other, the row's
// aria-pressed, or the four KPI values, if an unknown day is not rejected back
// to "all days", or if render() does not preserve the current selection.

import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

function makeClassList(node) {
    return {
        contains: (c) => node._classes.has(c),
        add: (...cs) => cs.forEach((c) => node._classes.add(c)),
        remove: (...cs) => cs.forEach((c) => node._classes.delete(c)),
        toggle: (c, force) => {
            const has = node._classes.has(c);
            const next = force === undefined ? !has : force;
            if (next) { node._classes.add(c); } else { node._classes.delete(c); }
            return next;
        },
    };
}

// Single simple selector only (tag, #id, .class, [attr] / [attr="value"], any
// combination of those on one element) - no descendant combinators. Every call
// site in batch-heatmap.js is either scoped to a specific root
// (card.querySelectorAll('[data-strip-pane]')) or a bare tag/class
// ('button.heatmap-day', 'tbody'), so a combinator is never needed.
function parseSimpleSelector(selector) {
    const s = selector.trim();
    let i = 0;
    let tag = null;
    const id = { value: null };
    const classes = [];
    const attrs = [];

    const tagMatch = s.slice(i).match(/^[A-Za-z][\w-]*/);
    if (tagMatch) {
        tag = tagMatch[0].toUpperCase();
        i += tagMatch[0].length;
    }

    while (i < s.length) {
        if (s[i] === '#') {
            const m = s.slice(i + 1).match(/^[\w-]+/);
            id.value = m[0];
            i += 1 + m[0].length;
        } else if (s[i] === '.') {
            const m = s.slice(i + 1).match(/^[\w-]+/);
            classes.push(m[0]);
            i += 1 + m[0].length;
        } else if (s[i] === '[') {
            const close = s.indexOf(']', i);
            const inner = s.slice(i + 1, close);
            const eq = inner.indexOf('=');
            if (eq === -1) {
                attrs.push([inner, null]);
            } else {
                attrs.push([inner.slice(0, eq), inner.slice(eq + 1).replace(/^"|"$/g, '')]);
            }
            i = close + 1;
        } else {
            throw new Error('Unsupported selector fragment: ' + selector);
        }
    }

    return { tag, id: id.value, classes, attrs };
}

function matchesSimple(node, parsed) {
    if (node.nodeType !== 1) { return false; }
    if (parsed.tag && node.tagName !== parsed.tag) { return false; }
    if (parsed.id && node.id !== parsed.id) { return false; }
    for (const c of parsed.classes) {
        if (!node._classes.has(c)) { return false; }
    }
    for (const [name, value] of parsed.attrs) {
        if (!(name in node._attrs)) { return false; }
        if (value !== null && node._attrs[name] !== value) { return false; }
    }
    return true;
}

function walk(node, cb) {
    for (const child of node.children) {
        cb(child);
        walk(child, cb);
    }
}

class FakeNode {
    constructor(tag) {
        this.tagName = tag ? tag.toUpperCase() : null;
        this.nodeType = 1;
        this._attrs = {};
        this._classes = new Set();
        this.children = [];
        this.parentNode = null;
        this._listeners = {};
        this.value = '';
        this._text = '';
    }

    get classList() { return makeClassList(this); }

    get textContent() { return this._text; }
    set textContent(v) { this._text = v; this.children = []; }

    setAttribute(name, value) {
        this._attrs[name] = String(value);
        if (name === 'id') { this.id = value; }
        if (name === 'class') { this._classes = new Set(String(value).split(/\s+/).filter(Boolean)); }
    }

    getAttribute(name) { return name in this._attrs ? this._attrs[name] : null; }
    removeAttribute(name) { delete this._attrs[name]; }
    hasAttribute(name) { return name in this._attrs; }

    appendChild(child) {
        child.parentNode = this;
        this.children.push(child);
        return child;
    }

    removeChild(child) {
        const at = this.children.indexOf(child);
        if (at !== -1) { this.children.splice(at, 1); }
        child.parentNode = null;
        return child;
    }

    get firstChild() { return this.children[0] || null; }

    remove() {
        if (this.parentNode) { this.parentNode.removeChild(this); }
    }

    querySelectorAll(selector) {
        const parsed = parseSimpleSelector(selector);
        const out = [];
        walk(this, (node) => { if (matchesSimple(node, parsed)) { out.push(node); } });
        return out;
    }

    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }

    matches(selector) { return matchesSimple(this, parseSimpleSelector(selector)); }

    closest(selector) {
        const parsed = parseSimpleSelector(selector);
        let node = this;
        while (node && node.nodeType === 1) {
            if (matchesSimple(node, parsed)) { return node; }
            node = node.parentNode;
        }
        return null;
    }

    addEventListener(type, handler) { (this._listeners[type] ||= []).push(handler); }
    removeEventListener(type, handler) {
        const list = this._listeners[type];
        if (list) { this._listeners[type] = list.filter((h) => h !== handler); }
    }

    // Bubbles: fires this node's own listeners for `type`, then walks up
    // through parentNode, firing each ancestor's listeners in turn, ending at
    // the document (which is given the same _listeners/addEventListener
    // shape below). event.target stays fixed at the originating node the
    // whole way up, matching how event.target.closest(...) is used
    // throughout batch-heatmap.js's delegated handlers.
    dispatch(type, init = {}) {
        const event = { type, target: this, preventDefault() {}, ...init };
        let node = this;
        while (node) {
            for (const handler of node._listeners[type] || []) { handler(event); }
            node = node.parentNode || node._parentDocument || null;
        }
    }
}

function el(tag, attrs = {}, children = []) {
    const node = new FakeNode(tag);
    for (const [k, v] of Object.entries(attrs)) { node.setAttribute(k, v); }
    children.forEach((c) => node.appendChild(c));
    return node;
}

// --- Build the fixture: a trimmed batch-overview.php + batch-heatmap.php,
// one KPI row, #dayPick with two days, and a two-day #peakHeatmap. ---

function metricNode(key) {
    const value = el('p', { class: 'kpi-value', 'data-metric': key });
    const sub = el('p', { class: 'kpi-sub', 'data-metric-sub': key });
    return { value, sub };
}

const eligible = metricNode('eligible');
const served = metricNode('served');
const peakHour = metricNode('peakHour');
const scannersActive = metricNode('scannersActive');

const dayOption0 = el('option', { value: '' });
const dayOption1 = el('option', { value: '2026-08-01' });
const dayOption2 = el('option', { value: '2026-08-02' });
const dayPick = el('select', { id: 'dayPick' }, [dayOption0, dayOption1, dayOption2]);

function heatmapRow(day, label, selected) {
    const button = el('button', { class: 'heatmap-day', 'data-day': day, 'aria-pressed': selected ? 'true' : 'false' });
    button.textContent = label;
    const th = el('th', {}, [button]);
    const row = el('tr', selected ? { class: 'is-selected' } : {}, [th]);
    return { row, button };
}

const day1Row = heatmapRow('2026-08-01', 'Aug 1 (Day 1)', false);
const day2Row = heatmapRow('2026-08-02', 'Aug 2 (Day 2)', false);
const heatmapTbody = el('tbody', {}, [day1Row.row, day2Row.row]);
const heatmapTable = el('table', { id: 'peakHeatmap' }, [heatmapTbody]);

const kpiRow = el('div', {}, [eligible.value, eligible.sub, served.value, served.sub, peakHour.value, peakHour.sub, scannersActive.value, scannersActive.sub]);

// The Stations card's strip, id'd exactly as Admin/batch-overview.php
// renders it: #stationsTable inside the All pane (data-batch/data-can-drill-in,
// what renderStationsDayPane() reads for the Per day table) and
// #stations-pane-day starting with the "no day picked" hint, matching the
// server's own initial render for a page that loaded with no ?day=.
const stripTabAll = el('button', { type: 'button', class: 'nav-link active', 'data-strip-target': 'all', 'aria-selected': 'true' });
const stripTabDay = el('button', { type: 'button', class: 'nav-link', 'data-strip-target': 'day', 'aria-selected': 'false' });
const stationsAllTable = el('table', { id: 'stationsTable', 'data-batch': '7', 'data-can-drill-in': '1' });
const paneAll = el('div', { 'data-strip-pane': 'all' }, [stationsAllTable]);
const dayHint = el('p', { id: 'stationsDayHint', class: 'text-muted mb-0' });
dayHint.textContent = 'Use the Day picker above to choose a day.';
const paneDay = el('div', { id: 'stations-pane-day', 'data-strip-pane': 'day', hidden: '' }, [dayHint]);
const stripCard = el('section', { id: 'stationsCard', 'data-strip': 'all' }, [stripTabAll, stripTabDay, paneAll, paneDay]);

const reportsPayload = {
    coverage: { eligible: 100, served: 60, remaining: 40, coverage: 60, voided: 0 },
    byScanner: [
        { userID: 1, scanner: 'Scanner1', families: 40 },
        { userID: 2, scanner: 'Scanner2', families: 20 },
        { userID: 0, scanner: 'TOTAL', families: 60 },
    ],
    heatmap: {
        days: ['2026-08-01', '2026-08-02'],
        hours: [8, 9, 10],
        cells: {
            '2026-08-01': {
                8: { families: 5, state: 'served' },
                9: { families: 30, state: 'served' },
                10: { families: 0, state: 'empty' },
            },
            '2026-08-02': {
                8: { families: 10, state: 'served' },
                9: { families: 5, state: 'served' },
                10: { families: 10, state: 'served' },
            },
        },
        max: 30,
    },
    byScannerByDay: {
        '2026-08-01': [
            { userID: 1, scanner: 'Scanner1', families: 5, handouts: 5, pace: 1, typicalSeconds: 90, firstTs: 1735700000, lastTs: 1735710000, idleSeconds: 60, bestHour: 9, share: 1 },
            { userID: 0, scanner: 'TOTAL', families: 5, handouts: 5, pace: 1, typicalSeconds: 90, firstTs: 1735700000, lastTs: 1735710000, idleSeconds: 60, bestHour: 9, share: 1 },
        ],
        '2026-08-02': [],
    },
    selectedDay: null,
};

const reportsData = el('script', { id: 'reportsData' });
reportsData.textContent = JSON.stringify(reportsPayload);

const documentNode = {
    nodeType: 9,
    children: [kpiRow, dayPick, heatmapTable, stripCard, reportsData],
    _listeners: {},
    addEventListener(type, handler) { (this._listeners[type] ||= []).push(handler); },
    createElement(tag) { return new FakeNode(tag); },
    querySelectorAll(selector) {
        const parsed = parseSimpleSelector(selector);
        const out = [];
        for (const root of this.children) {
            if (matchesSimple(root, parsed)) { out.push(root); }
            walk(root, (node) => { if (matchesSimple(node, parsed)) { out.push(node); } });
        }
        return out;
    },
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; },
    getElementById(id) {
        for (const root of this.children) {
            if (root.id === id) { return root; }
            const found = root.children && (function find(node) {
                for (const c of node.children) {
                    if (c.id === id) { return c; }
                    const deeper = find(c);
                    if (deeper) { return deeper; }
                }
                return null;
            }(root));
            if (found) { return found; }
        }
        return null;
    },
};

// Every fixture node's bubble chain has to end at the document, the same way
// a real DOM's does, or the strip's document-level delegated listener would
// never fire.
for (const root of documentNode.children) {
    (function attach(node) {
        node._parentDocument = documentNode;
        node.children.forEach(attach);
    }(root));
}

const fakeWindow = {};
fakeWindow.window = fakeWindow;
fakeWindow.document = documentNode;
fakeWindow.location = { href: 'http://localhost/dashboard?view=distribution&batch=7' };
fakeWindow.URL = URL;
const historyCalls = [];
fakeWindow.history = {
    replaceState(state, title, url) { historyCalls.push(url); },
};

const script = readFileSync(new URL('../../public/assets/js/dashboard/batch-heatmap.js', import.meta.url), 'utf8');

let threw = null;
try {
    vm.runInNewContext(script, fakeWindow, { filename: 'batch-heatmap.js' });
} catch (error) {
    threw = error;
}

assert.equal(threw, null, 'batch-heatmap.js must not throw on load: ' + (threw && threw.stack));
assert.notEqual(fakeWindow.BatchHeatmap, undefined, 'window.BatchHeatmap must be defined after load');

// --- All days (initial state): eligible/served come from coverage, peak hour
// is the tallest column across every day (2026-08-01's 9am cell, 30). ---
assert.equal(fakeWindow.BatchHeatmap.selectedDay(), null, 'Initial selection should be "all days" (no ?day= in the fixture URL).');
assert.equal(eligible.value.textContent, '100', 'Eligible should read the batch total.');
assert.equal(eligible.sub.textContent, 'in this batch', 'Eligible sub-line is fixed.');
assert.equal(served.value.textContent, '60', 'Served (all days) should read coverage.served.');
assert.equal(served.sub.textContent, '60% of eligible', 'Served sub-line (all days) should carry the coverage percentage.');
assert.equal(peakHour.value.textContent, '9am - 10am', 'Peak hour (all days) should be the tallest column across every day.');
assert.equal(peakHour.sub.textContent, '35 families', 'Peak hour sub-line should carry that column\'s summed count (2026-08-01\'s 30 + 2026-08-02\'s 5).');
assert.equal(scannersActive.value.textContent, '2', 'Scanners active (all days) should count byScanner rows with userID > 0, excluding TOTAL.');
assert.equal(scannersActive.sub.textContent, 'across the batch', 'Scanners active sub-line (all days) should read "across the batch".');
assert.equal(paneDay.querySelectorAll('table').length, 0, 'The Per day pane must not show a table before any day is picked.');
assert.equal(paneDay.querySelector('#stationsDayHint').textContent, 'Use the Day picker above to choose a day.', 'The Per day pane must open on the "no day picked" hint.');

// --- Selecting a day from the heatmap row header must update #dayPick,
// aria-pressed, the row's is-selected class, the KPIs and the URL - all from
// one function, never dayPick writing the button or vice versa. ---
day1Row.button.dispatch('click');

assert.equal(fakeWindow.BatchHeatmap.selectedDay(), '2026-08-01', 'Clicking a heatmap row header should select that day.');
assert.equal(dayPick.value, '2026-08-01', 'Selecting a day from the heatmap must update #dayPick (one source of truth).');
assert.equal(day1Row.button.getAttribute('aria-pressed'), 'true', 'The selected row\'s header must carry aria-pressed="true".');
assert.equal(day1Row.row._classes.has('is-selected'), true, 'The selected row must carry .is-selected.');
assert.equal(day2Row.button.getAttribute('aria-pressed'), 'false', 'The non-selected row must carry aria-pressed="false".');
assert.equal(served.value.textContent, '35', 'Served (2026-08-01) should sum that day\'s heatmap cells (5 + 30 + 0).');
assert.equal(served.sub.textContent, '60 across the batch', 'Served sub-line (a day picked) should carry the batch total.');
assert.equal(peakHour.value.textContent, '9am - 10am', 'Peak hour (2026-08-01 only) should be that day\'s tallest column.');
assert.equal(peakHour.sub.textContent, '30 families', 'Peak hour sub-line should still carry the count.');
assert.equal(eligible.value.textContent, '100', 'Eligible must never change with the day.');
// Finding 1 (task 13): the PDF's per-day Rollout table counts scanners from
// byScannerByDay, so the screen has to match it rather than repeating the
// batch-wide byScanner fold under a day heading. 2026-08-01 only has one
// non-TOTAL row (userID 1) in byScannerByDay.
assert.equal(scannersActive.value.textContent, '1', 'Scanners active must re-scope to the selected day\'s own byScannerByDay rows, not the batch-wide fold.');
assert.equal(scannersActive.sub.textContent, 'that day', 'Scanners active sub-line must say "that day" once a day is selected.');
assert.ok(historyCalls.length > 0 && historyCalls[historyCalls.length - 1].includes('day=2026-08-01'), 'The URL must carry ?day= after a selection, via replaceState.');

// --- The Per day Stations pane must rebuild into a table for a day that has
// rows, gated by #stationsTable's own data-can-drill-in (here "1"): the
// scanner row carries data-scanner-id, the TOTAL row does not and renders
// last. This is the property Important 2 of the review found missing - the
// pane used to sit frozen at whatever the server rendered at load. ---
assert.equal(paneDay.querySelector('#stationsDayHint'), null, 'The hint must be gone once a day with rows is picked.');
const dayTable = paneDay.querySelector('table');
assert.notEqual(dayTable, null, 'The Per day pane must render a table for a day that has rows.');
assert.equal(dayTable.id, 'stationsTableDay', 'The Per day table must carry its own id, distinct from #stationsTable.');
assert.equal(dayTable.getAttribute('data-batch'), '7', 'The Per day table must carry the batch id read off #stationsTable.');
const dayTbody = dayTable.querySelectorAll('tr').filter((tr) => tr.parentNode.tagName === 'TBODY');
assert.equal(dayTbody.length, 2, 'The Per day table must have one row per scanner in byScannerByDay for that day, TOTAL included.');
assert.equal(dayTbody[0].getAttribute('data-scanner-id'), '1', 'A drillable role must get data-scanner-id on the Per day table too.');
assert.equal(dayTbody[1].hasAttribute('data-scanner-id'), false, 'The TOTAL row must never carry data-scanner-id.');
assert.equal(dayTbody[1], dayTbody[dayTbody.length - 1], 'The TOTAL row must render last in the Per day table.');

// --- Changing #dayPick must drive the same function and move the heatmap's
// pressed state, not just its own value. ---
dayPick.value = '2026-08-02';
dayPick.dispatch('change');

assert.equal(fakeWindow.BatchHeatmap.selectedDay(), '2026-08-02', 'Changing #dayPick should select that day.');
assert.equal(day2Row.button.getAttribute('aria-pressed'), 'true', 'Changing #dayPick must move aria-pressed to the matching row header.');
assert.equal(day1Row.button.getAttribute('aria-pressed'), 'false', 'The previously selected row must lose aria-pressed.');
assert.equal(served.value.textContent, '25', 'Served (2026-08-02) should sum that day\'s cells (10 + 5 + 10).');
assert.equal(scannersActive.value.textContent, '0', 'Scanners active (2026-08-02) must read the day\'s own byScannerByDay rows, which are empty, not the batch-wide fold.');

// --- A day with no rows in byScannerByDay must show the "no scans that day"
// hint, not an empty table and not the "pick a day" hint - all three states
// have to stay distinguishable. ---
assert.equal(paneDay.querySelectorAll('table').length, 0, 'A day with no rows must not render a table.');
assert.equal(paneDay.querySelector('#stationsDayHint').textContent, 'No station logged a scan on Aug 2.', 'A day with no rows must name that day in the hint.');

// --- The role gate applies to the Per day pane exactly as it does to the
// All pane: flipping #stationsTable's data-can-drill-in must close the Per
// day table's gate too, read fresh, not cached from the first render. ---
stationsAllTable.setAttribute('data-can-drill-in', '0');
fakeWindow.BatchHeatmap.selectDay('2026-08-01');

const gatedOffTable = paneDay.querySelector('table');
assert.notEqual(gatedOffTable, null, 'A day with rows must still render a table once the gate is closed.');
const gatedOffRows = gatedOffTable.querySelectorAll('tr').filter((tr) => tr.parentNode.tagName === 'TBODY');
for (const row of gatedOffRows) {
    assert.equal(row.hasAttribute('data-scanner-id'), false, 'No row of the Per day table may carry data-scanner-id while the gate reads "0", real scanner or TOTAL.');
}
stationsAllTable.setAttribute('data-can-drill-in', '1');

// --- An unknown day falls back to all days rather than an empty card. ---
fakeWindow.BatchHeatmap.selectDay('2099-01-01');

assert.equal(fakeWindow.BatchHeatmap.selectedDay(), null, 'A day absent from the payload must fall back to "all days".');
assert.equal(dayPick.value, '', 'Falling back to all days must clear #dayPick.');
assert.equal(day1Row.button.getAttribute('aria-pressed'), 'false', 'No row header should read pressed once the fallback happens.');
assert.equal(day2Row.button.getAttribute('aria-pressed'), 'false', 'No row header should read pressed once the fallback happens.');
assert.equal(served.value.textContent, '60', 'Falling back to all days must recompute Served against the whole batch again.');
assert.equal(scannersActive.value.textContent, '2', 'Falling back to all days must recompute Scanners active against the batch-wide byScanner fold again.');
assert.equal(scannersActive.sub.textContent, 'across the batch', 'Falling back to all days must revert the sub-line too.');
assert.equal(paneDay.querySelector('#stationsDayHint').textContent, 'Use the Day picker above to choose a day.', 'Falling back to all days must also revert the Per day pane to its "no day picked" hint.');

// --- A re-render (the live poll) must rebuild the grid from the fresh
// payload and preserve whatever day is currently selected. ---
day1Row.button.dispatch('click');
assert.equal(fakeWindow.BatchHeatmap.selectedDay(), '2026-08-01', 'Setup: select 2026-08-01 before the re-render.');

const freshPayload = JSON.parse(JSON.stringify(reportsPayload));
freshPayload.coverage.served = 70;
freshPayload.heatmap.cells['2026-08-01'][9].families = 40; // scanning continued
freshPayload.heatmap.max = 40;

fakeWindow.BatchHeatmap.render(freshPayload);

assert.equal(fakeWindow.BatchHeatmap.selectedDay(), '2026-08-01', 'render() must preserve the current selection when that day still exists.');
const rebuiltButtons = heatmapTable.querySelectorAll('button.heatmap-day');
assert.equal(rebuiltButtons.length, 2, 'render() must rebuild one row per day in the fresh heatmap.');
const rebuiltDay1 = rebuiltButtons.find((b) => b.getAttribute('data-day') === '2026-08-01');
assert.equal(rebuiltDay1.getAttribute('aria-pressed'), 'true', 'render() must reapply the selection to the rebuilt row.');
assert.equal(rebuiltDay1.textContent, 'Aug 1 (Day 1)', 'render() must reuse the row label the server already rendered.');
assert.equal(served.value.textContent, '45', 'render() must recompute Served against the fresh cells (5 + 40 + 0).');

// --- render() with the selected day now missing must fall back rather than
// render an empty card. ---
const freshPayloadNoDay1 = JSON.parse(JSON.stringify(reportsPayload));
freshPayloadNoDay1.heatmap.days = ['2026-08-02'];
delete freshPayloadNoDay1.heatmap.cells['2026-08-01'];

fakeWindow.BatchHeatmap.render(freshPayloadNoDay1);

assert.equal(fakeWindow.BatchHeatmap.selectedDay(), null, 'render() must fall back to all days when the selection no longer exists in the fresh payload.');
assert.equal(dayPick.value, '', 'The fallback from render() must also clear #dayPick.');

// --- hourLabel()'s range end must wrap the same way PHP's
// date('ga', mktime($hour, 0)) does: mktime(24, 0) rolls into the next day's
// midnight and prints "12am", not "12pm". The other fixtures only use hours
// 8-10, so a naive `hour % 12` (no `12am` case for the wrapped hour) would
// pass everything above and still be wrong at the one boundary that matters
// - a peak or best hour of 23, whose range end is hour 24. ---
const hour23Payload = JSON.parse(JSON.stringify(reportsPayload));
hour23Payload.heatmap.days = ['2026-08-03'];
hour23Payload.heatmap.hours = [22, 23];
hour23Payload.heatmap.cells = {
    '2026-08-03': {
        22: { families: 2, state: 'served' },
        23: { families: 9, state: 'served' },
    },
};
hour23Payload.heatmap.max = 9;

fakeWindow.BatchHeatmap.render(hour23Payload);
fakeWindow.BatchHeatmap.selectDay('2026-08-03');

assert.equal(peakHour.value.textContent, '11pm - 12am', 'A peak hour of 23 must range to "12am" (the next day\'s midnight), not "12pm".');
assert.equal(peakHour.sub.textContent, '9 families', 'The peak hour sub-line should still carry that hour\'s count.');

// --- Strip switching writes no query parameter and touches only its own
// card. ---
const historyCallsBeforeStrip = historyCalls.length;
stripTabDay.dispatch('click');

assert.equal(stripCard.getAttribute('data-strip'), 'day', 'A strip click must update the card\'s data-strip.');
assert.equal(stripTabDay._classes.has('active'), true, 'The clicked tab must become active.');
assert.equal(stripTabAll._classes.has('active'), false, 'The previously active tab must lose .active.');
assert.equal(paneDay.hasAttribute('hidden'), false, 'The target pane must lose the hidden attribute.');
assert.equal(paneAll.hasAttribute('hidden'), true, 'The other pane must gain the hidden attribute.');
assert.equal(historyCalls.length, historyCallsBeforeStrip, 'Strip switching must write no query parameter (no new replaceState call).');

console.log('OK: BatchHeatmap selects a day from either control through one function, recomputes all four KPIs from the payload, wraps hour 23 correctly, rebuilds the Per day Stations pane (hint/table/gate) for every selection, falls back to all days for an unknown or since-removed day, preserves the selection across render(), and switches strips without touching the URL.');
