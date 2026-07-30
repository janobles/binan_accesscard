// Smoke test for public/assets/js/dashboard/manage-family-modal.js's standalone
// Data Entry page path: `initFamilyEntryModal(document)` must run to completion
// without throwing, and `bindControlNumberGate` must actually wire up so typing
// an available control number reveals the entry body. This repo has no JS test
// harness (no package.json, no jsdom) - see the notes at the bottom of this file
// for what that means for how this runs. Rather than add a new dependency, this
// builds a minimal DOM by hand, just enough to exercise that path.
//
// Run with: node tests/js/entry-page-gate.smoke.mjs
// Exits non-zero (and prints the failure) if the script throws during init, or
// if typing an available control number does not reveal the entry body.

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

function matchesSelector(node, selector) {
    return selector.split(',').some((part) => matchesSimple(node, parseSimpleSelector(part)));
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
// gate touch.
const controlNumberField = el('input', { id: 'controlNumber', name: 'control_no' });
const gateStatus = el('div', { 'data-control-number-status': '' });
const gate = el(
    'div',
    { class: 'row mb-4', 'data-control-number-gate': '', 'data-qr-check-url': 'http://localhost/records/qr-check' },
    [controlNumberField, gateStatus]
);

const realControlNumberInput = el('input', { type: 'hidden', name: 'qr_control_no', 'data-entry-control-number': '' });
const form = el('form', { id: 'familyEntryForm' }, [realControlNumberInput]);
const entryBody = el(
    'div',
    { class: 'row d-none', 'data-entry-body': '', 'data-family-entry-form': '' },
    [form]
);

const documentNode = {
    nodeType: 9,
    readyState: 'complete',
    _listeners: {},
    children: [gate, entryBody],
    addEventListener(type, handler) {
        (this._listeners[type] ||= []).push(handler);
    },
    querySelectorAll(selector) {
        const out = [];
        const roots = [gate, entryBody];
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
fakeWindow.localStorage = { getItem: () => null, setItem() {}, removeItem() {} };
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

// The gate must actually have wired up: typing a control number should trigger
// the availability check and, once it comes back available, reveal the body.
assert.equal(entryBody.classList.contains('d-none'), true, 'Body should still be hidden before any input.');

controlNumberField.value = '12345';
controlNumberField.dispatch('input');

// The debounce timer runs synchronously (fakeWindow.setTimeout above), but the
// fetch().then() chain it kicks off resolves as real microtasks - setImmediate
// runs after all of them drain, however many hops the chain needs.
await new Promise((resolve) => setImmediate(resolve));

assert.equal(entryBody.classList.contains('d-none'), false, 'A control number the server reports available must reveal the entry body.');
assert.equal(realControlNumberInput.value, '12345', 'The hidden qr_control_no field must be filled once the gate clears.');

console.log('OK: entry page init does not throw, and the control-number gate reveals the form.');
