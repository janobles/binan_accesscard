// Smoke test for public/assets/js/dashboard/barangay-map.js: task 13's Finding
// 2/3. binan_brgy_paths.json carries Title Case barangay names ("Biñan") while
// SubsidyStatsModel::byBarangay() returns the V22 dump's uppercase names
// ("BIÑAN"). Comparing them exactly makes every path fall through to
// "is-none" with a "No eligible families" popover even at 100% coverage. This
// pins that the map colours and labels a path correctly when the two sides
// only differ by case, and that hovering it still finds the leaderboard row
// whose data-barangay carries the opposite case. This repo has no JS test
// harness (no package.json, no jsdom) - see tests/js/entry-page-gate.smoke.mjs
// for the pattern this follows. Rather than add a dependency, this builds a
// minimal DOM by hand, just enough to exercise the module.
//
// Run with: node tests/js/barangay-map.smoke.mjs
// Exits non-zero (and prints the failure) if the script throws during load, if
// a path whose data-brgy differs only in case from a coverage row's barangay
// is not coloured and labelled from that row, or if hovering it fails to find
// the leaderboard row whose data-barangay carries the opposite case.

import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

function makeClassList(node) {
    return {
        contains: (c) => node._classes.has(c),
        add: (...cs) => cs.forEach((c) => node._classes.add(c)),
        remove: (...cs) => cs.forEach((c) => node._classes.delete(c)),
    };
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
    }

    get classList() { return makeClassList(this); }

    setAttribute(name, value) { this._attrs[name] = String(value); }
    getAttribute(name) { return name in this._attrs ? this._attrs[name] : null; }

    appendChild(child) {
        child.parentNode = this;
        this.children.push(child);
        return child;
    }

    // Only what barangay-map.js actually calls: a bare tag[attr] or
    // tag[attr="value"], no descendant combinators, no id/class selectors.
    querySelectorAll(selector) {
        const m = selector.trim().match(/^([A-Za-z][\w-]*)?\[([\w-]+)(?:="([^"]*)")?\]$/);
        if (!m) { throw new Error('Unsupported selector: ' + selector); }
        const [, tag, attr, value] = m;
        const out = [];
        const walk = (node) => {
            for (const child of node.children) {
                const tagOk = !tag || child.tagName === tag.toUpperCase();
                const attrOk = attr in child._attrs && (value === undefined || child._attrs[attr] === value);
                if (tagOk && attrOk) { out.push(child); }
                walk(child);
            }
        };
        walk(this);
        return out;
    }

    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }

    addEventListener(type, handler) { (this._listeners[type] ||= []).push(handler); }

    dispatch(type) {
        for (const handler of this._listeners[type] || []) { handler({ type, target: this }); }
    }
}

function el(tag, attrs = {}, children = []) {
    const node = new FakeNode(tag);
    for (const [k, v] of Object.entries(attrs)) { node.setAttribute(k, v); }
    children.forEach((c) => node.appendChild(c));
    return node;
}

// --- Fixture: two paths from binan_brgy_paths.json (Title Case), one
// coverage row and one leaderboard row from the V22 dump (uppercase). BIÑAN
// is served in full; SAN ANTONIO has zero eligible families reached, but is a
// real row (not simply absent), so it must resolve to "is-low"/"is-none" from
// its own row rather than the "no row at all" branch. ---

const binanPath = el('path', { 'data-brgy': 'Biñan' });
const sanAntonioPath = el('path', { 'data-brgy': 'San Antonio' });

const coverage = [
    { barangay: 'BIÑAN', total: 80, received: 80, coverage: 100 },
    { barangay: 'SAN ANTONIO', total: 40, received: 0, coverage: 0 },
];

const mapHost = el('div', {
    'data-barangay-map': '',
    'data-coverage': JSON.stringify(coverage),
}, [binanPath, sanAntonioPath]);

const binanLeaderboardRow = el('tr', { 'data-barangay': 'BIÑAN' });
const sanAntonioLeaderboardRow = el('tr', { 'data-barangay': 'SAN ANTONIO' });

// The fixture's three top-level nodes are siblings under the document, not
// nested under one root, so this checks each root itself (mapHost's children
// are the paths; the two <tr> are themselves the match) plus its descendants.
function matchesSelector(node, selector) {
    const m = selector.trim().match(/^([A-Za-z][\w-]*)?\[([\w-]+)(?:="([^"]*)")?\]$/);
    if (!m) { throw new Error('Unsupported selector: ' + selector); }
    const [, tag, attr, value] = m;
    const tagOk = !tag || node.tagName === tag.toUpperCase();
    const attrOk = attr in node._attrs && (value === undefined || node._attrs[attr] === value);
    return tagOk && attrOk;
}

const documentNode = {
    nodeType: 9,
    children: [mapHost, binanLeaderboardRow, sanAntonioLeaderboardRow],
    _listeners: {},
    addEventListener(type, handler) { (this._listeners[type] ||= []).push(handler); },
    querySelectorAll(selector) {
        const out = [];
        for (const root of this.children) {
            if (matchesSelector(root, selector)) { out.push(root); }
            out.push(...root.querySelectorAll(selector));
        }
        return out;
    },
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; },
};

const fakeWindow = {};
fakeWindow.window = fakeWindow;
fakeWindow.document = documentNode;

const script = readFileSync(new URL('../../public/assets/js/dashboard/barangay-map.js', import.meta.url), 'utf8');

let threw = null;
try {
    vm.runInNewContext(script, fakeWindow, { filename: 'barangay-map.js' });
} catch (error) {
    threw = error;
}

assert.equal(threw, null, 'barangay-map.js must not throw on load: ' + (threw && threw.stack));

// --- The case mismatch: "Biñan" (path) vs "BIÑAN" (coverage row) must still
// resolve to that row, not fall through to "is-none". ---
assert.equal(binanPath.classList.contains('is-high'), true, 'A 100%-coverage barangay must paint is-high even though the path\'s data-brgy differs in case from the coverage row\'s name.');
assert.equal(binanPath.classList.contains('is-none'), false, 'It must not fall through to is-none.');

assert.equal(sanAntonioPath.classList.contains('is-none'), true, 'A genuinely 0%-coverage barangay (a real row, zero received) reads is-none, which is correct here, not a case-mismatch miss.');

// --- Hovering must find the leaderboard row despite the case difference. ---
binanPath.dispatch('mouseenter');
assert.equal(binanLeaderboardRow.classList.contains('is-highlighted'), true, 'Hovering the path must highlight the leaderboard row whose data-barangay is uppercase.');
binanPath.dispatch('mouseleave');
assert.equal(binanLeaderboardRow.classList.contains('is-highlighted'), false, 'Leaving the path must remove the highlight.');

console.log('OK: barangay-map.js colours and labels a path from its coverage row and finds the matching leaderboard row even when the two sides\' barangay names differ only in case.');
