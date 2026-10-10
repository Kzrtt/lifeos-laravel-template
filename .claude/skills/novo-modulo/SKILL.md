---
name: novo-modulo
description: Cria um módulo novo em app-modules com TODAS as convenções do template — composer.json com namespace correto, provider, estrutura src/routes/database/tests, registro no ArchTest (DOMAIN_MODULES) e nas docs (BANCO.md, MODULOS.md). Use quando o usuário pedir um módulo novo ou quando uma feature exigir um bounded context que ainda não existe.
---

# Criar um módulo novo

Um módulo é um *bounded context* — antes de criar, confirme que deve existir
(critérios em `docs/arquitetura/ARQUITETURA.md` §"Como desenhar OS SEUS
módulos"). Na dúvida entre criar módulo ou crescer um existente: cresça o
existente até a fronteira doer (anti-over-engineering).

## Passo 1 — Decisões prévias (com o usuário se ambíguo)

- **Nome**: kebab-case, plural quando for coleção de agregados (`orders`),
  singular quando for capacidade (`billing`). Namespace: PascalCase do nome.
- **Papel**: dono de agregado (terá Models/tabelas)? fornecedor de capacidade
  (implementa contrato do core)? reator (listeners)? Isso define a árvore.
- **Fronteiras**: de quais capacidades de OUTROS módulos ele vai precisar
  (→ contratos no core) e o que ele oferece (→ contrato dele no core)?

## Passo 2 — Scaffold

Use o gerador do pacote e complete o que faltar:

```bash
php artisan make:module <nome>
composer update modules/<nome>
```

Confira/ajuste o gerado para o padrão do template (compare com um módulo
existente — `tenancy` é o exemplo mínimo):

1. `app-modules/<nome>/composer.json` — name `modules/<nome>` (ou o vendor do
   projeto), PSR-4 `<Namespace>\<Modulo>\` → `src/` (+ Tests/Factories/Seeders),
   provider em `extra.laravel.providers`.
2. `src/Providers/<Modulo>ServiceProvider.php` — vazio até o módulo ter binds
   ou listeners; binds de contrato do core moram AQUI.
3. Estrutura mínima: `src/`, `database/migrations/`, `database/factories/`,
   `routes/` (só se expuser rotas), `tests/` (com `.gitkeep` se vazio).

## Passo 3 — Registrar nas cercas e nas docs (OBRIGATÓRIO)

1. **`tests/Arch/ArchTest.php`**: adicione `'<Namespace>\<Modulo>'` à constante
   `DOMAIN_MODULES` — o módulo ganha todas as regras de fronteira de graça.
   SEM este passo o módulo nasce fora da lei.
2. **`docs/arquitetura/BANCO.md`**: se o módulo for dono de tabelas, adicione-as
   à tabela de ownership (prefixo de 3 letras único! confira colisão) e ao ER.
3. **`docs/arquitetura/MODULOS.md`**: adicione o módulo à árvore de visão geral.
4. **`CLAUDE.md`**: mapa de pastas/módulos.
5. **`config/models.php`**: se algum model deste módulo for referenciado em
   relação Eloquent por outro módulo, adicione a chave.

## Passo 4 — Primeiro conteúdo (se dono de agregado)

Siga o molde do `orders` (ou de `docs/arquitetura/BANCO.md` se os exemplos já
foram removidos), nesta ordem: migration (prefixo, UUID, FK `entity_ent_id` se
multi-tenant, timestamps prefixados, SoftDeletes) → Model (`Entityable`,
`HasUuids`, `#[ObservedBy]`, casts, `newFactory`) → Observer (estende
`AuditObserver`, `$hidden` para sensíveis) → Factory → Enum de status se houver
máquina de estados → teste de tenancy/auditoria do model.

## Passo 5 — Validar

```bash
php artisan modules:list        # o módulo aparece
composer test                   # suíte verde (incl. ArchTest com o módulo novo)
composer lint:check
```

Verde é pré-condição para declarar o módulo criado. Se a criação faz parte de
uma feature, siga o ciclo `/nova-feature` normalmente (o módulo entra no report
da feature); se foi criação avulsa, registre um mini-report mesmo assim (regra
de ouro nº 10).
