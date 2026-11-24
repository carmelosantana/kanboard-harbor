import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('follows the system scheme immediately and on changes in either direction', () => {
    const source = readFileSync(new URL('../../Assets/js/theme-auto.js', import.meta.url), 'utf8');
    const classes = new Set(['theme-dark', 'preset-newburgh']);
    const doc = {
        documentElement: {
            classList: {
                toggle(name, force) {
                    if (force) {
                        classes.add(name);
                    } else {
                        classes.delete(name);
                    }
                },
            },
        },
    };
    let onChange;
    const media = {
        matches: false,
        addEventListener(type, callback) {
            assert.equal(type, 'change');
            onChange = callback;
        },
    };
    const win = {
        matchMedia(query) {
            assert.equal(query, '(prefers-color-scheme: dark)');
            return media;
        },
    };

    new Function('window', 'document', source)(win, doc);
    assert.deepEqual(classes, new Set(['theme-light', 'preset-newburgh']));

    media.matches = true;
    onChange(media);
    assert.deepEqual(classes, new Set(['theme-dark', 'preset-newburgh']));

    media.matches = false;
    onChange(media);
    assert.deepEqual(classes, new Set(['theme-light', 'preset-newburgh']));
});
