<?php

namespace App\Filament\Resources\PosterImports;

use App\Filament\Resources\PosterImports\Pages\ListPosterImports;
use App\Filament\Resources\PosterImports\Tables\PosterImportsTable;
use App\Models\PosterImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PosterImportResource extends Resource
{
    protected static ?string $model = PosterImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Импорты картин';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?string $modelLabel = 'импорт картин';

    protected static ?string $pluralModelLabel = 'Импорты картин';

    public static function table(Table $table): Table
    {
        return PosterImportsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PosterImport::query()->whereIn('status', ['pending', 'running'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosterImports::route('/'),
        ];
    }
}
