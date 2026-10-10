<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider do shared kernel. Fica deliberadamente vazio: o core não faz bind de
 * implementação nenhuma — quem implementa um contrato do core é um módulo de
 * domínio, e o bind mora no provider DESSE módulo (ex.: PaymentsServiceProvider
 * faz o bind de PaymentGateway). Commands, migrations e factories do módulo são
 * auto-descobertos pelo internachi/modular.
 */
class CoreServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
