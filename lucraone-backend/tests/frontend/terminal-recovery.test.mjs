import test from 'node:test';
import assert from 'node:assert/strict';
import recuperacaoTerminal from '../../resources/js/terminal-recovery.js';

test('bloqueia cliques concorrentes e apaga o segredo ao fechar', async () => {
    const originalFetch = globalThis.fetch;
    let calls = 0;
    let finish;
    globalThis.fetch = () => {
        calls++;
        return new Promise((resolve) => {
            finish = () => resolve({
                ok: true,
                status: 200,
                json: async () => ({ data: { recovery_code: 'ABC123.SECRET123456', expires_at: '2026-10-12T13:00:00+00:00' } }),
            });
        });
    };

    try {
        const ui = recuperacaoTerminal('/recover', { name: 'Caixa de teste' }, 'csrf');
        ui.reason = 'lost';
        ui.confirmed = true;
        const first = ui.issue();
        const second = ui.issue();
        assert.equal(calls, 1);
        finish();
        await Promise.all([first, second]);
        assert.equal(ui.stage, 'result');
        assert.equal(ui.code, 'ABC123.SECRET123456');
        ui.close();
        assert.equal(ui.code, '');
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('falha de rede marca resultado incerto e não reenvia', async () => {
    const originalFetch = globalThis.fetch;
    let calls = 0;
    globalThis.fetch = async () => {
        calls++;
        throw new Error('synthetic network failure');
    };

    try {
        const ui = recuperacaoTerminal('/recover', { name: 'Caixa de teste' }, 'csrf');
        ui.reason = 'lost';
        ui.confirmed = true;
        await ui.issue();
        assert.equal(ui.stage, 'uncertain');
        assert.equal(calls, 1);
    } finally {
        globalThis.fetch = originalFetch;
    }
});
