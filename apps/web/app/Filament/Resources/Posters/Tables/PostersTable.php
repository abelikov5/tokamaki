<?php

namespace App\Filament\Resources\Posters\Tables;

use App\Models\Poster;
use App\Models\PosterCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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
                TextColumn::make('posterCategory.name')->label('Категория')->searchable(),
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
                SelectFilter::make('category_id')->label('Категория')->options(
                    fn (): array => PosterCategory::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all()
                ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->label('Удалить'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Удалить выбранные')
                        ->modalHeading('Удалить выбранные постеры?')
                        ->modalDescription('Постеры исчезнут из каталога вместе с вариантами и изображениями. Это действие нельзя отменить.')
                        ->modalSubmitActionLabel('Удалить'),
                ])->label('Действия'),
            ])
            ->defaultSort('imported_at', 'desc');
    }
}
