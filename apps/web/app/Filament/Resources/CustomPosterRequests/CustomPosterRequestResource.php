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

    protected static ?string $navigationLabel = 'Постеры по фото';

    protected static ?string $modelLabel = 'заявка на постер';

    protected static ?string $pluralModelLabel = 'Постеры по фото';

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
                TextInput::make('name')->label('Имя')->disabled(),
                TextInput::make('phone')->label('Телефон')->disabled(),
                TextInput::make('email')->label('Email')->disabled(),
                Textarea::make('comment')->label('Комментарий')->disabled()->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('photo')->label('Фото')->getStateUsing(fn (CustomPosterRequest $record): string => $record->admin_photo_url)->height(72),
            TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                'new' => 'Новая', 'processing' => 'В работе', 'approved' => 'Согласована',
                'completed' => 'Выполнена', 'cancelled' => 'Отменена', default => $state,
            }),
            TextColumn::make('name')->label('Имя')->searchable(),
            TextColumn::make('phone')->label('Телефон')->copyable()->searchable(),
            TextColumn::make('size_code')->label('Размер')->formatStateUsing(fn (string $state): string => str_replace('x', '×', $state)),
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
