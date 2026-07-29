<?php

namespace App\Console\Commands;

use App\Services\Feeds\ImageExtractor;
use App\Services\ProxyService;
use Illuminate\Console\Command;

class ExtractImages extends Command
{
    protected $signature = 'image:extract {limit? : Maximum number of items to process}';

    protected $description = 'Extract OpenGraph images for items with no image_url';

    public function handle(): int
    {
        $limit = $this->argument('limit');
        $limit = is_numeric($limit) ? (int) $limit : null;

        $extractor = new ImageExtractor(
            app(ProxyService::class),
            (int) config('lerama.image_extract_batch_size', 50),
            fn (string $msg) => $this->line($msg),
        );

        $extractor->run($limit);

        return self::SUCCESS;
    }
}
