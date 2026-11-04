<?php

namespace App\Filament\Resources\Posters\Pages;

use App\Filament\Resources\Posters\PosterResource;
use App\Jobs\ImportPostersJob;
use App\Models\PosterImport;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;

class ListPosters extends ListRecords
{
    protected static string $resource = PosterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Импортировать из WB')
                ->icon('heroicon-o-arrow-down-tray')
                ->schema([
                    TextInput::make('user_id')->label('User ID в mockup')->integer()->default(1)->required()->minValue(1),
                    Select::make('selection_mode')
                        ->label('Режим импорта')
                        ->options([
                            'all' => 'Все постеры',
                            'selected' => 'По списку nm_id',
                            'random' => 'Случайные постеры',
                        ])
                        ->default('all')
                        ->required()
                        ->live(),
                    Textarea::make('nm_ids')
                        ->label('nm_id через запятую')
                        ->helperText('Можно разделять запятыми, пробелами или переносами строк.')
                        ->required(fn (Get $get): bool => $get('selection_mode') === 'selected')
                        ->visible(fn (Get $get): bool => $get('selection_mode') === 'selected'),
                    TextInput::make('random_count')
                        ->label('Количество случайных дизайнов')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(500)
                        ->default(100)
                        ->required(fn (Get $get): bool => $get('selection_mode') === 'random')
                        ->visible(fn (Get $get): bool => $get('selection_mode') === 'random'),
                    Toggle::make('only_new')
                        ->label('Только ещё не импортированные')
                        ->helperText('Уже существующие дизайны будут пропущены до случайной выборки.')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    $nmIds = collect(preg_split('/[\s,;]+/', (string) ($data['nm_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
                        ->unique()->values()->all();
                    $posterImport = PosterImport::create([
                        'source_user_id' => (int) $data['user_id'],
                        'selection_mode' => $data['selection_mode'],
                        'requested_nm_ids' => $data['selection_mode'] === 'selected' ? $nmIds : null,
                        'random_count' => $data['selection_mode'] === 'random' ? (int) $data['random_count'] : null,
                        'only_new' => (bool) ($data['only_new'] ?? false),
                        'status' => 'pending',
                    ]);

                    ImportPostersJob::dispatch($posterImport->id);
                    Notification::make()
                        ->title('Импорт поставлен в очередь')
                        ->body('Ход выполнения отображается в разделе «Импорты постеров».')
                        ->success()
                        ->send();
                }),
        ];
    }
}
