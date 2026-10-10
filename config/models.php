<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Bindings de Model cross-módulo
    |--------------------------------------------------------------------------
    |
    | Relações Eloquent que cruzam a fronteira entre módulos são resolvidas por
    | estas chaves (ex.: a relação entity() do trait Entityable), em vez de
    | importar a classe do outro módulo diretamente. Isso preserva a regra
    | "um módulo não importa o interno de outro" no código de produção (src/),
    | mantendo o teste de arquitetura verde. Factories (test helpers) podem
    | referenciar direto se precisarem.
    |
    | Adicione uma chave aqui sempre que um model precisar se relacionar com um
    | model de OUTRO módulo: belongsTo(config('models.x'), ...).
    |
    */

    'entity' => Modules\Tenancy\Models\Entity::class,
];
