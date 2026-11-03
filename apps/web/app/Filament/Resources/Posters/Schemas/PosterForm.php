<?php

namespace App\Filament\Resources\Posters\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PosterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Публикация')->schema([
                Select::make('status')->label('Статус')->required()->options([
                    'draft' => 'Черновик',
                    'published' => 'Опубликован',
                    'archived' => 'Архив',
                ]),
                TextInput::make('slug')->label('URL')->required()->unique(ignoreRecord: true),
                TextInput::make('nm_id')->label('Основной nm_id')->disabled(),
            ])->columns(3),
            Section::make('Контент')->schema([
                TextInput::make('title')->label('Название')->required()->maxLength(255)->columnSpanFull(),
                Textarea::make('short_description')->label('Короткое описание')->rows(3)->columnSpanFull(),
                Textarea::make('description')->label('Описание товара')->rows(8)->columnSpanFull(),
            ]),
            Section::make('Каталог и поиск')->schema([
                TextInput::make('category')->label('Категория'),
                TextInput::make('style')->label('Стиль'),
                TextInput::make('room')->label('Комната'),
                TextInput::make('primary_color')->label('Основной цвет'),
                Select::make('orientation')->label('Ориентация')->options([
                    'vertical' => 'Вертикальная',
                    'horizontal' => 'Горизонтальная',
                    'square' => 'Квадратная',
                ]),
                TagsInput::make('search_keywords')->label('Поисковые слова')->columnSpanFull(),
            ])->columns(2),
            Section::make('SEO')->schema([
                TextInput::make('seo_title')->label('SEO title')->maxLength(60)->columnSpanFull(),
                Textarea::make('seo_description')->label('Meta description')->rows(3)->maxLength(320)->columnSpanFull(),
            ]),
            Section::make('Размеры и WB')->schema([
                Repeater::make('variants')->relationship()->schema([
                    Select::make('size_code')->label('Размер')->required()->options(['30x40' => '30×40', '40x50' => '40×50']),
                    TextInput::make('nm_id')->label('nm_id')->required(),
                    TextInput::make('price')->label('Цена, ₽')->numeric()->minValue(0),
                    Toggle::make('is_active')->label('Активен')->default(true),
                ])->columns(4)->addable(false)->deletable(false)->reorderable(false)->columnSpanFull(),
            ]),
        ]);
    }
}
