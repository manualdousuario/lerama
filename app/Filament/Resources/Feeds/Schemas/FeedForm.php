<?php

namespace App\Filament\Resources\Feeds\Schemas;

use App\Enums\FeedStatus;
use App\Enums\FeedType;
use App\Support\UrlValidator;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FeedForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->label(__('suggest.form.title'))
                    ->required()
                    ->maxLength(255)
                    ->validationMessages(['required' => __('validation.title_required')])
                    ->columnSpanFull(),

                TextInput::make('site_url')
                    ->label(__('admin.feed_form.site_url'))
                    ->url()
                    ->required()
                    ->maxLength(512)
                    ->placeholder('https://exemplo.com')
                    ->rules([self::safeUrlRule('site_url')])
                    ->validationMessages([
                        'required' => __('validation.site_url_required'),
                        'url' => __('validation.site_url_valid'),
                    ]),

                TextInput::make('feed_url')
                    ->label(__('admin.feed_form.feed_url'))
                    ->url()
                    ->required()
                    ->maxLength(512)
                    ->placeholder('https://exemplo.com/feed.xml')
                    ->rules([self::safeUrlRule('feed_url')])
                    ->validationMessages([
                        'required' => __('validation.feed_url_required'),
                        'url' => __('validation.feed_url_valid'),
                    ]),

                Select::make('language')
                    ->label(__('common.language'))
                    ->options(fn (): array => collect(array_keys(config('lerama.languages', [])))
                        ->mapWithKeys(fn (string $code): array => [$code => __('lang.'.$code)])
                        ->all())
                    ->default('pt_BR')
                    ->required()
                    ->selectablePlaceholder(false)
                    ->validationMessages(['in' => __('validation.language_invalid')]),

                Select::make('feed_type')
                    ->label(__('admin.feed_form.feed_type'))
                    ->options(fn (): array => collect(FeedType::cases())
                        ->mapWithKeys(fn (FeedType $type): array => [$type->value => strtoupper($type->value)])
                        ->all())
                    // Left blank on purpose: FeedTypeDetector fills it in on create.
                    ->placeholder(__('admin.feed_form.auto_detect'))
                    ->helperText(__('admin.feed_form.feed_type_help')),

                Select::make('status')
                    ->label(__('common.status'))
                    ->options(fn (): array => collect(FeedStatus::cases())
                        ->mapWithKeys(fn (FeedStatus $status): array => [$status->value => __('status.'.$status->value)])
                        ->all())
                    ->required()
                    ->selectablePlaceholder(false)
                    // New feeds always start online, as the legacy form did.
                    ->visibleOn('edit')
                    ->validationMessages(['in' => __('validation.status_invalid')]),

                Toggle::make('proxy_only')
                    ->label(__('admin.feed_form.proxy_only'))
                    ->default(false),

                Toggle::make('shuffle')
                    ->label(__('admin.feed_form.shuffle'))
                    ->default(true),

                CheckboxList::make('categories')
                    ->label(__('admin.feed_form.categories'))
                    ->relationship('categories', 'name')
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2),

                CheckboxList::make('tags')
                    ->label(__('admin.feed_form.tags'))
                    ->relationship('tags', 'name')
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2),
            ]);
    }

    /**
     * SSRF guard shared with the public suggestion flow: `url` on its own still
     * accepts private hosts and non-HTTP schemes.
     *
     * Filament evaluates closures passed to rules() through its own injector,
     * so the Laravel rule has to be returned by an outer closure rather than
     * passed directly.
     */
    private static function safeUrlRule(string $field): Closure
    {
        return static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            if (is_string($value) && $value !== '' && ! UrlValidator::validate($value)['valid']) {
                $fail(__('validation.'.$field.'_valid'));
            }
        };
    }
}
