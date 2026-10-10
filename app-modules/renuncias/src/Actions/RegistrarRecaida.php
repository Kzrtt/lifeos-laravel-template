<?php

declare(strict_types=1);

namespace Modules\Renuncias\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Exceptions\LifeosException;
use Modules\Renuncias\Models\Renuncia;

/**
 * A recaída fecha a tentativa em curso (de `desde` até `quando`) e recomeça a
 * contagem em `quando`. Transação com `lockForUpdate()`: o mesmo `for update`
 * da RPC de hoje, para duas recaídas simultâneas não se atropelarem.
 */
final class RegistrarRecaida extends AbstractRenunciaAction
{
    protected function sanitize(array $input): array
    {
        $bruto = $input['quando'] ?? null;
        $vazio = $bruto === null || $bruto === '';

        return [
            'id' => $this->texto($input['id'] ?? null),
            'quando' => $vazio ? CarbonImmutable::now('UTC')->toDateTimeString() : ($this->instante($bruto)?->toDateTimeString() ?? ''),
        ];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'string'], 'quando' => ['required', 'string']];
    }

    protected function codes(): array
    {
        return ['id' => 'missing_id', 'quando' => 'invalid_quando'];
    }

    protected function handle(array $data): mixed
    {
        if (! Str::isUuid($data['id'])) {
            throw LifeosException::notFound();
        }

        return DB::transaction(function () use ($data): Renuncia {
            $renuncia = Renuncia::query()->lockForUpdate()->find($data['id']) ?? throw LifeosException::notFound();
            $quando = CarbonImmutable::parse($data['quando'], 'UTC');

            if ($quando->lessThan($renuncia->ren_desde)) {
                throw new LifeosException('quando_antes_do_inicio');
            }

            $renuncia->tentativas()->create(['rtn_inicio' => $renuncia->ren_desde, 'rtn_fim' => $quando]);
            $renuncia->update(['ren_desde' => $quando]);

            return $renuncia->load('tentativas');
        });
    }
}
