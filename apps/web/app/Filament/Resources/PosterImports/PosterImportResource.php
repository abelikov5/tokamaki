<?php

namespace App\Filament\Resources\PosterImports;

use App\Filament\Resources\PosterImports\Pages\ListPosterImports;
use App\Filament\Resources\PosterImports\Tables\PosterImportsTable;
use App\Models\PosterImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'indexNowSubmissions as indexnow_completed_count' => fn (Builder $query): Builder => $query->where('status', 'completed'),
                'indexNowSubmissions as indexnow_failed_count' => fn (Builder $query): Builder => $query->where('status', 'failed'),
            ])
            ->withSum([
                'indexNowSubmissions as indexnow_urls_count' => fn (Builder $query): Builder => $query->where('status', 'completed'),
            ], 'urls_count')
            ->withMax([
                'indexNowSubmissions as indexnow_last_submitted_at' => fn (Builder $query): Builder => $query->where('status', 'completed'),
            ], 'submitted_at');
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
