<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Quem GRAVA valores de vocabulário nas próprias tabelas implementa isto, para
 * que renomear e contar uso (hoje um `case` de SQL em duas RPCs) respeitem a
 * fronteira dos módulos. O provider do módulo registra a classe com
 * `$this->app->tag(X::class, ConsumidorDeVocabulario::TAG)`; o módulo
 * `vocabularios` chama todos dentro de uma transação.
 */
interface ConsumidorDeVocabulario
{
    public const TAG = 'vocabulario.consumidores';

    /**
     * Domínios que este módulo consome.
     *
     * @return list<string>
     */
    public function dominios(): array;

    /** Quantos registros (não apagados) usam o valor. */
    public function contarUso(string $dominio, string $valor): int;

    /** Troca o valor nos dados; devolve quantos registros foram migrados. */
    public function renomear(string $dominio, string $de, string $para): int;
}
