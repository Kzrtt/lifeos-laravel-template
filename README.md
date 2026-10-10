# LifeOS — template Laravel (instância vazia)

Painel de gestão de vida/projeto em **Laravel 12 modular + PostgreSQL**, pronto para uma
VPS: tarefas, projetos, notas, agenda, finanças, manifestações, citações, renúncias,
memória de longo prazo, senhas com escopo, servidor MCP e backup. **Sem nenhum dado,
mídia ou identidade de instância anterior.**

- 16 módulos em `app-modules/` (arquitetura modular com fronteiras testadas pelo CI)
- Multi-tenant por construção (uma `Entity` por instância) e auditoria append-only
- Front estático servido pelo próprio Laravel; só `lifeos-config.js` é gerado
- Deploy: `Dockerfile` + `compose.docker.yaml` (nginx → php-fpm → PostgreSQL 16)

## Subir uma instância (dev)

```bash
composer install
cp .env.example .env && php artisan key:generate
# .env: DB_* de um PostgreSQL 16 (crie o banco), LIFEOS_ENTITY_SLUG=<slug>, APP_URL
php artisan migrate
php artisan lifeos:nova-instancia <slug> "Nome da instância" --sub="subtítulo"
php artisan serve        # use um host próprio (ex.: lifeos.test), não localhost
```

`lifeos:nova-instancia` pede a senha mestre (sem eco) e a grava só como hash. Abra
`/lifeos/index.html` e entre com ela.

## Deploy na VPS

```bash
cp .env.example .env     # ou exporte as variáveis do compose
APP_URL=https://seu-dominio DB_PASSWORD=<forte> LIFEOS_ENTITY_SLUG=<slug> \
  docker compose -f compose.docker.yaml up -d --build
docker compose -f compose.docker.yaml exec app php artisan lifeos:nova-instancia <slug> "Nome"
```

O nginx escuta na porta `WEB_PORT` (padrão 3000), pensado para ficar atrás de um proxy
(Cloudflare Tunnel, Caddy, Traefik). Agende `php artisan lifeos:dump-banco` e copie o dump
para fora da máquina.

## Personalizar

- Imagens: troque `public/assets/images/{banner_lifeos.png,profile_lifeos.jpeg,favicon.png,icon.png}`
  (os que vêm aqui são placeholders neutros).
- Tema: `ent_tema` da entidade (`abismo` padrão; veja `public/assets/css/themes/lifeos/`).
- Textos: `tutorial.html` e `index.html` em `app-modules/painel/resources/pages/` ainda
  descrevem a arquitetura antiga; reescreva para a sua instância.

## Qualidade

```bash
composer test          # exige PostgreSQL (banco lifeos_test, ver phpunit.xml)
composer lint:check
```

## Documentação

[`CLAUDE.md`](CLAUDE.md) (regras de trabalho e ressalvas) · [`docs/`](docs/README.md)
(arquitetura, domínio, relatórios) · `/docs-site` no app rodando (regras de negócio por
módulo, DER e contrato da API).

> ⚠ **Licença de assets:** `public/assets/webfonts/` e o CSS `font-awesome-pro-master` são
> Font Awesome **Pro**. Confirme sua licença antes de tornar o repositório público.
