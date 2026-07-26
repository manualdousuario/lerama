<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Filament\Resources\Tags\TagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTags extends ManageRecords
{
    protected static string $resource = TagResource::class;

    public function getTitle(): string
    {
        return __('admin.tags.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.tags.new')),
        ];
    }
}
