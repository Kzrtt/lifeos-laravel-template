<?php

declare(strict_types=1);

namespace Modules\Vocabularios\Services;

use Modules\Core\Contracts\ConsumidorDeVocabulario;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Tenancy\TenantContext;
use Modules\Vocabularios\Models\Vocabulario;
use Modules\Vocabularios\Support\Dominios;
use Throwable;

/**
 * Leitura (read model), seed e a ponte para os consumidores de cada módulo.
 */
final class VocabularioService implements VocabularioReadModel
{
    public function valores(string $dominio): array
    {
        try {
            $valores = Vocabulario::query()
                ->where('voc_dominio', $dominio)
                ->orderBy('voc_ordem')->orderBy('voc_valor')
                ->pluck('voc_valor')->all();
        } catch (Throwable) {
            $valores = [];
        }

        return $valores !== [] ? array_values($valores) : Dominios::valoresPadrao($dominio);
    }

    /** Semeia os valores padrão na instância (idempotente). */
    public function semear(string $entityId): void
    {
        TenantContext::runAs($entityId, function (): void {
            foreach (Dominios::PADRAO as $dominio => $valores) {
                foreach ($valores as $i => [$valor, $cor, $protegido]) {
                    Vocabulario::query()->firstOrCreate(
                        ['voc_dominio' => $dominio, 'voc_valor' => $valor],
                        ['voc_cor' => $cor, 'voc_ordem' => ($i + 1) * 10, 'voc_protegido' => $protegido],
                    );
                }
            }
        });
    }

    /**
     * Os consumidores que gravam o domínio (pode haver mais de um módulo: os
     * meios de pagamento vivem em movimentações e em recorrências).
     *
     * @return list<ConsumidorDeVocabulario>
     */
    public function consumidores(string $dominio): array
    {
        $achados = [];
        foreach (app()->tagged(ConsumidorDeVocabulario::TAG) as $consumidor) {
            if (in_array($dominio, $consumidor->dominios(), true)) {
                $achados[] = $consumidor;
            }
        }

        return $achados;
    }

    /** Quantos registros usam o valor, somando os módulos; null se ninguém consome o domínio. */
    public function uso(string $dominio, string $valor): ?int
    {
        $consumidores = $this->consumidores($dominio);

        return $consumidores === []
            ? null
            : array_sum(array_map(fn (ConsumidorDeVocabulario $c): int => $c->contarUso($dominio, $valor), $consumidores));
    }

    /** Troca o valor nos dados de todos os módulos; devolve quantos registros migraram. */
    public function renomearNosDados(string $dominio, string $de, string $para): int
    {
        return array_sum(array_map(
            fn (ConsumidorDeVocabulario $c): int => $c->renomear($dominio, $de, $para),
            $this->consumidores($dominio),
        ));
    }
}
