<?php

namespace App\Filament\Resources\Posters\Tables;

use App\Jobs\GeneratePosterContentJob;
use App\Models\Poster;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class PostersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview')
                    ->label('Картина')
                    ->getStateUsing(fn (Poster $record): ?string => $record->primary_image_url)
                    ->height(72),
                TextColumn::make('title')->label('Название')->searchable()->wrap()->limit(55),
                ToggleColumn::make('is_admin_recommended')
                    ->label('Рекомендация')
                    ->sortable(),
                TextColumn::make('view_count')
                    ->label('Просмотры')
                    ->numeric()
                    ->sortable(),
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
                TextColumn::make('ai_generation_status')->label('ИИ')->badge()->formatStateUsing(fn (?string $state): string => match ($state) {
                    'pending' => 'В очереди',
                    'processing' => 'Генерируется',
                    'generated' => 'Готово',
                    'failed' => 'Ошибка',
                    default => 'Нет',
                })->color(fn (?string $state): string => match ($state) {
                    'generated' => 'success',
                    'failed' => 'danger',
                    'pending', 'processing' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('categories.name')->label('Группы')->badge()->separator(',')->wrap(),
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
                TernaryFilter::make('is_admin_recommended')
                    ->label('Рекомендация админа')
                    ->trueLabel('Только рекомендованные')
                    ->falseLabel('Только обычные'),
                SelectFilter::make('categories')
                    ->label('Группа')
                    ->relationship('categories', 'name', fn ($query) => $query->orderBy('type')->orderBy('sort_order')->orderBy('name'))
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->label('Удалить'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('generateAiContent')
                        ->label('Сгенерировать через ИИ')
                        ->icon('heroicon-o-sparkles')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Сгенерировать контент для выбранных картин?')
                        ->modalDescription('Название, описания, SEO, группы и поисковые параметры будут обновлены в фоне.')
                        ->action(function (Collection $records): void {
                            $records->each(function (Poster $poster): void {
                                $poster->update([
                                    'ai_generation_status' => 'pending',
                                    'ai_generation_error' => null,
                                ]);
                                GeneratePosterContentJob::dispatch($poster->id);
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->label('Удалить выбранные')
                        ->modalHeading('Удалить выбранные картины?')
                        ->modalDescription('Картины исчезнут из каталога вместе с вариантами и изображениями. Это действие нельзя отменить.')
                        ->modalSubmitActionLabel('Удалить'),
                ])->label('Действия'),
            ])
            ->defaultSort('imported_at', 'desc');
    }
}
