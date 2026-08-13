# ADR-001 — Arquitetura Modular Monolith

**Status:** Aceito  
**Data:** 2026-08-13  
**Contexto:** Fase de Fundação

---

## Contexto

Ao iniciar o LUCRAONE, precisamos definir uma estrutura arquitetural que:

1. Organize o código de forma clara e escalável
2. Permita que diferentes domínios (Tenancy, Companies, Identity, etc.) evoluam independentemente
3. Não introduza prematuramente complexidade de microserviços
4. Facilite testes e manutenção

Duas principais opções foram consideradas:

### Opção A: Estrutura Flat Tradicional
```
app/
├── Models/
├── Controllers/
├── Services/
└── Repositories/
```

**Problemas:**
- Sem limites claros entre domínios
- Difícil localizar código relacionado a um módulo específico
- Escalabilidade ruim com crescimento

### Opção B: Modular Monolith (Escolhida)
```
app/Modules/
├── Tenancy/
├── Companies/
├── Identity/
└── ...
```

**Benefícios:**
- Domínios bem definidos e separados
- Cada módulo é auto-contido (Domain, Application, Infrastructure, Http)
- Evolução futura para microserviços é possível
- Facilita onboarding de novos desenvolvedores

---

## Decisão

Adotar **Modular Monolith** como padrão arquitetural inicial.

```
app/Modules/{Module}/
├── Domain/
│   ├── Models/
│   ├── Events/
│   └── Exceptions/
├── Application/
│   ├── Actions/
│   └── DTOs/
├── Infrastructure/
│   └── Persistence/
└── Http/
    ├── Controllers/
    ├── Requests/
    └── Resources/
```

Cada módulo deve:
- Ser responsável por um domínio ou agregado
- Possuir sua própria lógica de negócio
- Estar isolado de outros módulos (sem acoplamento)
- Ter testes abrangentes

---

## Consequências

### Positivas

✅ Código organizado por domínio (Domain-Driven Design)  
✅ Facilita testes e compreensão do sistema  
✅ Permite crescimento sem necessidade de refatoração drástica  
✅ Preparação futura para separação em microserviços  

### Negativas

⚠️ Estrutura de diretórios mais profunda  
⚠️ Requer disciplina do time para manter separação  
⚠️ Pode parecer "overkill" inicialmente para equipe pequena  

### Mitigação

- Documentação clara dos padrões
- Code review enfocado em coesão de módulos
- Testes automatizados verificando isolamento

---

## Referências

- Laravel Best Practices
- Domain-Driven Design (Evans)
- Clean Architecture (Martin)

---

## Alternativas Rejeitadas

### Microserviços
**Motivo:** Complexidade prematura. Será considerado quando volume justificar.

### Estrutura Flat
**Motivo:** Escalabilidade limitada. Dificulta manutenção futura.

---
