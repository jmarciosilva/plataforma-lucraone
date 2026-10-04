import test from 'node:test';
import assert from 'node:assert/strict';
import precoProduto, {centavos, sugerirPreco} from '../../resources/js/product-price.js';

for (const [custo, margem, esperado] of [
    ['2.19', '30', '2,85'], ['2.19', '0', '2,19'],
    ['10.00', '12,50', '11,25'], ['0.01', '50', '0,02'],
]) {
    test(`margem sobre custo ${custo} / ${margem}`, () => assert.equal(sugerirPreco(custo, margem), esperado));
}

test('decimal brasileiro usa centavos e rejeita formatos inválidos/overflow', () => {
    assert.equal(centavos('2'), 200n);
    assert.equal(centavos('2,19'), 219n);
    assert.equal(centavos('100,00'), 10000n);
    for (const valor of ['1e3', 'NaN', 'Infinity', '-2', '1,234', '1.234,56', '10000000000,00']) {
        assert.equal(centavos(valor), null);
    }
});

test('margem inválida ou custo ausente não produz sugestão', () => {
    for (const margem of ['', '-1', '1e2', 'NaN', 'INF', '1000.01', '2.333']) {
        assert.equal(sugerirPreco('2.19', margem), null);
    }
    assert.equal(sugerirPreco('', '30'), null);
    assert.equal(sugerirPreco('0', '30'), null);
    assert.equal(sugerirPreco('9999999999.99', '1000'), null);
});

test('custo calcula sugestão ao digitar, sem alterar tipo ou valor', () => {
    const s = precoProduto({tipo: 'cost', moeda: 'BRL', valor: '2,19', margem: '30'});
    assert.equal(s.podeSugerir, true);
    assert.equal(s.precoSugerido, '2,85');
    assert.equal(s.tipo, 'cost');
    assert.equal(s.valor, '2,19');
    s.margem = '50';
    assert.equal(s.precoSugerido, '3,29');
    assert.equal(s.valor, '2,19');
});

test('usar sugestão apenas preenche venda; alteração manual permanece', () => {
    const s = precoProduto({custos: {BRL: '2.19'}, tipo: 'cost', moeda: 'BRL', valor: '2,19', margem: '30'});
    s.aplicar();
    assert.equal(s.tipo, 'sale');
    assert.equal(s.valor, '2,85');
    assert.equal(s.podeSugerir, false);
    s.valor = '2,79';
    assert.equal(s.margemEfetiva, '27,3973');
    s.margem = '40';
    s.aplicar();
    assert.equal(s.valor, '2,79');
    s.valor = '2,99';
    assert.equal(s.margemEfetiva, '36,5297');
});

test('venda não oferece margem desejada e sem custo continua editável', () => {
    const s = precoProduto({tipo: 'sale', moeda: 'BRL', valor: '5,00', margem: '30'});
    assert.equal(s.podeSugerir, false);
    assert.equal(s.precoSugerido, null);
    assert.equal(s.margemEfetiva, null);
    s.aplicar();
    assert.equal(s.valor, '5,00');
    s.valor = '7,99';
    assert.equal(s.valor, '7,99');
});

test('custo inválido ou margem inválida não produz NaN/Infinity nem altera formulário', () => {
    for (const valor of ['', '0', 'NaN', 'Infinity', '1e3']) {
        const s = precoProduto({tipo: 'cost', valor, margem: '30'});
        assert.equal(s.precoSugerido, null);
        s.aplicar();
        assert.equal(s.tipo, 'cost');
        assert.equal(s.valor, valor);
    }
    const s = precoProduto({tipo: 'cost', valor: '2,19', margem: 'NaN'});
    assert.equal(s.precoSugerido, null);
    s.aplicar();
    assert.equal(s.valor, '2,19');
});

test('retorno de validação preserva preço manual e moeda normaliza somente consulta', () => {
    const s = precoProduto({custos: {BRL: '2.19'}, tipo: 'sale', moeda: 'brl', valor: '2,79', margem: '30'});
    assert.equal(s.custoAtual, '2,19');
    assert.equal(s.valor, '2,79');
    s.moeda = 'EUR';
    assert.equal(s.valor, '2,79');
});

// Mesmos vetores usados pelo teste da regra decimal PHP.
const {readFileSync} = await import('node:fs');
const {margemPercentual} = await import('../../resources/js/product-price.js');
for (const [custo, venda, margem] of JSON.parse(readFileSync(new URL('../Fixtures/margem-decimal.json', import.meta.url), 'utf8'))) {
    test(`paridade com backend: custo ${custo}, venda ${venda}`, () => assert.equal(margemPercentual(custo, venda), margem));
}
