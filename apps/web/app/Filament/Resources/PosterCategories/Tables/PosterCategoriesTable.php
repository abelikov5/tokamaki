<?php

namespace App\Filament\Resources\PosterCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PosterCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Название')->searchable()->sortable(),
                TextColumn::make('slug')->label('URL')->searchable()->copyable(),
                IconColumn::make('is_active')->label('В каталоге')->boolean()->sortable(),
                TextColumn::make('posters_count')->label('Постеров')->counts('posters')->sortable(),
                TextColumn::make('sort_order')->label('Порядок')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('Удалить'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Удалить выбранные'),
                ])->label('Действия'),
            ]);
    }
}
