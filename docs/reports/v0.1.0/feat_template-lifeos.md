# feat: template LifeOS vazio

Entregue em 2026-10-10.

## O que mudou

Derivado do `lifeos-laravel` (instância privada), sem histórico git:

- Removido o módulo `arquivo` (blog, galeria, página profissional, jogo, publicação) e as
  tabelas dele; `/` e `/index.html` redirecionam para `/lifeos/index.html`; blog desligado
  por padrão (`ent_blog_habilitado` default `false`).
- Removidos dados e mídias pessoais: `backups/`, `database/media/`, `public/qui-videti/`,
  `public/assets/{gallery,files}`, imagens de portfólio, JS exclusivo do arquivo.
- Removida a tooling da migração do Supabase (importador, baixar-mídias, restore-backup,
  conexão `supabase`, fixtures SQL), o `deploy.yml` (Tailscale/IP) e os relatórios e o plano
  de migração do projeto original.
- Neutralizada a identidade: slug `demo`, título/rodapé `LifeOS`, mocks do front, comentários
  "pedido do …" → "do dono", campo `custo_kurt` → `custo_dono` (código, fixture e contrato MCP).
- Imagens de banner/avatar/favicon trocadas por placeholders neutros.
- Docker/compose com placeholders; `README.md` e `CLAUDE.md` reescritos; o README do
  template-base virou `docs/arquitetura/TEMPLATE_BASE.md`.

## Verificação

- `php artisan test`: 218 testes / 1748 asserções verdes; `pint --test` e ArchTest verdes.
- Smoke: `migrate`, `lifeos:nova-instancia demo`, `/` → 302, `/lifeos/*.html`,
  `lifeos-config.js`, `/docs-site` → 200; login por senha mestre responde `ok`, senha errada
  `unauthorized`. O banco só tem a entidade, uma senha (hash) e os vocabulários seed.

## Ressalvas

- Testes rodaram num PostgreSQL 16 temporário (pgserver), não no compose; o Docker não foi
  exercitado (sem acesso ao daemon nesta máquina).
- Font Awesome Pro versionado em `public/assets/`: verificar licença antes de publicar.
- `tutorial.html`/`index.html` do painel e algumas dicas de `senhas.html`/`temas.html` ainda
  citam Supabase, GitHub Pages e `gate.js`; textos não reescritos.
- Comparação "byte a byte" com o front antigo foi removida (não faz sentido no template).
- `docs/arquitetura/*` e `reports/SISTEMA.md` são do template-base e descrevem exemplos
  (`orders/payments`) que não estão neste repositório.
