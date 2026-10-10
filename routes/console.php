<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// O agendador roda por UM cron na VPS: `* * * * * php artisan schedule:run`.
Schedule::command('lifeos:dump-banco')->dailyAt('03:15')->onOneServer()->withoutOverlapping();
Schedule::command('model:prune')->daily();
