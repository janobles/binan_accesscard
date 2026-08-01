// Smoke test for public/assets/js/dashboard/manage-family-modal.js's standalone
// Data Entry page path: `initFamilyEntryModal(document)` must run to completion
// without throwing, must bind the form's submit handler, and
// `bindControlNumberGate` must actually wire up so typing an available control
// number reveals both gated sections of the spine and offers a draft restore
// when one is on record. This repo has no JS test harness (no package.json, no
// jsdom) - see the notes at the bottom of this file for what that means for how
// this runs. Rather than add a new dependency, this builds a minimal DOM by
// hand, just enough to exercise that path.
//
// The fixture below mirrors app/Views/Family/entry.php's real shape:
// [data-family-entry-form] sits on the outer <div>, one level above <form>,
// not on the form itself - manage-family-modal.js does root.querySelector('form')
// from that marker in several places, and would silently find nothing if the
// marker sat on the form. The two gated sections are separate
// [data-entry-section] nodes (section-head, section-members), not one wrapper.
//
// Run with: node tests/js/entry-page-gate.smoke.mjs
// Exits non-zero (and prints the failure) if the script throws during init, if
// the submit handler never binds, if typing an available control number does
// not reveal both gated sections, or if an on-record draft is not offered once
// the gate opens.

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
            if (next) {
                node._classes.add(c);
            } else {
                node._classes.delete(c);
            }
            return next;
        },
    };
}

// Hand-rolled, not a regex: attribute values ("members[0][sector_ids][]") carry
// their own square brackets, which a single top-level regex can't tell apart
// from the selector's own [attr] delimiters.
function parseSimpleSelector(part) {
    const s = part.trim();
    let i = 0;
    let tag = null;
    let id = null;
    let requireChecked = false;
    const classes = [];
    const attrs = [];

    const tagMatch = s.slice(i).match(/^[A-Za-z][\w-]*/);
    if (tagMatch) {
        tag = tagMatch[0];
        i += tagMatch[0].length;
    }

    while (i < s.length) {
        if (s[i] === '#') {
            const m = s.slice(i + 1).match(/^[\w-]+/);
            id = m[0];
            i += 1 + m[0].length;
        } else if (s[i] === '.') {
            const m = s.slice(i + 1).match(/^[\w-]+/);
            classes.push(m[0]);
            i += 1 + m[0].length;
        } else if (s[i] === '[') {
            // Scan for the closing bracket ourselves: a quoted attribute value
            // ("sector_ids[]") can carry its own brackets, so the first "]" is
            // not necessarily the selector's own.
            let close = -1;
            let inQuotes = false;
            for (let j = i + 1; j < s.length; j++) {
                if (s[j] === '"') {
                    inQuotes = !inQuotes;
                } else if (s[j] === ']' && !inQuotes) {
                    close = j;
                    break;
                }
            }
            if (close === -1) {
                throw new Error('Unterminated [attr] in selector: ' + part);
            }
            const inner = s.slice(i + 1, close);
            const eq = inner.indexOf('=');
            if (eq === -1) {
                attrs.push([inner, null]);
            } else {
                const name = inner.slice(0, eq);
                const value = inner.slice(eq + 1).replace(/^"|"$/g, '');
                attrs.push([name, value]);
            }
            i = close + 1;
        } else if (s.slice(i) === ':checked') {
            requireChecked = true;
            i = s.length;
        } else {
            throw new Error('Unsupported selector fragment: ' + part);
        }
    }

    return { tag: tag ? tag.toUpperCase() : null, id, classes, attrs, requireChecked };
}

function matchesSimple(node, parsed) {
    if (node.nodeType !== 1) {
        return false;
    }
    if (parsed.tag && node.tagName !== parsed.tag) {
        return false;
    }
    if (parsed.id && node.id !== parsed.id) {
        return false;
    }
    for (const c of parsed.classes) {
        if (!node._classes.has(c)) {
            return false;
        }
    }
    for (const [name, value] of parsed.attrs) {
        if (!(name in node._attrs)) {
            return false;
        }
        if (value !== null && node._attrs[name] !== value) {
            return false;
        }
    }
    if (parsed.requireChecked && !node.checked) {
        return false;
    }
    return true;
}

// Only the plain descendant combinator ("A B") is supported, which is all
// setStepStates()'s '#entrySpine .stepper-step' lookup needs - no >, +, or ~.
function matchesCombinatorPart(node, part) {
    const segments = part.trim().split(/\s+/).filter(Boolean);

    if (segments.length <= 1) {
        return matchesSimple(node, parseSimpleSelector(part));
    }

    if (!matchesSimple(node, parseSimpleSelector(segments[segments.length - 1]))) {
        return false;
    }

    var ancestor = node.parentNode;
    var segIndex = segments.length - 2;

    while (ancestor && segIndex >= 0) {
        if (matchesSimple(ancestor, parseSimpleSelector(segments[segIndex]))) {
            segIndex--;
        }
        ancestor = ancestor.parentNode;
    }

    return segIndex < 0;
}

function matchesSelector(node, selector) {
    return selector.split(',').some((part) => matchesCombinatorPart(node, part));
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
        this.dataset = {};
        this.children = [];
        this.parentNode = null;
        this._listeners = {};
        this.value = '';
        this.disabled = false;
        this.checked = false;
    }

    get classList() {
        return makeClassList(this);
    }

    setAttribute(name, value) {
        this._attrs[name] = String(value);
        if (name === 'id') {
            this.id = value;
        }
        if (name === 'class') {
            this._classes = new Set(String(value).split(/\s+/).filter(Boolean));
        }
        if (name.startsWith('data-')) {
            const key = name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
            this.dataset[key] = String(value);
        }
    }

    getAttribute(name) {
        return name in this._attrs ? this._attrs[name] : null;
    }

    removeAttribute(name) {
        delete this._attrs[name];
    }

    appendChild(child) {
        child.parentNode = this;
        this.children.push(child);
        return child;
    }

    querySelectorAll(selector) {
        const out = [];
        walk(this, (node) => {
            if (matchesSelector(node, selector)) {
                out.push(node);
            }
        });
        return out;
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] || null;
    }

    matches(selector) {
        return matchesSelector(this, selector);
    }

    closest(selector) {
        let node = this;
        while (node && node.nodeType === 1) {
            if (matchesSelector(node, selector)) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

    addEventListener(type, handler) {
        (this._listeners[type] ||= []).push(handler);
    }

    removeEventListener(type, handler) {
        const list = this._listeners[type];
        if (list) {
            this._listeners[type] = list.filter((h) => h !== handler);
        }
    }

    dispatch(type, event = {}) {
        for (const handler of this._listeners[type] || []) {
            handler({ target: this, preventDefault() {}, ...event });
        }
    }

    checkValidity() {
        return true;
    }

    focus() {
        /* no-op: nothing in this fixture asserts on focus */
    }

    remove() {
        if (this.parentNode) {
            var siblings = this.parentNode.children;
            var at = siblings.indexOf(this);

            if (at !== -1) {
                siblings.splice(at, 1);
            }

            this.parentNode = null;
        }
    }
}

function el(tag, attrs = {}, children = []) {
    const node = new FakeNode(tag);
    for (const [k, v] of Object.entries(attrs)) {
        node.setAttribute(k, v);
    }
    children.forEach((c) => node.appendChild(c));
    return node;
}

// The entry page's actual markup (app/Views/Family/entry.php +
// app/Views/Family/_fields.php), reduced to the elements the init path and the
// gate touch. [data-family-entry-form] is on the outer div, not the <form> -
// see the file header comment for why that distinction is the whole point of
// this fixture.
const controlNumberField = el('input', { id: 'controlNumber' });
const gateStatus = el('div', { 'data-control-number-status': '' });
const qrImage = el('img', { class: 'd-none', 'data-control-number-qr': '' });
const gate = el(
    'div',
    { 'data-control-number-gate': '', 'data-qr-check-url': 'http://localhost/records/qr-check' },
    [controlNumberField, gateStatus, qrImage]
);

const sectionHead = el('div', { class: 'stepper-step-content d-none', id: 'section-head', 'data-entry-section': '' });
const sectionMembers = el('div', { class: 'stepper-step-content d-none', id: 'section-members', 'data-entry-section': '' });

const realControlNumberInput = el('input', { type: 'hidden', name: 'qr_control_no', 'data-entry-control-number': '' });
const saveButton = el('button', { type: 'submit', 'data-family-save': '' });
const form = el('form', { id: 'familyEntryForm' }, [gate, sectionHead, sectionMembers, realControlNumberInput, saveButton]);
const entryRoot = el('div', { class: 'container-fluid px-4 py-4', 'data-family-entry-form': '' }, [form]);

const documentNode = {
    nodeType: 9,
    readyState: 'complete',
    _listeners: {},
    children: [entryRoot],
    addEventListener(type, handler) {
        (this._listeners[type] ||= []).push(handler);
    },
    createElement(tag) {
        return new FakeNode(tag);
    },
    querySelectorAll(selector) {
        const out = [];
        const roots = [entryRoot];
        for (const root of roots) {
            if (matchesSelector(root, selector)) {
                out.push(root);
            }
            walk(root, (node) => {
                if (matchesSelector(node, selector)) {
                    out.push(node);
                }
            });
        }
        return out;
    },
    querySelector(selector) {
        return this.querySelectorAll(selector)[0] || null;
    },
    getElementById(id) {
        return this.querySelector('#' + id);
    },
};

const fakeWindow = {};
fakeWindow.window = fakeWindow;
fakeWindow.document = documentNode;
fakeWindow.registerDashboardModal = function registerDashboardModal() {};
// A draft already on record, so offerDraftRestoreIfVisible has something to
// offer once the gate opens - draftIsEmpty() requires at least one non-blank
// head field, an empty members/sector/service array is fine.
const existingDraft = JSON.stringify({
    head: { lastname: 'Reyes' },
    members: [],
    sector_ids: [],
    service_ids: [],
    savedAt: Date.now(),
});
fakeWindow.localStorage = {
    getItem: (key) => (key === 'binan_family_modal_draft_v1' ? existingDraft : null),
    setItem() {},
    removeItem() {},
};
// Instant timers: the gate's real debounce (350ms) and the qr-check hang guard
// (5000ms) would otherwise make this test slow for no benefit.
fakeWindow.setTimeout = (fn) => { fn(); return 0; };
fakeWindow.clearTimeout = () => {};
fakeWindow.URL = URL;
fakeWindow.location = { href: 'http://localhost/records/entry' };
fakeWindow.fetch = (url) => {
    fakeWindow.lastFetchUrl = url;

    return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ available: true, message: '' }),
    });
};

const script = readFileSync(new URL('../../public/assets/js/dashboard/manage-family-modal.js', import.meta.url), 'utf8');

let initThrew = null;
try {
    vm.runInNewContext(script, fakeWindow, { filename: 'manage-family-modal.js' });
} catch (error) {
    initThrew = error;
}

assert.equal(
    initThrew,
    null,
    'initFamilyEntryPage() must not throw when the page loads: ' + (initThrew && initThrew.stack)
);

// This is the direct regression check for the bug where [data-family-entry-form]
// sat on <form> itself: every root.querySelector('form') inside
// initFamilyEntryModal() then found nothing, so the `if (formEl)` block never
// ran and no submit handler was ever bound. Confirms the fix without exercising
// submitFamilyForm()'s validation/AJAX chain, which needs far more DOM than
// this fixture builds.
assert.equal(
    (form._listeners.submit || []).length,
    1,
    'initFamilyEntryModal() must bind a submit handler to the form.'
);

// Same root cause, different symptom: offerDraftRestoreIfVisible(root) also
// did root.querySelector('form') and returned early on the same null. With the
// fix, [data-family-entry-form] is never itself d-none on this page (only the
// gated sections inside it are), so the restore prompt is offered at init,
// before the control number gate even opens - see the fixed comment in
// initFamilyEntryModal(). A draft is seeded in fakeWindow.localStorage above
// so there is something to offer.
assert.equal(
    entryRoot.dataset.familyDraftOffered,
    '1',
    'A draft on record must be offered for restore once init runs.'
);

// The gate must actually have wired up: typing a control number should trigger
// the availability check and, once it comes back available, reveal both gated
// sections of the spine (not one shared wrapper).
assert.equal(sectionHead.classList.contains('d-none'), true, 'Head section should still be hidden before any input.');
assert.equal(sectionMembers.classList.contains('d-none'), true, 'Members section should still be hidden before any input.');

controlNumberField.value = '12345';
controlNumberField.dispatch('input');

// The debounce timer runs synchronously (fakeWindow.setTimeout above), but the
// fetch().then() chain it kicks off resolves as real microtasks - setImmediate
// runs after all of them drain, however many hops the chain needs.
await new Promise((resolve) => setImmediate(resolve));

assert.equal(sectionHead.classList.contains('d-none'), false, 'A control number the server reports available must reveal the head section.');
assert.equal(sectionMembers.classList.contains('d-none'), false, 'A control number the server reports available must reveal the members section.');
assert.equal(realControlNumberInput.value, '12345', 'The hidden qr_control_no field must be filled once the gate clears.');

console.log('OK: entry page init does not throw, the submit handler binds, a draft on record is offered, and the control-number gate reveals both spine sections.');
