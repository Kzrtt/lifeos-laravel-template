<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Timestamps com microssegundos: datas importadas do Supabase (timestamptz com
        // µs) entram sem arredondar, e os checksums da importação batem exatamente.
        Schema::defaultTimePrecision(6);
    }

    public function boot(): void
    {
        // Rotas com senha: hoje não há limite algum (AUTH.md registra brute force
        // possível nos tokens curtos). `lifeos` cobre o uso normal das telas;
        // `lifeos-gate` é o mais apertado, para check_page_access e o webhook.
        RateLimiter::for('lifeos', fn (Request $r): Limit => Limit::perMinute(240)->by($r->ip()));
        RateLimiter::for('lifeos-gate', fn (Request $r): Limit => Limit::perMinute(30)->by($r->ip()));
    }
}
