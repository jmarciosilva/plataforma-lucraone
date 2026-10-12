export default (url, terminal, csrf) => ({
    url,
    terminal,
    csrf,
    modal: false,
    stage: 'confirm',
    reason: '',
    confirmed: false,
    busy: false,
    code: '',
    expiresAt: '',
    error: '',
    copied: false,

    init() {
        window.addEventListener('pagehide', () => this.close(), { once: true });
    },

    get expiryLabel() {
        if (!this.expiresAt) return '';
        const date = new Date(this.expiresAt);
        if (Number.isNaN(date.getTime())) return 'Consulte o horário de expiração do código.';
        return new Intl.DateTimeFormat('pt-BR', {
            dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Sao_Paulo',
        }).format(date) + ' (horário de Brasília)';
    },

    open() {
        this.stage = 'confirm';
        this.reason = '';
        this.confirmed = false;
        this.error = '';
        this.modal = true;
    },

    close() {
        this.modal = false;
        this.code = '';
        this.expiresAt = '';
        this.copied = false;
    },

    async issue() {
        if (this.busy || !this.reason || !this.confirmed) return;
        this.busy = true;
        this.error = '';

        try {
            const response = await fetch(this.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: AbortSignal.timeout(20000),
                body: JSON.stringify({ reason: this.reason }),
            });

            let payload = null;
            try { payload = await response.json(); } catch { /* resposta inesperada */ }

            if (response.ok && typeof payload?.data?.recovery_code === 'string' && payload.data.expires_at) {
                this.code = payload.data.recovery_code;
                this.expiresAt = payload.data.expires_at;
                this.stage = 'result';
                return;
            }

            if (response.status >= 500 || (response.ok && !payload?.data?.recovery_code)) {
                this.stage = 'uncertain';
                return;
            }

            this.error = payload?.message || payload?.errors?.reason?.[0]
                || 'Não foi possível iniciar a recuperação. Verifique sua autorização e tente novamente.';
        } catch {
            this.stage = 'uncertain';
        } finally {
            this.busy = false;
        }
    },

    async copyCode() {
        try {
            await navigator.clipboard.writeText(this.code);
            this.copied = true;
        } catch {
            this.error = 'Não foi possível copiar automaticamente. Selecione e copie o código.';
        }
    },
});
