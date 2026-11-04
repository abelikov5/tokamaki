<?php

namespace App\Filament\Resources\Posters\Schemas;

use App\Models\Poster;
use App\Models\PosterCategory;
use App\Models\PosterImage;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PosterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Публикация')->schema([
                Select::make('status')->label('Статус')->required()->options([
                    'draft' => 'Черновик',
                    'published' => 'Опубликован',
                    'archived' => 'Архив',
                ]),
                TextInput::make('slug')->label('URL')->required()->unique(ignoreRecord: true),
                TextInput::make('nm_id')->label('Основной nm_id')->disabled(),
                TextInput::make('vendor_code')->label('Артикул')->disabled(),
                Toggle::make('is_admin_recommended')
                    ->label('Рекомендация админа')
                    ->helperText('Поднимает картину в каталоге, подборках и похожих товарах.'),
                TextInput::make('view_count')->label('Просмотры')->numeric()->disabled(),
            ])->columns(3)->columnSpanFull(),
            Section::make('Фотографии')->schema([
                View::make('filament.poster-images')
                    ->viewData(fn (?Poster $record): array => [
                        'images' => $record?->images()->get() ?? collect(),
                    ]),
                Section::make('Управление фотографиями')
                    ->description('Добавление, удаление, порядок и подписи изображений')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Repeater::make('images')
                            ->hiddenLabel()
                            ->relationship()
                            ->schema([
                                FileUpload::make('path')
                                    ->label('Фотография')
                                    ->image()
                                    ->disk('public')
                                    ->directory('posters/manual')
                                    ->visibility('public')
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->maxSize(20480)
                                    ->imagePreviewHeight('190')
                                    ->openable()
                                    ->downloadable()
                                    ->required(fn (?PosterImage $record): bool => blank($record?->remote_url)),
                                Select::make('role')->label('Тип')->required()->options([
                                    'main' => 'Главное изображение',
                                    'slide' => 'Дополнительное изображение',
                                ])->default('slide'),
                                TextInput::make('alt_text')->label('Описание для изображения')->maxLength(255),
                            ])
                            ->columns(1)
                            ->grid(3)
                            ->orderColumn('sort_order')
                            ->addActionLabel('Добавить фотографию')
                            ->deleteAction(fn (Action $action) => $action
                                ->label('Удалить фотографию')
                                ->requiresConfirmation())
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ])->columnSpanFull(),
            Section::make('Контент')->schema([
                TextInput::make('title')->label('Название')->required()->maxLength(255)->columnSpanFull(),
                Textarea::make('short_description')->label('Короткое описание')->rows(3)->columnSpanFull(),
                Textarea::make('description')->label('Описание товара')->rows(8)->columnSpanFull(),
            ]),
            Section::make('Каталог и поиск')->schema([
                Select::make('categories')
                    ->label('Группы')
                    ->relationship('categories', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('type')->orderBy('sort_order')->orderBy('name'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Можно выбрать несколько сюжетов, стилей, комнат и коллекций.')
                    ->createOptionForm([
                        TextInput::make('name')->label('Название')->required()->maxLength(255),
                        TextInput::make('slug')->label('URL')->maxLength(255),
                        Select::make('type')->label('Тип')->options(PosterCategory::typeOptions())->required()->default('theme'),
                        Toggle::make('is_active')->label('Показывать в каталоге')->default(true),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
                        $data['sort_order'] = ((int) PosterCategory::max('sort_order')) + 10;

                        return PosterCategory::create($data)->id;
                    }),
                TextInput::make('style')->label('Стиль'),
                TextInput::make('room')->label('Комната'),
                TextInput::make('primary_color')->label('Основные цвета'),
                Select::make('orientation')->label('Ориентация')->options([
                    'vertical' => 'Вертикальная',
                    'horizontal' => 'Горизонтальная',
                    'square' => 'Квадратная',
                ]),
                TagsInput::make('search_keywords')->label('Поисковые слова')->columnSpanFull(),
            ])->columns(2),
            Section::make('SEO')->schema([
                TextInput::make('seo_title')->label('SEO title')->maxLength(60)->columnSpanFull(),
                Textarea::make('seo_description')->label('Meta description')->rows(3)->maxLength(320)->columnSpanFull(),
            ]),
            Section::make('ИИ-контент')->schema([
                TextInput::make('ai_generation_status')->label('Статус')->disabled()->formatStateUsing(fn (?string $state): string => match ($state) {
                    'pending' => 'В очереди',
                    'processing' => 'Генерируется',
                    'generated' => 'Готово',
                    'failed' => 'Ошибка',
                    default => 'Не запускался',
                }),
                TextInput::make('ai_model')->label('Модель')->disabled(),
                TextInput::make('ai_generated_at')->label('Дата генерации')->disabled(),
                Textarea::make('ai_generation_error')->label('Ошибка')->disabled()->columnSpanFull(),
                Textarea::make('ai_response')
                    ->label('Ответ модели (JSON)')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state)
                        ? (string) json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                        : (string) $state)
                    ->rows(18)
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ])->columns(3),
            Section::make('Размеры и WB')->schema([
                Repeater::make('variants')->relationship()->schema([
                    Select::make('size_code')->label('Размер')->required()->options(['30x40' => '30×40', '40x50' => '40×50']),
                    TextInput::make('nm_id')->label('nm_id')->required(),
                    TextInput::make('vendor_code')->label('Артикул')->disabled(),
                    TextInput::make('price')->label('Цена, ₽')->numeric()->minValue(0),
                    Toggle::make('is_active')->label('Активен')->default(true),
                ])->columns(5)->addable(false)->deletable(false)->reorderable(false)->columnSpanFull(),
            ]),
        ]);
    }
}
