import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';

for (const view of ['orders/show', 'inventory/index']) {
    test(`${view}: min e step acompanham o produto selecionado`, () => {
        const source = readFileSync(new URL(`../../resources/views/${view}.blade.php`, import.meta.url), 'utf8');
        const input = source.match(/<x-input\b[^>]*nome="quantity"[^>]*>/s)?.[0];
        assert.ok(input);
        for (const attribute of ['min', 'step']) {
            const expression = input.match(new RegExp(`x-bind:${attribute}="([^"]+)"`))?.[1];
            assert.ok(expression, `${attribute} deve acompanhar a seleção`);
            const context = {unidades: {un: 'UN', kg: 'KG'}, produto: 'un', selecionada: null, embalagem: ''};
            assert.equal(String(runInNewContext(expression, context)), '1');
            context.produto = 'kg';
            assert.equal(String(runInNewContext(expression, context)), '0.001');
            if (view === 'orders/show') {
                context.embalagem = 'caixa';
                assert.equal(String(runInNewContext(expression, context)), '1');
                context.embalagem = '';
            }
            context.produto = 'un';
            if (view === 'inventory/index') context.selecionada = {factor: 6};
            assert.equal(String(runInNewContext(expression, context)), '1');
        }
    });
}
