<?php

declare(strict_types=1);

namespace Modules\Vocabularios\Actions;

use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;
use Modules\Vocabularios\Models\Vocabulario;
use Modules\Vocabularios\Support\Dominios;

final class CriarVocabulario extends RpcAction
{
    public const HEX = '/^#[0-9a-fA-F]{6}$/';

    protected function sanitize(array $input): array
    {
        $ordem = $input['ordem'] ?? null;

        return [
            'dominio' => trim((string) ($input['dominio'] ?? '')),
            'valor' => trim((string) ($input['valor'] ?? '')),
            'cor' => trim((string) ($input['cor'] ?? '')),
            'ordem' => is_numeric($ordem) ? (int) $ordem : 999,
        ];
    }

    protected function rules(): array
    {
        return [
            'dominio' => ['required', 'in:'.implode(',', array_keys(Dominios::LISTA))],
            'valor' => ['required', 'string'],
            'cor' => ['nullable', 'regex:'.self::HEX],
        ];
    }

    protected function codes(): array
    {
        return ['dominio' => 'dominio_invalido', 'valor' => 'valor_vazio', 'cor' => 'cor_invalida'];
    }

    protected function handle(array $data): mixed
    {
        $existe = Vocabulario::query()
            ->where('voc_dominio', $data['dominio'])->where('voc_valor', $data['valor'])->exists();
        if ($existe) {
            throw LifeosException::conflict('valor_duplicado');
        }

        return Vocabulario::query()->create([
            'voc_dominio' => $data['dominio'],
            'voc_valor' => $data['valor'],
            'voc_cor' => Dominios::LISTA[$data['dominio']]['cor'] && $data['cor'] !== '' ? $data['cor'] : null,
            'voc_ordem' => $data['ordem'],
            'voc_protegido' => false,
        ]);
    }
}
