<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Заказ')
                    ->schema([
                        TextEntry::make('id')->label('Номер заказа'),
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('amount')
                            ->label('Сумма')
                            ->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' ₽'),
                        TextEntry::make('designer_processing')
                            ->label('Обработка дизайнером')
                            ->formatStateUsing(fn (bool $state): string => $state
                                ? 'Да, +'.number_format((int) config('orders.designer_price'), 0, ',', "\u{00A0}")."\u{00A0}₽"
                                : 'Нет'),
                        TextEntry::make('created_at')->label('Создан')->dateTime('d.m.Y H:i'),
                        TextEntry::make('generation_id')->label('ID генерации'),
                    ])
                    ->columns(3),
                Section::make('Получатель и доставка')
                    ->schema([
                        TextEntry::make('email')->label('Email')->copyable(),
                        TextEntry::make('city')->label('Город'),
                        TextEntry::make('street')->label('Улица'),
                    ])
                    ->columns(2),
                Section::make('Изображения')
                    ->schema([
                        ImageEntry::make('original_image_path')
                            ->label('Оригинал')
                            ->getStateUsing(fn (Order $record): string => $record->adminImageUrl('original')),
                        ImageEntry::make('generated_image_path')
                            ->label('Результат генерации')
                            ->getStateUsing(fn (Order $record): string => $record->adminImageUrl('generated')),
                    ])
                    ->columns(2),
                Section::make('Техническая информация')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('original_name')->label('Имя исходного файла'),
                        TextEntry::make('ip_address')->label('IP-адрес'),
                        TextEntry::make('user_agent')->label('Браузер')->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
