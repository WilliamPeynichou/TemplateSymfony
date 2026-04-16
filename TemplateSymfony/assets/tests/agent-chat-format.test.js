import test from 'node:test';
import assert from 'node:assert/strict';

import { formatPendingAction } from '../agent-chat-format.js';

test('formatPendingAction provides safe plain-text payload', () => {
    const payload = formatPendingAction({
        name: '<img src=x onerror=alert(1)>',
        args: { value: '<script>alert(1)</script>' },
    });

    assert.equal(payload.name, '<img src=x onerror=alert(1)>');
    assert.match(payload.argsText, /<script>alert\(1\)<\/script>/);
});

test('formatPendingAction falls back to defaults', () => {
    const payload = formatPendingAction(null);

    assert.equal(payload.name, 'Action');
    assert.equal(payload.argsText, '{}');
});
