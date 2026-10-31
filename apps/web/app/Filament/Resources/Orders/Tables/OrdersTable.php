<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                ImageColumn::make('generated_image_path')
                    ->label('Макет')
                    ->getStateUsing(fn (Order $record): string => $record->adminImageUrl('generated'))
                    ->square(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'new' => 'Новый',
                        'processing' => 'В работе',
                        'completed' => 'Выполнен',
                        'cancelled' => 'Отменён',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('city')->label('Город')->searchable(),
                IconColumn::make('designer_processing')->label('Дизайнер')->boolean(),
                TextColumn::make('amount')
                    ->label('Сумма')
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' ₽')
                    ->sortable(),
                TextColumn::make('created_at')->label('Создан')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'new' => 'Новый',
                        'processing' => 'В работе',
                        'completed' => 'Выполнен',
                        'cancelled' => 'Отменён',
                    ]),
                TernaryFilter::make('designer_processing')->label('Обработка дизайнером'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
