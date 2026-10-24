<?php

namespace App\Filament\Resources\Visitors\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VisitorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('browser_id')
                    ->label('ID браузера')
                    ->searchable()
                    ->copyable()
                    ->limit(18),
                TextColumn::make('generations_count')
                    ->label('Генераций')
                    ->counts('generations')
                    ->sortable(),
                TextColumn::make('last_ip_address')->label('Последний IP')->searchable(),
                TextColumn::make('last_user_agent')->label('Браузер / устройство')->limit(45)->toggleable(),
                TextColumn::make('created_at')->label('Впервые')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('last_seen_at')->label('Последняя активность')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
