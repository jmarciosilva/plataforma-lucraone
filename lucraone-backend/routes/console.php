<?php

use App\Modules\Automation\Infrastructure\Console\PruneAutomationLogsCommand;
use App\Modules\Reporting\Infrastructure\Console\SendSalesSummaryCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Tarefas agendadas.
|
| Dependem de um cron chamando `php artisan schedule:run` a cada minuto. Em
| desenvolvimento dá para simular com `php artisan schedule:work`.
*/

// Resumo semanal de vendas, segunda de manhã, para quem pode ver relatórios
Schedule::command(SendSalesSummaryCommand::class, ['--dias' => 7])
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping()
    ->onOneServer();

// Poda do histórico de automações — sem isso a tabela cresce sem teto
Schedule::command(PruneAutomationLogsCommand::class)
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();
