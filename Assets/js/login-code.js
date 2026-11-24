(function () {
    'use strict';

    const group = document.querySelector('[data-code-digits]');
    if (!group) return;

    const form = group.form;
    const field = form.elements.namedItem('code');
    const fallback = field.closest('[data-code-fallback]');
    const digits = Array.from(group.querySelectorAll('input'));

    function enterDigits(index, value) {
        const numbers = value.replace(/[^0-9]/g, '').slice(0, digits.length);
        const start = numbers.length === digits.length ? 0 : index;
        if (!numbers) {
            digits[index].value = '';
            return;
        }

        for (let offset = 0; offset < numbers.length && start + offset < digits.length; offset++) {
            digits[start + offset].value = numbers[offset];
        }
        digits[Math.min(start + numbers.length, digits.length - 1)].focus();
    }

    digits.forEach((digit, index) => {
        digit.addEventListener('focus', () => digit.select());
        digit.addEventListener('input', () => enterDigits(index, digit.value));
        digit.addEventListener('paste', event => {
            const value = event.clipboardData.getData('text');
            event.preventDefault();
            enterDigits(index, value);
        });
        digit.addEventListener('keydown', event => {
            if (event.key === 'Backspace' && !digit.value && index > 0) {
                event.preventDefault();
                digits[index - 1].value = '';
                digits[index - 1].focus();
            }
        });
    });

    form.addEventListener('submit', () => {
        field.value = digits.map(digit => digit.value).join('');
    });

    const hadFocus = document.activeElement === field;
    group.hidden = false;
    group.disabled = false;
    field.type = 'hidden';
    fallback.hidden = true;

    if (field.value) {
        enterDigits(0, field.value);
    } else if (hadFocus) {
        digits[0].focus();
    }
}());
