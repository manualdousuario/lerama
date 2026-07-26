<?php

namespace App\Filament\Resources\Feeds\Pages;

use App\Enums\FeedStatus;
use App\Enums\FeedType;
use App\Filament\Resources\Feeds\FeedResource;
use App\Mail\FeedRegisteredMail;
use App\Models\Feed;
use App\Services\FeedSlugService;
use App\Services\FeedTypeDetector;
use App\Services\ItemCountService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateFeed extends CreateRecord
{
    protected static string $resource = FeedResource::class;

    public function getTitle(): string
    {
        return __('admin.feed_form.add_title');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = FeedSlugService::generateForFeed($data['site_url']);

        // The form hides the status field on create; new feeds start online.
        $data['status'] = FeedStatus::Online->value;

        if (empty($data['feed_type'])) {
            $data['feed_type'] = app(FeedTypeDetector::class)->detectType($data['feed_url'])
                ?? FeedType::Rss2->value;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Feed $feed */
        $feed = $this->record;

        // CheckboxList sync happens outside the model events that keep the
        // denormalised counters honest.
        app(ItemCountService::class)->recountTaxonomy(
            $feed->categories()->pluck('categories.id')->map(intval(...))->all(),
            $feed->tags()->pluck('tags.id')->map(intval(...))->all(),
        );

        Cache::flush();

        $this->notifyAdmin($feed);
    }

    private function notifyAdmin(Feed $feed): void
    {
        $adminEmail = (string) config('lerama.admin.email', '');
        $smtpHost = (string) config('mail.mailers.smtp.host', '');

        if ($adminEmail === '' || $smtpHost === '') {
            return;
        }

        try {
            Mail::to($adminEmail)->send(new FeedRegisteredMail($feed));
        } catch (\Throwable $e) {
            Log::error('Failed to send feed registration notification: '.$e->getMessage());
        }
    }
}
