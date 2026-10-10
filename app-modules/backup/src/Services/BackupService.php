<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Scopes\EntityScope;

/**
 * Monta os arquivos SQL do backup de UMA instância.
 *
 * ÚNICA exceção à regra "Eloquent é a única camada de dados" (documentada no
 * plano, §13): este serviço VARRE O CATÁLOGO do banco (Schema) em vez de listar
 * models à mão, para que uma tabela nova entre no backup sem ninguém lembrar de
 * registrá-la. É infraestrutura de exportação, não regra de negócio.
 *
 * Só saem as linhas da entidade de quem pede (`entity_ent_id`) — com duas
 * instâncias, a primeira jamais baixa os dados da segunda. O formato difere do
 * Supabase (colunas com prefixo): backups antigos restauram pelo importador.
 */
final class BackupService
{
    /** Tabelas do framework: não são dado do LifeOS. */
    private const IGNORADAS = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'];

    /** Credenciais: só entram se `incluir_credenciais`. */
    private const CREDENCIAIS = ['access_tokens', 'token_pages', 'admin_configs'];

    private const LINHAS_POR_INSERT = 500;

    /**
     * @return array{gerado_em: string, arquivos: list<array{nome: string, conteudo: string}>, tabelas: list<array{tabela: string, linhas: int}>}
     */
    public function gerar(string $entityId, bool $incluirCredenciais): array
    {
        $geradoEm = now()->utc()->format('Y-m-d\TH:i:s.v\Z');
        $dump = $this->tabelas($entityId, $incluirCredenciais);
        $cabecalho = "-- Backup LifeOS · gerado em {$geradoEm}";

        $arquivos = [];
        $arquivos[] = ['nome' => '00_limpar.sql', 'conteudo' => $this->limpar($dump, $entityId, $cabecalho)];

        $n = 1;
        foreach ($dump as $t) {
            if ($t['linhas'] === []) {
                continue;
            }
            $arquivos[] = [
                'nome' => str_pad((string) $n++, 2, '0', STR_PAD_LEFT).'_'.$t['tabela'].'.sql',
                'conteudo' => $this->inserts($t, $cabecalho),
            ];
        }

        $resumo = array_map(fn (array $t): array => ['tabela' => $t['tabela'], 'linhas' => count($t['linhas'])], $dump);
        array_unshift($arquivos, ['nome' => 'LEIAME.md', 'conteudo' => $this->leiame($geradoEm, $resumo, $incluirCredenciais)]);

        return ['gerado_em' => $geradoEm, 'arquivos' => $arquivos, 'tabelas' => $resumo];
    }

    /**
     * Tabelas com linhas da entidade, em ordem de dependência (pais primeiro).
     *
     * @return list<array{tabela: string, colunas: list<string>, json: list<string>, depende_de: list<string>, linhas: list<array<string, mixed>>}>
     */
    private function tabelas(string $entityId, bool $incluirCredenciais): array
    {
        $tabelas = [];

        foreach (Schema::getTables() as $tabela) {
            $nome = $tabela['name'];
            if (($tabela['schema'] ?? 'public') !== 'public' || in_array($nome, self::IGNORADAS, true)) {
                continue;
            }
            if (! $incluirCredenciais && in_array($nome, self::CREDENCIAIS, true)) {
                continue;
            }

            $colunas = Schema::getColumns($nome);
            $nomes = array_map(fn (array $c): string => $c['name'], array_filter($colunas, fn (array $c): bool => ! ($c['generation'] ?? null)));

            // A entidade em si, ou as linhas que carregam entity_ent_id.
            if ($nome === 'entities') {
                $query = DB::table($nome)->where('ent_id', $entityId);
            } elseif (in_array(EntityScope::COLUMN, $nomes, true)) {
                $query = DB::table($nome)->where(EntityScope::COLUMN, $entityId);
            } else {
                continue;
            }

            $pk = Schema::getIndexes($nome);
            $ordem = collect($pk)->firstWhere('primary', true)['columns'] ?? [];
            foreach ($ordem as $coluna) {
                $query->orderBy($coluna);
            }

            $json = array_values(array_map(
                fn (array $c): string => $c['name'],
                array_filter($colunas, fn (array $c): bool => in_array($c['type_name'], ['json', 'jsonb'], true)),
            ));

            $tabelas[] = [
                'tabela' => $nome,
                'colunas' => array_values($nomes),
                'json' => $json,
                'depende_de' => array_values(array_unique(array_filter(
                    array_map(fn (array $fk): string => $fk['foreign_table'], Schema::getForeignKeys($nome)),
                    fn (string $d): bool => $d !== $nome,
                ))),
                'linhas' => $query->get()->map(fn (object $r): array => (array) $r)->all(),
            ];
        }

        return $this->ordenar($tabelas);
    }

    /**
     * @param  list<array{tabela: string, colunas: list<string>, json: list<string>, depende_de: list<string>, linhas: list<array<string, mixed>>}>  $tabelas
     * @return list<array{tabela: string, colunas: list<string>, json: list<string>, depende_de: list<string>, linhas: list<array<string, mixed>>}>
     */
    private function ordenar(array $tabelas): array
    {
        $nomes = array_column($tabelas, 'tabela');
        $pendentes = [];
        foreach ($tabelas as $t) {
            $pendentes[$t['tabela']] = array_values(array_intersect($t['depende_de'], $nomes));
        }
        $porNome = array_column($tabelas, null, 'tabela');
        $saida = [];

        while ($pendentes !== []) {
            $prontas = array_keys(array_filter($pendentes, fn (array $d): bool => $d === []));
            sort($prontas);
            if ($prontas === []) { // ciclo: despeja o resto na ordem
                foreach (array_keys($pendentes) as $nome) {
                    $saida[] = $porNome[$nome];
                }
                break;
            }
            foreach ($prontas as $nome) {
                $saida[] = $porNome[$nome];
                unset($pendentes[$nome]);
            }
            foreach ($pendentes as $nome => $deps) {
                $pendentes[$nome] = array_values(array_diff($deps, $prontas));
            }
        }

        return $saida;
    }

    /** @param list<array{tabela: string, colunas: list<string>, json: list<string>, depende_de: list<string>, linhas: list<array<string, mixed>>}> $dump */
    private function limpar(array $dump, string $entityId, string $cabecalho): string
    {
        $linhas = [
            $cabecalho, '--',
            '-- Remove as linhas desta instância das tabelas que o backup repõe (filhas primeiro).',
            '-- Só toca a entidade abaixo: outras instâncias do mesmo banco ficam intactas.',
            '',
        ];

        foreach (array_reverse($dump) as $t) {
            $coluna = $t['tabela'] === 'entities' ? 'ent_id' : EntityScope::COLUMN;
            $linhas[] = "delete from {$this->tab($t['tabela'])} where {$this->ident($coluna)} = ".$this->literal($entityId).';';
        }

        return implode("\n", $linhas)."\n";
    }

    /** @param array{tabela: string, colunas: list<string>, json: list<string>, depende_de: list<string>, linhas: list<array<string, mixed>>} $t */
    private function inserts(array $t, string $cabecalho): string
    {
        $cols = implode(', ', array_map($this->ident(...), $t['colunas']));
        $partes = [$cabecalho, "-- {$this->tab($t['tabela'])} · ".count($t['linhas']).' linha(s)', ''];

        foreach (array_chunk($t['linhas'], self::LINHAS_POR_INSERT) as $bloco) {
            $bloco = array_map(function (array $linha) use ($t): array {
                foreach ($t['json'] as $coluna) { // jsonb sai como texto: decodifica para não duplo-codificar
                    if (isset($linha[$coluna]) && is_string($linha[$coluna])) {
                        $linha[$coluna] = json_decode($linha[$coluna], true);
                    }
                }

                return $linha;
            }, $bloco);

            $json = "[\n".implode(",\n", array_map(fn (array $l): string => json_encode($l, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $bloco))."\n]";
            $partes[] = "insert into {$this->tab($t['tabela'])} ({$cols})";
            $partes[] = "select {$cols} from jsonb_populate_recordset(null::{$this->tab($t['tabela'])}, {$this->dollar($json)});";
            $partes[] = '';
        }

        return implode("\n", $partes);
    }

    /** @param list<array{tabela: string, linhas: int}> $resumo */
    private function leiame(string $geradoEm, array $resumo, bool $incluirCredenciais): string
    {
        $total = array_sum(array_column($resumo, 'linhas'));
        $linhas = [
            '# Backup do LifeOS', '',
            "Gerado em {$geradoEm}.",
            $incluirCredenciais
                ? 'Inclui credenciais: `access_tokens` (senhas como HASH, nunca em claro), `token_pages` e `admin_configs` (valores CIFRADOS com a `APP_KEY` do servidor de origem: sem a mesma `APP_KEY`, o token do MCP e o PAT não decifram). **Guarde este arquivo como guardaria as senhas.**'
                : 'Sem credenciais: `access_tokens`, `token_pages` e `admin_configs` ficaram de fora.',
            '',
            '## Conteúdo — '.count($resumo)." tabelas, {$total} linhas", '',
            '| Tabela | Linhas |', '|---|---|',
            ...array_map(fn (array $t): string => "| `{$t['tabela']}` | {$t['linhas']} |", $resumo),
            '',
            'Tabela com 0 linhas não tem arquivo próprio.',
            '',
            '## Como restaurar', '',
            '1. **Schema.** O banco de destino precisa ter as mesmas tabelas: `php artisan migrate`.',
            '2. **Dados.** Rode os `.sql` em ordem numérica, começando pelo `00_limpar.sql`; ou de uma vez só, tudo-ou-nada:', '',
            '   ```bash',
            '   cat *.sql | psql "$DATABASE_URL" --single-transaction -v ON_ERROR_STOP=1',
            '   ```', '',
            '## O que não está aqui', '',
            '- Arquivos do disco (banners das Manifestações, imagens da galeria, páginas do arquivo).',
            '- Backups gerados antes da migração para Laravel têm outro formato (colunas sem prefixo): restauram pelo comando `php artisan lifeos:importar-supabase`.',
            '',
        ];

        return implode("\n", $linhas);
    }

    private function tab(string $nome): string
    {
        return 'public.'.$this->ident($nome);
    }

    private function ident(string $nome): string
    {
        return '"'.str_replace('"', '""', $nome).'"';
    }

    private function literal(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }

    private function dollar(string $texto): string
    {
        $tag = '$lifeos$';
        while (str_contains($texto, $tag)) {
            $tag = '$lifeos'.bin2hex(random_bytes(3)).'$';
        }

        return $tag.$texto.$tag;
    }
}
