<?php

declare(strict_types=1);

namespace Modules\Renuncias\Actions;

use Carbon\CarbonImmutable;
use Modules\Core\Exceptions\LifeosException;
use Modules\Renuncias\Models\Renuncia;

final class AtualizarRenuncia extends AbstractRenunciaAction
{
    protected function sanitize(array $input): array
    {
        $patch = $input['patch'] ?? null;

        if (is_array($patch)) {
            if (array_key_exists('nome', $patch)) {
                $patch['nome'] = $this->limpo($patch['nome'], self::MAX_NOME) ?? '';
            }
            if (array_key_exists('emoji', $patch)) {
                $patch['emoji'] = $this->limpo($patch['emoji'], self::MAX_EMOJI) ?? '';
            }
            if (array_key_exists('desde', $patch)) {
                $patch['desde'] = $this->instante($patch['desde'])?->toDateTimeString() ?? '';
            }
        }

        return ['id' => $this->texto($input['id'] ?? null), 'patch' => is_array($patch) ? $patch : null];
    }

    protected function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'patch' => ['required', 'array', 'min:1'],
            'patch.nome' => ['sometimes', 'required', 'string'],
            'patch.emoji' => ['sometimes', 'required', 'string'],
            'patch.desde' => ['sometimes', 'required', 'string'],
            'patch.arquivada' => ['sometimes', 'boolean:strict'],
        ];
    }

    protected function codes(): array
    {
        return [
            'id' => 'missing_id', 'patch' => 'empty_patch', 'patch.nome' => 'invalid_nome',
            'patch.emoji' => 'invalid_emoji', 'patch.desde' => 'invalid_desde', 'patch.arquivada' => 'invalid_arquivada',
        ];
    }

    protected function handle(array $data): mixed
    {
        $mapa = ['nome' => 'ren_nome', 'emoji' => 'ren_emoji', 'desde' => 'ren_desde', 'arquivada' => 'ren_arquivada'];
        $update = [];
        foreach ($mapa as $campo => $coluna) {
            if (array_key_exists($campo, $data['patch'])) {
                $update[$coluna] = $data['patch'][$campo];
            }
        }
        if ($update === []) {
            throw new LifeosException('empty_patch');
        }

        $renuncia = $this->achar(Renuncia::class, $data['id']);

        // Desarquivar sem data nova recomeça a contagem de agora.
        if (($update['ren_arquivada'] ?? null) === false && ! array_key_exists('ren_desde', $update) && $renuncia->ren_arquivada) {
            $update['ren_desde'] = CarbonImmutable::now('UTC')->toDateTimeString();
        }

        if (isset($update['ren_nome']) && $this->nomeDuplicado($update['ren_nome'], $renuncia->ren_id)) {
            throw LifeosException::conflict('nome_duplicado');
        }

        $this->protegendoNome(fn () => $renuncia->update($update));

        return $renuncia->load('tentativas');
    }
}
