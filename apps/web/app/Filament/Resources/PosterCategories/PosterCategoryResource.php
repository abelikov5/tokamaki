<?php

namespace App\Filament\Resources\PosterCategories;

use App\Filament\Resources\PosterCategories\Pages\CreatePosterCategory;
use App\Filament\Resources\PosterCategories\Pages\EditPosterCategory;
use App\Filament\Resources\PosterCategories\Pages\ListPosterCategories;
use App\Filament\Resources\PosterCategories\Schemas\PosterCategoryForm;
use App\Filament\Resources\PosterCategories\Tables\PosterCategoriesTable;
use App\Models\PosterCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PosterCategoryResource extends Resource
{
    protected static ?string $model = PosterCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Категории';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?string $modelLabel = 'категория';

    protected static ?string $pluralModelLabel = 'Категории';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PosterCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PosterCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosterCategories::route('/'),
            'create' => CreatePosterCategory::route('/create'),
            'edit' => EditPosterCategory::route('/{record}/edit'),
        ];
    }
}
