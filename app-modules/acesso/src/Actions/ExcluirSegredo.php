<?php

declare(strict_types=1);

namespace Modules\Acesso\Actions;

use Modules\Acesso\Services\SegredoService;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;

final class ExcluirSegredo extends RpcAction
{
    public function __construct(private readonly SegredoService $segredos) {}

    protected function sanitize(array $input): array
    {
        return ['key' => trim((string) ($input['key'] ?? ''))];
    }

    protected function rules(): array
    {
        return ['key' => ['required', 'in:'.implode(',', SegredoService::CHAVES_VALIDAS)]];
    }

    protected function codes(): array
    {
        return ['key' => 'invalid_key'];
    }

    protected function handle(array $data): mixed
    {
        $linha = $this->segredos->get($data['key']);
        if ($linha === null) {
            throw LifeosException::notFound();
        }

        $linha->delete();

        return $data['key'];
    }
}
