import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import onboardingCliente from '../../resources/js/onboarding.js';

function wizard(passo = 1) {
    const estado = onboardingCliente({passo, dados: {
        name: 'Cliente', administrator_email: 'admin@cliente.test', administrator_name: 'Admin',
        configure_company: '0', keep_platform_access: '0',
    }}, '/consulta');
    // Form controls model required/type/minlength and expose native feedback calls.
    const campo = (value = '', options = {}) => ({
        value, type: 'text', required: false, disabled: false, reports: 0, ...options,
        checkValidity() {
            if (this.disabled) return true;
            if (this.required && !this.value) return false;
            if (this.type === 'email' && this.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value)) return false;
            return !this.minLength || this.value.length >= this.minLength;
        },
        reportValidity() { this.reports++; this.validationVisible = !this.checkValidity(); return this.checkValidity(); },
    });
    const email = campo('', {type: 'email', required: true});
    Object.defineProperty(email, 'value', {
        get: () => estado.dados.administrator_email,
        set: (value) => { estado.dados.administrator_email = value; },
    });
    const name = campo('', {required: true});
    Object.defineProperty(name, 'value', {get: () => estado.dados.name});
    const password = campo('senha-valida-123', {type: 'password', required: true, minLength: 8});
    const confirmation = campo('senha-valida-123', {type: 'password', required: true, minLength: 8});
    estado.$refs = {
        email, password, confirmation,
        passo1: {querySelectorAll: () => [name]}, passo2: {querySelectorAll: () => [email, password, confirmation]}, passo3: {querySelectorAll: () => []},
        titulo: {focus() {}},
    };
    estado.$nextTick = async (callback) => {
        password.disabled = confirmation.disabled = Boolean(estado.identidade?.exists);
        callback?.();
    };
    return estado;
}

function consulta(contexto, identidade) {
    const pedidos = [];
    contexto.mock.method(globalThis, 'fetch', async (url, opcoes) => {
        pedidos.push({url, opcoes});
        return {ok: true, json: async () => identidade};
    });
    return pedidos;
}

test('avançar e voltar preserva dados e não grava antes da confirmação', async (t) => {
    const pedidos = consulta(t, {exists: false, available: true, name: null});
    const estado = wizard();
    await estado.avancar();
    await estado.avancar();
    await estado.avancar();
    assert.equal(estado.passo, 4);
    estado.voltar();
    estado.voltar();
    assert.equal(estado.dados.name, 'Cliente');
    assert.equal(estado.$refs.password.value, 'senha-valida-123');
    assert.equal(pedidos.length, 1);
    assert.equal(pedidos[0].opcoes.method, undefined); // fetch padrão = GET.
    assert.equal(pedidos[0].url.includes('senha'), false);
});

test('identidade existente informa nome e limpa senha sem gravação', async (t) => {
    consulta(t, {exists: true, available: true, name: 'Nome global'});
    const estado = wizard(2);
    await estado.avancar();
    assert.equal(estado.dados.administrator_name, 'Nome global');
    assert.equal(estado.$refs.password.value, '');
    assert.equal(estado.$refs.confirmation.value, '');
    assert.equal(estado.passo, 3);
});

test('conta existente indisponível impede avançar', async (t) => {
    consulta(t, {exists: true, available: false, name: null});
    const estado = wizard(2);
    await estado.avancar();
    assert.equal(estado.passo, 2);
    assert.match(estado.aviso, /indisponível/);
});

test('Enter antes da revisão apenas avança e confirmação duplicada não envia', async () => {
    const estado = wizard();
    let recusas = 0;
    const evento = {preventDefault() { recusas++; }};
    estado.confirmar(evento);
    await Promise.resolve();
    assert.equal(recusas, 1);
    assert.equal(estado.enviando, false);
    estado.passo = 4;
    estado.confirmar(evento);
    assert.equal(estado.enviando, true);
    estado.confirmar(evento);
    assert.equal(recusas, 2);
});

test('erro de empresa preserva dados e exige redigitar senha nova antes da revisão', async (t) => {
    consulta(t, {exists: false, available: true, name: null});
    const estado = wizard(3);
    estado.$refs.password.value = '';
    estado.$refs.confirmation.value = '';
    await estado.avancar();
    assert.equal(estado.passo, 2);
    assert.equal(estado.dados.name, 'Cliente');
    assert.match(estado.aviso, /senha/);
});

test('resposta atrasada para outro e-mail não substitui a identidade atual', async (t) => {
    let liberar;
    t.mock.method(globalThis, 'fetch', () => new Promise((resolve) => { liberar = resolve; }));
    const estado = wizard(2);
    const pendente = estado.consultarAdministrador();
    estado.dados.administrator_email = 'outro@cliente.test';
    liberar({ok: true, json: async () => ({exists: true, available: true, name: 'Outra pessoa'})});
    assert.equal(await pendente, false);
    assert.equal(estado.identidade, null);
    assert.equal(estado.dados.administrator_name, 'Admin');
});

for (const [caso, email] of [['vazio', ''], ['inválido', 'sem-arroba']]) {
    test(`e-mail ${caso} permanece no passo 2 com feedback nativo`, async (t) => {
        const pedidos = consulta(t, {exists: false, available: true, name: null});
        const estado = wizard(2);
        estado.$refs.email.value = email;
        await estado.avancar();
        assert.equal(estado.passo, 2);
        assert.equal(estado.$refs.email.reports, 1);
        assert.equal(estado.$refs.email.validationVisible, true);
        assert.equal(pedidos.length, 0);
    });
}

test('e-mail válido avança e controles representativos do passo são validados', async (t) => {
    consulta(t, {exists: false, available: true, name: null});
    const estado = wizard(2);
    await estado.avancar();
    assert.equal(estado.passo, 3);
    assert.equal(estado.$refs.email.reports, 1);
    assert.equal(estado.$refs.password.reports, 1);
});

test('campo obrigatório inválido do estabelecimento mostra feedback', async () => {
    const estado = wizard();
    estado.dados.name = '';
    await estado.avancar();
    assert.equal(estado.passo, 1);
    assert.equal(estado.$refs.passo1.querySelectorAll()[0].validationVisible, true);
});

test('voltar e avançar mantém nome, e-mail e opções do formulário', async (t) => {
    consulta(t, {exists: false, available: true, name: null});
    const estado = wizard(2);
    Object.assign(estado.dados, {configure_company: '1', keep_platform_access: '1', company_name: 'Empresa preenchida'});
    const antes = {...estado.dados};
    await estado.avancar();
    estado.voltar();
    assert.deepEqual(estado.dados, antes);
    await estado.avancar();
    await estado.avancar();
    assert.equal(estado.passo, 4);
    assert.deepEqual(estado.dados, antes);
});

for (const falha of ['HTTP', 'rede']) {
    test(`falha ${falha} informa problema de consulta, preserva campos e permite tentar novamente`, async (t) => {
        let falhar = true;
        t.mock.method(globalThis, 'fetch', async () => {
            if (falhar && falha === 'rede') throw new Error('offline');
            return {ok: !falhar, json: async () => ({exists: false, available: true, name: null})};
        });
        const estado = wizard(2);
        const antes = {...estado.dados};
        await estado.avancar();
        assert.equal(estado.passo, 2);
        assert.match(estado.aviso, /Não foi possível verificar/);
        assert.doesNotMatch(estado.aviso, /conta está indisponível/);
        assert.equal(estado.consultando, false);
        assert.deepEqual(estado.dados, antes);
        assert.equal(estado.$refs.password.value, 'senha-valida-123');
        falhar = false;
        await estado.avancar();
        assert.equal(estado.passo, 3);
        assert.equal(estado.aviso, '');
    });
}

test('resumo usa bindings da view real e nunca inclui a senha mascarada', async (t) => {
    consulta(t, {exists: false, available: true, name: null});
    const estado = wizard(2);
    Object.assign(estado.dados, {plan: 'free', status: 'ACTIVE', locale: 'pt-BR', currency: 'BRL', timezone: 'UTC'});
    await estado.avancar();
    await estado.avancar();
    const view = readFileSync(new URL('../../resources/views/tenants/create.blade.php', import.meta.url), 'utf8');
    const review = view.match(/<section x-show="passo === 4"[\s\S]*?<\/section>/)[0];
    const maps = {planos: {free: 'Gratuito'}, situacoes: {ACTIVE: 'Ativo'}, idiomas: {'pt-BR': 'Português (Brasil)'}};
    const expressions = [...review.matchAll(/x-text="([^"]+)"/g)].map((match) => match[1]);
    assert.ok(expressions.length >= 10);
    const rendered = expressions.map((expression) => {
        const executable = expression.replace(/{{ Js::from\(\$(\w+)\) }}/g, (_, key) => JSON.stringify(maps[key]));
        return Function('dados', 'identidade', `return (${executable})`)(estado.dados, estado.identidade);
    }).join(' ');
    assert.match(rendered, /Cliente/);
    assert.match(rendered, /admin@cliente.test/);
    assert.match(rendered, /Gratuito/);
    assert.equal(rendered.includes(estado.$refs.password.value), false);
    assert.doesNotMatch(review, /\$refs\.(password|confirmation)|dados\.(password|password_confirmation)|type="password"|tipo="password"/);
    assert.match(view, /nome="password" tipo="password"/);
    assert.match(view, /nome="administrator_email" tipo="email"[\s\S]*?required/);
    assert.doesNotMatch(view, /x-model="dados\.password/);
});
