<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Modules Namespace
    |--------------------------------------------------------------------------
    |
    | Namespace PHP em que os módulos vivem. O template usa `Modules\` (default
    | do pacote) para funcionar de imediato; ao iniciar um sistema real, a skill
    | /prontuario renomeia para o nome do SEU projeto em PascalCase (ex.:
    | `MeuSistema\`) — imports mais legíveis e extração futura de módulo para
    | pacote próprio sem fricção. Se renomear, ajuste também o vendor abaixo,
    | os composer.json de cada módulo e tests/Arch/ArchTest.php.
    |
    */

    'modules_namespace' => 'Modules',

    /*
    |--------------------------------------------------------------------------
    | Composer "Vendor" Name
    |--------------------------------------------------------------------------
    |
    | Prefixo dos pacotes Composer dos módulos (modules/core, modules/orders...).
    | Deve ser o kebab-case do namespace acima.
    |
    */

    'modules_vendor' => 'modules',

    /*
    |--------------------------------------------------------------------------
    | Modules Directory
    |--------------------------------------------------------------------------
    |
    | Onde os módulos moram. Manter `app-modules/` é altamente recomendado:
    | fica ao lado do código da app numa listagem alfabética.
    |
    */

    'modules_directory' => 'app-modules',

    /*
    |--------------------------------------------------------------------------
    | Base Test Case
    |--------------------------------------------------------------------------
    */

    'tests_base' => 'Tests\TestCase',

    /*
    |--------------------------------------------------------------------------
    | Custom Stubs
    |--------------------------------------------------------------------------
    |
    | Stubs próprios para `php artisan make:module`, se quiser que módulos novos
    | já nasçam com a sua cara. Ver README do internachi/modular.
    |
    */

    'stubs' => null,

    /*
    |--------------------------------------------------------------------------
    | Custom override of event discovery
    |--------------------------------------------------------------------------
    */

    'should_discover_events' => null,
];
