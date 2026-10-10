# CLAUDE.md — LifeOS Template (Laravel modular)

Guia de padrões para qualquer pessoa (ou agente) que trabalhe neste repositório.
**Leia antes de escrever código.** As regras abaixo são executáveis: Pest + Pint +
ArchTest quebram se forem violadas.

## Visão

Template **vazio** do LifeOS em Laravel 12 modular, para subir uma nova instância em
uma VPS. Sem dados, sem mídias e sem identidade de nenhuma instância anterior: o
primeiro passo de qualquer deploy é `php artisan lifeos:nova-instancia`.

O front é HTML/JS estático servido cru pelo backend (`StaticPageController`, não é
Blade). O contrato front↔back é fixo: `POST {token, action, ...}` →
`{ok:true,...}` | `{ok:false,error:"codigo"}`, nos caminhos `/functions/v1/lifeos-*` e
`/rest/v1/rpc/*`. A chave `supabaseUrl` de `lifeos-config.js` é nome histórico do
contrato; ela recebe a URL desta própria aplicação. Só `lifeos-config.js` é gerado
(`GET /assets/js/lifeos-config.js`), a partir da entidade e do `.env`.

Desvios deliberados do template genérico: o backend **serve** telas estáticas;
testes e dev rodam em **PostgreSQL** (arrays `text[]`, índices parciais — sqlite não
serve); fila `sync`, sem Redis/Horizon. Em dev, use um host próprio (ex.:
`lifeos.test`): o front entra em modo mock em `localhost`/`127.0.0.1`.

## Módulos

`core` (shared kernel), `tenancy`, `acesso`, `vocabularios`, `projetos`, `notas`,
`visoes`, `agenda`, `manifestacoes`, `citacoes`, `memoria`, `renuncias`, `financas`,
`backup`, `mcp`, `painel`.

O módulo `arquivo` (blog/galeria/páginas públicas) **não existe** neste template. O
front já tem o interruptor `LIFEOS_CONFIG.blog.habilitado`, que fica `false` por
padrão (`ent_blog_habilitado`); `/` redireciona para `/lifeos/index.html`.

## A verdade mora na documentação

`docs/` é canônica; se este arquivo ou o código divergirem dela, a documentação vence.
Em ambiguidade genuína, **pare e pergunte** — decisão de produto é do dono, não da IA.

## As regras de ouro

1. **Eloquent é a única camada de dados.** Sem SQL cru/query builder para regra de negócio.
2. **Fronteira de módulo é sagrada.** Lógica em **Actions** dentro do módulo dono; REST, MCP
   e CLI são adaptadores finos. Entre módulos: só **contrato**, **evento** ou
   **read model/DTO** do `core` — nunca importar o interno de outro. O ArchTest quebra o CI.
3. **Multi-tenancy sempre.** Todo model com escopo de tenant usa `Entityable` + `EntityScope`;
   em jobs/CLI/canais sem sessão, restaure o contexto com `TenantContext::runAs()`.
4. **`declare(strict_types=1)` em todo arquivo PHP.**
5. **Segredos nunca em claro.** Senhas só como hash; segredos cifrados com a `APP_KEY`;
   `.env.example` só com placeholders; nada de credencial real no repositório.
6. **Tudo auditável.** Mutações gravadas por Observers que estendem o `AuditObserver` do core
   em `audit_logs` (append-only).
7. **Convenções de banco:** prefixo de 3 letras por tabela, UUID PK, SoftDeletes, FK
   `{tabela_singular}_{pk}`.
8. **Padrões sempre verdes.** `composer lint:check && composer test` antes de commitar.
9. **Toda feature fecha com relatório** em `docs/reports/vX.Y.Z/feat_*.md` e uma entrada
   `pending` em `docs/reports/sync-state.json`; `/sync-docs` propaga para a doc evergreen.

## Mapa de pastas

```
app/                 # host enxuto: comandos lifeos:*, middlewares de borda, providers
app-modules/<mod>/   # todo o domínio: src/, routes/, database/, resources/, tests/
config/lifeos.php    # entity_slug (LIFEOS_ENTITY_SLUG) e session_key
public/assets/       # front estático compartilhado (js, css/temas, imagens placeholder)
docs/                # arquitetura/ (como), dominio/ (o quê), reports/ (entregas)
docs-site/           # site estático público em /docs-site: regras por módulo, DER, contrato
docker/ Dockerfile compose.docker.yaml   # deploy em VPS: nginx -> php-fpm -> postgres 16
tests/               # Unit, Feature, Arch da raiz; cada módulo tem tests/ próprio
.claude/skills/      # prontuario, dominio, nova-feature, sync-docs, novo-modulo
```

## Nova instância

```bash
cp .env.example .env && php artisan key:generate   # configure DB_* (PostgreSQL)
php artisan migrate
php artisan lifeos:nova-instancia <slug> "<Nome>" --sub="<subtítulo>"   # pede a senha mestre
# .env: LIFEOS_ENTITY_SLUG=<slug>
```

A senha mestre nunca tem valor padrão; é gravada só como hash. Identidade (nome, banner,
avatar, tema) vem da entidade; troque `public/assets/images/{banner_lifeos.png,
profile_lifeos.jpeg,favicon.png,icon.png}` pelos da instância.

## Comandos úteis

```bash
composer test          # pest: Unit + Feature + Modules + Arch (exige PostgreSQL: DB lifeos_test)
composer lint          # pint (corrige)       composer lint:check   # CI
php artisan modules:list
php artisan lifeos:dump-banco                  # pg_dump gzip (agendado diariamente)
php artisan audit:query <tabela> <id>
docker compose -f compose.docker.yaml up -d --build   # deploy na VPS
```

## Ressalvas conhecidas do template

- `public/assets/webfonts/` e `public/assets/css/font-awesome-pro-master.min.css` são do
  **Font Awesome Pro** (licença paga): confirme a licença antes de publicar o repositório.
- Os textos do `tutorial.html` e do `index.html` do painel ainda narram a arquitetura
  antiga (Supabase + GitHub Pages) e a metade pública; reescreva-os para a sua instância.
- As skills `/prontuario` e `/novo-modulo` citam os exemplos `orders/payments/notifications`
  do template genérico; esses módulos não existem aqui.
- A pasta `docs/arquitetura/` e o `README.md` do template genérico foram preservados como
  referência de arquitetura; o fluxo de pedido→cobrança descrito lá é ilustrativo.

## Estado atual

218 testes / 1748 asserções verdes, Pint e ArchTest verdes. Detalhes em
[`docs/reports/v0.1.0/feat_template-lifeos.md`](docs/reports/v0.1.0/feat_template-lifeos.md).
