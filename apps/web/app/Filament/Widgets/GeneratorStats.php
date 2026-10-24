<?php

namespace App\Filament\Widgets;

use App\Models\Generation;
use App\Models\Visitor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GeneratorStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Уникальные посетители', Visitor::query()->count()),
            Stat::make('Всего генераций', Generation::query()->count()),
            Stat::make('Успешно', Generation::query()->where('status', 'completed')->count())
                ->color('success'),
            Stat::make('С ошибкой', Generation::query()->where('status', 'failed')->count())
                ->color('danger'),
        ];
    }
}
