<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Acesso\Services\TokenService;
use Modules\Core\Tenancy\TenantContext;
use Modules\Tenancy\Models\Entity;
use Modules\Vocabularios\Services\VocabularioService;

/**
 * Cria uma instância do LifeOS: a entidade e a senha mestre. A instalação nunca
 * nasce com senha padrão — o comando pede a senha (ou recebe por --senha em
 * automação) e ela é gravada só como hash.
 */
final class LifeosNovaInstancia extends Command
{
    protected $signature = 'lifeos:nova-instancia
        {slug : identificador da instância (LIFEOS_ENTITY_SLUG)}
        {nome : nome exibido no masthead}
        {--sub= : subtítulo do masthead}
        {--senha= : senha mestre (se omitida, é pedida sem eco)}';

    protected $description = 'Cria uma instância do LifeOS (entidade + senha mestre).';

    public function handle(TokenService $tokens, VocabularioService $vocabularios): int
    {
        $slug = (string) $this->argument('slug');

        if (Entity::query()->where('ent_slug', $slug)->exists()) {
            $this->error("Já existe uma instância '{$slug}'.");

            return self::FAILURE;
        }

        $senha = (string) ($this->option('senha') ?: $this->secret('Senha mestre'));
        if ($senha === '') {
            $this->error('A senha mestre não pode ser vazia.');

            return self::FAILURE;
        }
        if (mb_strlen($senha) < TokenService::MIN_LEN) {
            $this->warn('Senha curta (< '.TokenService::MIN_LEN.' caracteres): aceita, mas considere uma maior.');
        }

        DB::transaction(function () use ($slug, $senha, $tokens, $vocabularios): void {
            $entity = Entity::query()->create([
                'ent_slug' => $slug,
                'ent_name' => (string) $this->argument('nome'),
                'ent_sub' => $this->option('sub') ?: null,
            ]);

            TenantContext::runAs($entity->ent_id, fn () => $tokens->create($senha, 'mestre', true));
            $vocabularios->semear($entity->ent_id);
        });

        $this->info("Instância '{$slug}' criada.");

        return self::SUCCESS;
    }
}
