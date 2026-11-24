import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const source = readFileSync(new URL('../../Assets/js/login-code.js', import.meta.url), 'utf8');
const load = new Function('document', source);

function harness() {
    const document = {
        activeElement: null,
        querySelector: selector => selector === '[data-code-digits]' ? group : null,
    };
    const fallback = { hidden: false };
    const code = {
        type: 'text',
        value: '',
        closest: selector => selector === '[data-code-fallback]' ? fallback : null,
    };
    const form = new EventTarget();
    form.elements = { namedItem: name => name === 'code' ? code : null };
    const digits = Array.from({ length: 6 }, () => Object.assign(new EventTarget(), {
        value: '',
        focus() {
            document.activeElement = this;
            this.dispatchEvent(new Event('focus'));
        },
        select() {},
    }));
    const group = { form, hidden: true, disabled: true, querySelectorAll: () => digits };

    load(document);
    return { document, form, code, digits };
}

function typeDigit(box, value) {
    box.value = value;
    box.dispatchEvent(new Event('input'));
}

test('typing a digit advances to the next box', () => {
    const { document, digits } = harness();
    digits[0].focus();
    typeDigit(digits[0], '4');

    assert.equal(digits[0].value, '4');
    assert.equal(document.activeElement, digits[1]);
});

test('backspace on an empty box clears and focuses the previous box', () => {
    const { document, digits } = harness();
    typeDigit(digits[0], '4');
    const backspace = Object.assign(new Event('keydown', { cancelable: true }), {
        key: 'Backspace',
    });
    digits[1].dispatchEvent(backspace);

    assert.equal(digits[0].value, '');
    assert.equal(document.activeElement, digits[0]);
    assert.equal(backspace.defaultPrevented, true);
});

test('pasting a complete code fills all six boxes', () => {
    const { digits } = harness();
    const paste = Object.assign(new Event('paste', { cancelable: true }), {
        clipboardData: { getData: type => type === 'text' ? '123456' : '' },
    });
    digits[0].dispatchEvent(paste);

    assert.deepEqual(digits.map(box => box.value), ['1', '2', '3', '4', '5', '6']);
    assert.equal(paste.defaultPrevented, true);
});

test('submitting joins the six boxes into the hidden code field', () => {
    const { form, code, digits } = harness();
    digits.forEach((box, index) => typeDigit(box, '012345'[index]));
    assert.equal(code.type, 'hidden');
    assert.equal(code.value, '');

    form.dispatchEvent(new Event('submit'));

    assert.equal(code.value, '012345');
});

test('typing a non-digit clears the box and leaves focus in place', () => {
    const { document, digits } = harness();
    digits[0].focus();
    typeDigit(digits[0], 'x');

    assert.equal(digits[0].value, '');
    assert.equal(document.activeElement, digits[0]);
});
