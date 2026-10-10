<?php

declare(strict_types=1);

namespace Modules\Renuncias\Actions;

use Modules\Core\Exceptions\LifeosException;
use Modules\Renuncias\Models\Renuncia;

final class CriarRenuncia extends AbstractRenunciaAction
{
    protected function sanitize(array $input): array
    {
        $r = $input['renuncia'] ?? null;

        if (is_array($r)) {
            $r = [
                'nome' => $this->limpo($r['nome'] ?? null, self::MAX_NOME) ?? '',
                'emoji' => $this->limpo($r['emoji'] ?? null, self::MAX_EMOJI) ?? '',
                'desde' => $this->instante($r['desde'] ?? null)?->toDateTimeString() ?? '',
            ];
        }

        return ['renuncia' => is_array($r) ? $r : null];
    }

    protected function rules(): array
    {
        return [
            'renuncia' => ['required', 'array'],
            'renuncia.nome' => ['required', 'string'],
            'renuncia.emoji' => ['required', 'string'],
            'renuncia.desde' => ['required', 'string'],
        ];
    }

    protected function codes(): array
    {
        return ['renuncia' => 'missing_renuncia', 'renuncia.nome' => 'invalid_nome', 'renuncia.emoji' => 'invalid_emoji', 'renuncia.desde' => 'invalid_desde'];
    }

    protected function handle(array $data): mixed
    {
        $r = $data['renuncia'];

        if ($this->nomeDuplicado($r['nome'])) {
            throw LifeosException::conflict('nome_duplicado');
        }

        return $this->protegendoNome(fn (): Renuncia => Renuncia::query()->create([
            'ren_nome' => $r['nome'], 'ren_emoji' => $r['emoji'], 'ren_desde' => $r['desde'],
        ])->load('tentativas'));
    }
}
