<?php

declare(strict_types=1);

namespace Modules\Memoria\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Memoria\Models\Memoria;

abstract class AbstractMemoriaAction extends RpcAction
{
    public const MAX_TITULO = 120;

    public const MAX_DESCRICAO = 400;

    public const MAX_TEXTO = 8000;

    public const MAX_ORIGEM = 60;

    public function __construct(protected readonly VocabularioReadModel $vocabulario) {}

    /** Texto aparado dentro do limite, ou null (vazio ou grande demais). */
    protected function limpo(mixed $v, int $max): ?string
    {
        $s = $this->texto($v);

        return ($s === '' || mb_strlen($s) > $max) ? null : $s;
    }

    /** Título já em uso (sem diferenciar caixa e espaço) por outra memória? */
    protected function tituloDuplicado(string $titulo, ?string $exceto = null): bool
    {
        return Memoria::query()
            ->whereRaw('lower(btrim(mem_titulo)) = lower(btrim(?))', [$titulo])
            ->when($exceto !== null, fn ($q) => $q->where('mem_id', '!=', $exceto))
            ->exists();
    }

    /**
     * Roda a escrita traduzindo a violação do índice único (corrida entre duas
     * requisições) para o mesmo `titulo_duplicado` da checagem prévia.
     *
     * @template T
     *
     * @param  callable():T  $escrita
     * @return T
     */
    protected function protegendoTitulo(callable $escrita): mixed
    {
        try {
            return $escrita();
        } catch (UniqueConstraintViolationException) {
            throw LifeosException::conflict('titulo_duplicado');
        }
    }
}
