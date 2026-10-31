/**
 * Simulador de promoção da tela de vendas do PDV.
 *
 * Envia produto + % de desconto ao backend e mostra o impacto na lucratividade
 * e o ponto de equilíbrio, com base nas vendas do período. A formatação (moeda,
 * %, número) é feita aqui para o resultado ler em português.
 */
export default function vendasPdvSimulador(cfg) {
    return {
        url: cfg.url,
        produto: '',
        desconto: '',
        carregando: false,
        erro: '',
        r: null,

        async simular() {
            this.erro = '';
            this.r = null;

            if (!this.produto) {
                this.erro = 'Escolha um produto.';
                return;
            }
            const d = parseFloat(String(this.desconto).replace(',', '.'));
            if (!(d > 0 && d < 100)) {
                this.erro = 'Informe um desconto entre 1 e 99%.';
                return;
            }

            this.carregando = true;
            try {
                const u = new URL(this.url, window.location.origin);
                u.searchParams.set('product_id', this.produto);
                u.searchParams.set('desconto', String(d));
                const resp = await fetch(u, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await resp.json();
                if (!resp.ok || !data.ok) {
                    this.erro = data.erro || 'Não foi possível simular agora.';
                    return;
                }
                this.r = data;
            } catch (e) {
                this.erro = 'Falha ao simular. Tente novamente.';
            } finally {
                this.carregando = false;
            }
        },

        moeda(v) {
            return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        pct(v) {
            return v == null ? '—' : Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
        },
        num(v) {
            return Number(v || 0).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
        },
    };
}
