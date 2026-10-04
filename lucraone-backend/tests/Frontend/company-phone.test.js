import test from 'node:test';
import assert from 'node:assert/strict';
import telefoneCompany, {mascararCelular} from '../../resources/js/company-phone.js';

for (const [entrada, esperado] of [
    ['', ''], ['1', '(1'], ['11', '(11'], ['119', '(11) 9'],
    ['1198765', '(11) 98765'], ['11987654321', '(11) 98765-4321'],
    ['(11) 98765-4321', '(11) 98765-4321'],
    ['abc11!98765@4321xyz', '(11) 98765-4321'],
    ['11987654321999', '(11) 98765-4321'],
]) {
    test(`máscara formata ${JSON.stringify(entrada)}`, () => {
        assert.equal(mascararCelular(entrada), esperado);
        assert.equal(mascararCelular(esperado), esperado);
    });
}

test('edição inicial formata o valor existente e input atualiza apenas telefone', () => {
    const outroCampo = {value: 'Empresa Ltda'};
    const input = {value: '11987654321', selectionStart: 11, setSelectionRange(start) { this.selectionStart = start; }};
    const componente = telefoneCompany();
    componente.$el = input;
    componente.init();
    assert.equal(input.value, '(11) 98765-4321');
    input.value = '21912345678';
    input.selectionStart = 11;
    componente.formatar();
    assert.equal(input.value, '(21) 91234-5678');
    assert.equal(input.selectionStart, 15);
    assert.equal(outroCampo.value, 'Empresa Ltda');
});

test('apagar telefone limpa máscara e editar no meio preserva posição entre dígitos', () => {
    const componente = telefoneCompany();
    componente.$el = {value: '(11) 91265-4321', selectionStart: 7, setSelectionRange(start) { this.selectionStart = start; }};
    componente.formatar();
    assert.equal(componente.$el.selectionStart, 7);
    componente.$el.value = '';
    componente.$el.selectionStart = 0;
    componente.formatar();
    assert.equal(componente.$el.value, '');
    assert.equal(componente.$el.selectionStart, 0);
});

test('input durante digitação formata progressivamente e ignora dígitos excedentes', () => {
    const componente = telefoneCompany();
    componente.$el = {value: '', selectionStart: 0, setSelectionRange(start) { this.selectionStart = start; }};
    componente.init();
    const esperado = ['(1', '(11', '(11) 9'];
    for (const [index, digito] of [...'119876543211234'].entries()) {
        componente.$el.value += digito;
        componente.$el.selectionStart = componente.$el.value.length;
        componente.formatar();
        if (index < 3) assert.equal(componente.$el.value, esperado[index]);
        if (index >= 10) assert.equal(componente.$el.value, '(11) 98765-4321');
    }
});

test('input após colagem remove caracteres inválidos e limita a onze dígitos', () => {
    for (const colado of ['11987654321', '119876543211234', '(11) 98765-4321', 'abc11!98765@4321xyz']) {
        const componente = telefoneCompany();
        componente.$el = {value: colado, selectionStart: colado.length, setSelectionRange(start) { this.selectionStart = start; }};
        componente.formatar();
        assert.equal(componente.$el.value, '(11) 98765-4321');
    }
});
