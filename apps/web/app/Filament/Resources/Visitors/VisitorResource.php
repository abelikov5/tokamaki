<?php

namespace App\Filament\Resources\Visitors;

use App\Filament\Resources\Visitors\Pages\ListVisitors;
use App\Filament\Resources\Visitors\Pages\ViewVisitor;
use App\Filament\Resources\Visitors\RelationManagers\GenerationsRelationManager;
use App\Filament\Resources\Visitors\Schemas\VisitorForm;
use App\Filament\Resources\Visitors\Schemas\VisitorInfolist;
use App\Filament\Resources\Visitors\Tables\VisitorsTable;
use App\Models\Visitor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VisitorResource extends Resource
{
    protected static ?string $model = Visitor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Посетители';

    protected static ?string $modelLabel = 'посетитель';

    protected static ?string $pluralModelLabel = 'посетители';

    protected static ?string $recordTitleAttribute = 'browser_id';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return VisitorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VisitorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VisitorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            GenerationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisitors::route('/'),
            'view' => ViewVisitor::route('/{record}'),
        ];
    }
}
