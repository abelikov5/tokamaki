<?php

namespace App\Filament\Resources\Generations\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GenerationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Проект')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('original_name')->label('Исходный файл'),
                        TextEntry::make('visitor.browser_id')->label('ID посетителя')->placeholder('Старые данные'),
                        TextEntry::make('ip_address')->label('IP при создании')->placeholder('—'),
                        TextEntry::make('edits_count')->label('Количество правок'),
                        TextEntry::make('created_at')->label('Создано')->dateTime('d.m.Y H:i:s'),
                        TextEntry::make('completed_at')->label('Завершено')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                        TextEntry::make('last_activity_at')->label('Последняя активность')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                        TextEntry::make('user_agent')->label('Браузер / устройство')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('error')->label('Ошибка')->columnSpanFull()->placeholder('Нет'),
                    ]),
                Section::make('Настройки генератора')
                    ->schema([
                        KeyValueEntry::make('settings')->label('Параметры'),
                    ]),
                Section::make('Результат')
                    ->schema([
                        KeyValueEntry::make('diagnostics')->label('Диагностика')->placeholder('Нет данных'),
                    ]),
            ]);
    }
}
