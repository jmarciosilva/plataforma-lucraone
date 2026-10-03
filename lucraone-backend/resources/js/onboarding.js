export default function onboardingCliente(inicial, consultaUrl) {
    return {
        passo: inicial.passo,
        dados: inicial.dados,
        identidade: null,
        emailConsultado: null,
        consultando: false,
        aviso: '',
        enviando: false,
        async consultarAdministrador() {
            const email = this.dados.administrator_email.trim();
            if (!this.$refs.email.checkValidity()) {
                this.$refs.email.reportValidity();
                return false;
            }
            if (!email) return false;
            if (this.emailConsultado === email && this.identidade) return this.identidade.available;
            this.consultando = true;
            this.aviso = '';
            try {
                const resposta = await fetch(`${consultaUrl}?${new URLSearchParams({email})}`, {
                    headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store',
                });
                if (!resposta.ok) throw new Error('consulta indisponível');
                const identidade = await resposta.json();
                if (email !== this.dados.administrator_email.trim()) return false;
                this.identidade = identidade;
                this.emailConsultado = email;
                if (identidade.exists && identidade.available) {
                    this.dados.administrator_name = identidade.name;
                    this.$refs.password.value = '';
                    this.$refs.confirmation.value = '';
                }
                if (!identidade.available) this.aviso = 'Esta conta está indisponível para vínculo. Escolha uma pessoa com conta ativa.';
                return identidade.available;
            } catch {
                this.aviso = 'Não foi possível verificar o e-mail. Tente novamente antes de continuar.';
                return false;
            } finally {
                this.consultando = false;
            }
        },
        async avancar() {
            if (this.passo === 2 && !await this.consultarAdministrador()) return;
            if (this.passo === 3 && !await this.consultarAdministrador()) {
                this.passo = 2;
                return;
            }
            await this.$nextTick();
            const campos = this.$refs[`passo${this.passo}`].querySelectorAll('input, select');
            for (const campo of campos) {
                if (!campo.disabled && !campo.reportValidity()) return;
            }
            if (this.passo === 2 && !this.identidade.exists && this.$refs.password.value !== this.$refs.confirmation.value) {
                this.aviso = 'A confirmação deve ser igual à senha inicial.';
                return;
            }
            // Após erro do servidor, dados comuns voltam pelo old(), mas as
            // senhas nunca são guardadas na sessão. Recolha a credencial antes
            // de revisar novamente, sem perder a configuração da empresa.
            if (this.passo === 3 && !this.identidade.exists && (
                !this.$refs.password.value || !this.$refs.password.checkValidity()
                || this.$refs.password.value !== this.$refs.confirmation.value
            )) {
                this.passo = 2;
                this.aviso = 'Por segurança, digite e confirme a senha inicial novamente.';
                this.$nextTick(() => this.$refs.titulo.focus());
                return;
            }
            this.aviso = '';
            this.passo = Math.min(4, this.passo + 1);
            this.$nextTick(() => this.$refs.titulo.focus());
        },
        voltar() {
            this.passo = Math.max(1, this.passo - 1);
            this.aviso = '';
            this.$nextTick(() => this.$refs.titulo.focus());
        },
        confirmar(evento) {
            if (this.passo !== 4) {
                evento.preventDefault();
                this.avancar();
                return;
            }
            if (this.enviando) {
                evento.preventDefault();
                return;
            }
            this.enviando = true;
        },
    };
}
