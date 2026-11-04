<?php

namespace App\Filament\Resources\CustomPosterRequests;

use App\Filament\Resources\CustomPosterRequests\Pages\EditCustomPosterRequest;
use App\Filament\Resources\CustomPosterRequests\Pages\ListCustomPosterRequests;
use App\Models\CustomPosterRequest;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CustomPosterRequestResource extends Resource
{
    protected static ?string $model = CustomPosterRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?string $navigationLabel = 'Заказы картин';

    protected static ?string $modelLabel = 'заказ картины';

    protected static ?string $pluralModelLabel = 'Заказы картин';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Заявка')->schema([
                Select::make('status')->label('Статус')->required()->options([
                    'new' => 'Новая',
                    'processing' => 'В работе',
                    'approved' => 'Согласована',
                    'completed' => 'Выполнена',
                    'cancelled' => 'Отменена',
                ]),
                TextInput::make('size_code')->label('Размер')->disabled(),
                TextInput::make('poster.title')->label('Готовая картина')->disabled(),
                TextInput::make('email')->label('Email')->disabled(),
                TextInput::make('city')->label('Город')->disabled(),
                TextInput::make('street')->label('Улица')->disabled(),
                TextInput::make('amount')->label('Сумма, ₽')->disabled(),
                Textarea::make('comment')->label('Комментарий')->disabled()->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('photo')->label('Макет')->getStateUsing(fn (CustomPosterRequest $record): ?string => $record->admin_photo_url)->height(72),
            TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                'new' => 'Новая', 'processing' => 'В работе', 'approved' => 'Согласована',
                'completed' => 'Выполнена', 'cancelled' => 'Отменена', default => $state,
            }),
            TextColumn::make('poster.title')->label('Картина')->limit(32)->placeholder('По своему фото')->searchable(),
            TextColumn::make('email')->label('Email')->copyable()->searchable(),
            TextColumn::make('city')->label('Город')->searchable(),
            TextColumn::make('size_code')->label('Размер')->formatStateUsing(fn (string $state): string => str_replace('x', '×', $state)),
            TextColumn::make('amount')->label('Сумма')->formatStateUsing(fn (?int $state): string => $state ? number_format($state, 0, ',', ' ').' ₽' : '—'),
            TextColumn::make('created_at')->label('Создана')->dateTime('d.m.Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status')->label('Статус')->options([
                'new' => 'Новая', 'processing' => 'В работе', 'approved' => 'Согласована',
                'completed' => 'Выполнена', 'cancelled' => 'Отменена',
            ]),
        ])->recordUrl(fn (CustomPosterRequest $record): string => static::getUrl('edit', ['record' => $record]))->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomPosterRequests::route('/'),
            'edit' => EditCustomPosterRequest::route('/{record}/edit'),
        ];
    }
}
