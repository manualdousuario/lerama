<?php

namespace App\Filament\Support;

use App\Support\Slugger;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class TaxonomySchema
{
    /** @param  class-string<Model>  $model */
    public static function configure(Schema $schema, string $model): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('common.name'))
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if (blank($get('slug'))) {
                            $set('slug', Slugger::slug((string) $state));
                        }
                    })
                    ->validationMessages(['required' => __('validation.name_required')]),

                TextInput::make('slug')
                    ->label(__('common.slug'))
                    ->maxLength(100)
                    ->helperText(__('admin.category_form.slug_help'))
                    ->unique($model, 'slug', ignoreRecord: true)
                    ->validationMessages(['unique' => __('validation.slug_exists')])
                    ->dehydrateStateUsing(fn (Get $get, ?string $state): string => filled($state)
                        ? trim($state)
                        : Slugger::slug((string) $get('name'))),
            ]);
    }
}
