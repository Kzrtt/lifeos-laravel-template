<?php

declare(strict_types=1);

namespace Modules\Backup\Actions;

use Modules\Backup\Services\BackupService;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Tenancy\TenantContext;

final class GerarBackup extends RpcAction
{
    public function __construct(private readonly BackupService $backup) {}

    protected function sanitize(array $input): array
    {
        return ['incluir_credenciais' => ($input['incluir_credenciais'] ?? true) !== false];
    }

    protected function rules(): array
    {
        return [];
    }

    /** @return array{gerado_em: string, arquivos: list<array{nome: string, conteudo: string}>, tabelas: list<array{tabela: string, linhas: int}>} */
    protected function handle(array $data): mixed
    {
        $entityId = TenantContext::id() ?? throw new LifeosException('unauthorized', 401);

        return $this->backup->gerar($entityId, $data['incluir_credenciais']);
    }
}
