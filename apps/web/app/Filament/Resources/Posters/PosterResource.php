<?php

namespace App\Filament\Resources\Posters;

use App\Filament\Resources\Posters\Pages\EditPoster;
use App\Filament\Resources\Posters\Pages\ListPosters;
use App\Filament\Resources\Posters\Schemas\PosterForm;
use App\Filament\Resources\Posters\Tables\PostersTable;
use App\Models\Poster;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PosterResource extends Resource
{
    protected static ?string $model = Poster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Постеры';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?string $modelLabel = 'постер';

    protected static ?string $pluralModelLabel = 'Постеры';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PosterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosters::route('/'),
            'edit' => EditPoster::route('/{record}/edit'),
        ];
    }
}
