import test from 'node:test';
import assert from 'node:assert/strict';
import skuProduto, {sugerirSku} from '../../resources/js/product-sku.js';

for (const [nome, codigo] of [
    ['Leite Integral Italac 1L', 'LEITE-INTEGRAL-ITALAC-1L'],
    ['Café com açúcar', 'CAFE-COM-ACUCAR'], [' Pão & queijo / 500g! ', 'PAO-QUEIJO-500G'],
    ['a'.repeat(150), 'A'.repeat(100)], ['!!!', 'PRODUTO'],
]) {
    test(`sugestão para ${nome.slice(0, 30)}`, () => assert.equal(sugerirSku(nome), codigo));
}

test('nome sugere código na criação e código manual não é sobrescrito', () => {
    const estado = skuProduto({nome: '', sku: '', automatico: true, editando: false});
    estado.nome = 'Leite Integral Italac 1L';
    estado.nomeAlterado();
    assert.equal(estado.sku, 'LEITE-INTEGRAL-ITALAC-1L');
    estado.sku = 'MEU-CODIGO';
    estado.codigoAlterado();
    estado.nome = 'Outro produto';
    estado.nomeAlterado();
    assert.equal(estado.sku, 'MEU-CODIGO');
    assert.equal(estado.automatico, false);
    estado.sku = '';
    estado.gerar();
    assert.equal(estado.sku, 'OUTRO-PRODUTO');
    assert.equal(estado.automatico, true);
});

test('edição preserva código ao trocar nome e só gera por ação explícita', () => {
    const estado = skuProduto({nome: 'Antigo', sku: 'FIXO', automatico: true, editando: true});
    estado.init();
    estado.nome = 'Novo nome';
    estado.nomeAlterado();
    assert.equal(estado.sku, 'FIXO');
    estado.gerar();
    assert.equal(estado.sku, 'NOVO-NOME');
    assert.equal(estado.automatico, false);
});

test('retorno de validação mantém código manual e nome vazio não quebra inicialização', () => {
    const estado = skuProduto({nome: 'Leite', sku: 'MANUAL', automatico: false, editando: false});
    estado.init();
    assert.equal(estado.sku, 'MANUAL');
    const vazio = skuProduto({nome: null, sku: null, automatico: true, editando: false});
    vazio.init();
    assert.equal(vazio.sku, '');
});
