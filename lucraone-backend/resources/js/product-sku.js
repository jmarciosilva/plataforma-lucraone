export function sugerirSku(nome) {
    return String(nome ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-|-$/g, '')
        .slice(0, 100).replace(/-$/g, '') || 'PRODUTO';
}

export default function skuProduto(inicial) {
    return {
        nome: String(inicial.nome ?? ''),
        sku: String(inicial.sku ?? ''),
        automatico: !inicial.editando && inicial.automatico,
        editando: inicial.editando,
        init() {
            this.nomeAlterado();
        },
        nomeAlterado() {
            if (this.automatico && !this.editando) this.sku = this.nome.trim() ? sugerirSku(this.nome) : '';
        },
        codigoAlterado() {
            this.automatico = false;
        },
        gerar() {
            this.sku = sugerirSku(this.nome);
            this.automatico = !this.editando;
        },
    };
}
