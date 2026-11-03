<?php

namespace App\Filament\Resources\Posters\Pages;

use App\Filament\Resources\Posters\PosterResource;
use App\Jobs\ImportPostersJob;
use App\Models\PosterImport;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

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
                    Textarea::make('nm_ids')->label('nm_id через запятую')->helperText('Оставьте пустым, чтобы импортировать все постеры пользователя.'),
                ])
                ->action(function (array $data): void {
                    $nmIds = collect(preg_split('/[\s,;]+/', (string) ($data['nm_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
                        ->unique()->values()->all();
                    $posterImport = PosterImport::create([
                        'source_user_id' => (int) $data['user_id'],
                        'requested_nm_ids' => $nmIds === [] ? null : $nmIds,
                        'status' => 'pending',
                    ]);

                    ImportPostersJob::dispatch($posterImport->id);
                    Notification::make()->title('Импорт поставлен в очередь')->success()->send();
                }),
        ];
    }
}
