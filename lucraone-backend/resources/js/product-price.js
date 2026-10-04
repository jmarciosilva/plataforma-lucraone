// Valores monetários são calculados em centavos; a margem usa centésimos de %.
function decimalInteiro(valor, digitos, limite) {
    const texto = String(valor ?? '');
    if (!new RegExp(`^\\d{1,${digitos}}(?:[.,]\\d{1,2})?$`).test(texto)) return null;
    const [inteiro, fracao = ''] = texto.replace(',', '.').split('.');
    const resultado = BigInt(inteiro) * 100n + BigInt(fracao.padEnd(2, '0'));
    return resultado <= limite ? resultado : null;
}

export function centavos(valor) {
    return decimalInteiro(valor, 10, 999999999999n);
}

function formatar(valor) {
    const negativo = valor < 0n;
    const absoluto = negativo ? -valor : valor;
    return `${negativo ? '-' : ''}${absoluto / 100n},${String(absoluto % 100n).padStart(2, '0')}`;
}

export function sugerirPreco(custo, margem) {
    const c = centavos(custo);
    const m = decimalInteiro(margem, 4, 100000n);
    if (c === null || c <= 0n || m === null) return null;
    const resultado = (c * (10000n + m) + 5000n) / 10000n;
    return resultado <= 999999999999n ? formatar(resultado) : null;
}

export function margemPercentual(custo, venda) {
    const c = centavos(custo);
    const v = centavos(venda);
    if (c === null || c <= 0n || v === null) return null;
    const diferenca = (v - c) * 1000000n;
    const absoluto = diferenca < 0n ? -diferenca : diferenca;
    const arredondado = (absoluto + c / 2n) / c;
    return `${diferenca < 0n && arredondado !== 0n ? '-' : ''}${arredondado / 10000n}.${String(arredondado % 10000n).padStart(4, '0')}`;
}

export default function precoProduto(inicial) {
    return {
        custos: inicial.custos ?? {},
        tipo: inicial.tipo ?? 'sale',
        moeda: inicial.moeda ?? 'BRL',
        valor: inicial.valor ?? '',
        margem: inicial.margem ?? '',
        erro: '',
        get custo() { return this.custos[String(this.moeda).toUpperCase()] ?? ''; },
        get custoAtual() {
            const c = centavos(this.custo);
            return c !== null ? formatar(c) : null;
        },
        get podeSugerir() {
            const c = centavos(this.valor);
            return this.tipo === 'cost' && c !== null && c > 0n;
        },
        get precoSugerido() {
            return this.podeSugerir ? sugerirPreco(this.valor, this.margem) : null;
        },
        get margemEfetiva() {
            const c = centavos(this.custo);
            const v = centavos(this.valor);
            if (this.tipo !== 'sale' || c === null || c <= 0n || v === null || v <= 0n) return null;
            return margemPercentual(this.custo, this.valor)?.replace('.', ',') ?? null;
        },
        aplicar() {
            if (!this.podeSugerir) return;
            const sugestao = this.precoSugerido;
            this.erro = sugestao === null
                ? 'Informe uma margem entre 0% e 1000%, com até duas casas decimais. O preço sugerido precisa caber no campo de valor.'
                : '';
            if (sugestao !== null) {
                this.valor = sugestao;
                this.tipo = 'sale';
            }
        },
    };
}
