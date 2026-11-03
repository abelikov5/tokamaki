<?php

namespace App\Filament\Resources\Posters\Tables;

use App\Models\Poster;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PostersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview')
                    ->label('Постер')
                    ->getStateUsing(fn (Poster $record): ?string => $record->primary_image_url)
                    ->height(72),
                TextColumn::make('title')->label('Название')->searchable()->wrap()->limit(55),
                TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'draft' => 'Черновик',
                    'published' => 'Опубликован',
                    'archived' => 'Архив',
                    default => $state,
                })->color(fn (string $state): string => match ($state) {
                    'published' => 'success',
                    'archived' => 'gray',
                    default => 'warning',
                }),
                TextColumn::make('category')->label('Категория')->searchable(),
                TextColumn::make('variants.size_code')->label('Размеры')->badge()->separator(','),
                TextColumn::make('nm_id')->label('nm_id')->copyable()->searchable(),
                TextColumn::make('source_user_id')->label('Источник')->formatStateUsing(fn (int $state): string => 'User '.$state),
                TextColumn::make('imported_at')->label('Импорт')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options([
                    'draft' => 'Черновик',
                    'published' => 'Опубликован',
                    'archived' => 'Архив',
                ]),
                SelectFilter::make('category')->label('Категория')->options(
                    fn (): array => Poster::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category', 'category')->all()
                ),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('imported_at', 'desc');
    }
}
