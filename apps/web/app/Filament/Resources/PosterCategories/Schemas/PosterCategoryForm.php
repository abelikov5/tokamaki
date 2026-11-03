<?php

namespace App\Filament\Resources\PosterCategories\Schemas;

use App\Models\PosterCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PosterCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Группа')->schema([
                TextInput::make('name')->label('Название')->required()->maxLength(255),
                Select::make('type')->label('Тип')->options(PosterCategory::typeOptions())->required()->default('theme'),
                TextInput::make('slug')
                    ->label('URL')
                    ->helperText('Можно оставить пустым — URL сформируется из названия.')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Toggle::make('is_active')->label('Показывать в каталоге')->default(true),
                TextInput::make('sort_order')->label('Порядок сортировки')->integer()->minValue(0)->default(0),
            ])->columns(2),
            Section::make('SEO')->schema([
                TextInput::make('seo_title')->label('SEO title')->maxLength(255)->columnSpanFull(),
                Textarea::make('seo_description')->label('Meta description')->rows(4)->maxLength(500)->columnSpanFull(),
            ]),
        ]);
    }
}
