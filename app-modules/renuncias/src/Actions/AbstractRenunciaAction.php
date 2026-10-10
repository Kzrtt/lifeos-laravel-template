<?php

declare(strict_types=1);

namespace Modules\Renuncias\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;
use Modules\Renuncias\Models\Renuncia;
use Throwable;

abstract class AbstractRenunciaAction extends RpcAction
{
    public const MAX_NOME = 80;

    public const MAX_EMOJI = 16;

    /** Relógio do servidor e o do navegador divergem um pouco. */
    private const FOLGA_FUTURO_SEGUNDOS = 300;

    protected function limpo(mixed $v, int $max): ?string
    {
        $s = $this->texto($v);

        return ($s === '' || mb_strlen($s) > $max) ? null : $s;
    }

    /**
     * Instante válido (ISO, com folga de 5 min no futuro), ou null. Um instante
     * levemente no futuro é encolhido para "agora".
     */
    protected function instante(mixed $v): ?CarbonImmutable
    {
        $s = $this->texto($v);
        if ($s === '') {
            return null;
        }

        try {
            $t = CarbonImmutable::parse($s)->utc();
        } catch (Throwable) {
            return null;
        }

        $agora = CarbonImmutable::now('UTC');
        if ($t->greaterThan($agora->addSeconds(self::FOLGA_FUTURO_SEGUNDOS))) {
            return null;
        }

        return $t->greaterThan($agora) ? $agora : $t;
    }

    protected function nomeDuplicado(string $nome, ?string $exceto = null): bool
    {
        return Renuncia::query()
            ->whereRaw('lower(btrim(ren_nome)) = lower(btrim(?))', [$nome])
            ->when($exceto !== null, fn ($q) => $q->where('ren_id', '!=', $exceto))
            ->exists();
    }

    /**
     * @template T
     *
     * @param  callable():T  $escrita
     * @return T
     */
    protected function protegendoNome(callable $escrita): mixed
    {
        try {
            return $escrita();
        } catch (UniqueConstraintViolationException) {
            throw LifeosException::conflict('nome_duplicado');
        }
    }
}
