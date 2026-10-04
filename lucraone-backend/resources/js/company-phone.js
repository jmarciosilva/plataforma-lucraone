export function mascararCelular(valor) {
    const digitos = String(valor ?? '').replace(/[^0-9]/g, '').slice(0, 11);
    if (!digitos) return '';
    if (digitos.length <= 2) return `(${digitos}`;
    const numero = digitos.slice(2);
    return `(${digitos.slice(0, 2)}) ${numero.slice(0, 5)}${numero.length > 5 ? '-' + numero.slice(5) : ''}`;
}

export default function telefoneCompany() {
    return {
        init() {
            this.$el.value = mascararCelular(this.$el.value);
        },
        formatar() {
            const campo = this.$el;
            const antes = campo.value.slice(0, campo.selectionStart ?? campo.value.length);
            const digitosAntes = antes.replace(/[^0-9]/g, '').length;
            campo.value = mascararCelular(campo.value);
            let posicao = 0;
            let encontrados = 0;
            while (posicao < campo.value.length && encontrados < digitosAntes) {
                if (/[0-9]/.test(campo.value[posicao])) encontrados++;
                posicao++;
            }
            campo.setSelectionRange(posicao, posicao);
        },
    };
}
