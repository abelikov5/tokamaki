<?php

namespace App\Filament\Resources\Visitors\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VisitorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Посетитель')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('browser_id')->label('ID браузера')->copyable(),
                        TextEntry::make('generations_count')->label('Количество генераций')->state(fn ($record): int => $record->generations()->count()),
                        TextEntry::make('first_ip_address')->label('Первый IP')->placeholder('—'),
                        TextEntry::make('last_ip_address')->label('Последний IP')->placeholder('—'),
                        TextEntry::make('created_at')->label('Первое посещение')->dateTime('d.m.Y H:i:s'),
                        TextEntry::make('last_seen_at')->label('Последняя активность')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                        TextEntry::make('first_user_agent')->label('Первый браузер / устройство')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('last_user_agent')->label('Последний браузер / устройство')->columnSpanFull()->placeholder('—'),
                    ]),
            ]);
    }
}
