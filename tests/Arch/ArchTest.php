<?php

declare(strict_types=1);

// Torna a fronteira modular EXECUTÁVEL: o CI quebra se alguém furar as
// invariantes. Ver docs/arquitetura/FRONTEIRAS.md.
//
// Ao criar um módulo novo (skill /novo-modulo), adicione o namespace dele à
// constante DOMAIN_MODULES — ele ganha todas as regras de graça.

/** Todos os módulos de domínio (namespace raiz Modules\<Modulo>). */
const DOMAIN_MODULES = [
    'Modules\Tenancy',
    'Modules\Acesso',
    'Modules\Citacoes',
    'Modules\Painel',
    'Modules\Vocabularios',
    'Modules\Projetos',
    'Modules\Notas',
    'Modules\Visoes',
    'Modules\Agenda',
    'Modules\Manifestacoes',
    'Modules\Memoria',
    'Modules\Renuncias',
    'Modules\Financas',
    'Modules\Backup',
    'Modules\Mcp',
];

arch('todo o código dos módulos declara strict types')
    ->expect('Modules')
    ->toUseStrictTypes();

arch('o core não depende de nenhum módulo de domínio')
    ->expect('Modules\Core')
    ->not->toUse(DOMAIN_MODULES);

// Cada módulo de domínio só pode importar o core (e a si mesmo) — nunca o
// interno de outro módulo. Relações cross-módulo passam por config/models.php;
// capacidades, por contratos do core; reações, por eventos do core.
foreach (DOMAIN_MODULES as $module) {
    $others = array_values(array_filter(DOMAIN_MODULES, fn (string $m): bool => $m !== $module));

    arch(class_basename($module).' não importa o interno de outro módulo de domínio')
        ->expect($module)
        ->not->toUse($others);
}
