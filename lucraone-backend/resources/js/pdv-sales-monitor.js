/**
 * Monitor da tela de vendas do PDV.
 *
 * A venda chega ao backend pelo envio do PDV (a cada ~30s). Em vez de deixar o
 * usuário recarregando a página com ansiedade, verificamos em segundo plano se
 * o número de eventos do dia cresceu e avisamos — sem recarregar sozinho, para
 * não atrapalhar quem está mexendo nos filtros. Recarregar é sempre uma escolha
 * explícita no botão.
 */
export default function vendasPdvMonitor(cfg) {
    return {
        url: cfg.url,
        base: Number(cfg.base ?? 0),
        ultima: cfg.hora ?? '',
        novas: 0,
        verificando: false,
        timer: null,
        intervalo: 12000,

        init() {
            this.loop();
            // Pausa quando a aba está oculta; ao voltar, verifica na hora.
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.parar();
                } else {
                    this.loop();
                    this.verificar();
                }
            });
        },

        loop() {
            this.parar();
            this.timer = setInterval(() => this.verificar(), this.intervalo);
        },

        parar() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },

        async verificar() {
            if (this.verificando) {
                return;
            }
            this.verificando = true;
            try {
                const resposta = await fetch(this.url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (resposta.ok) {
                    const dados = await resposta.json();
                    if (typeof dados.verificado_em === 'string') {
                        this.ultima = dados.verificado_em;
                    }
                    if (typeof dados.eventos === 'number' && dados.eventos > this.base) {
                        this.novas = dados.eventos - this.base;
                    }
                }
            } catch (erro) {
                // Silencioso de propósito: a verificação é um conforto, não pode
                // poluir a tela com erro de rede momentâneo.
            } finally {
                this.verificando = false;
            }
        },

        recarregar() {
            window.location.reload();
        },
    };
}
