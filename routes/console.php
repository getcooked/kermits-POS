<?php

use App\Models\Product;
use App\Services\ProductImageProcessor;
use App\Services\ReservationSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('reservations:expire', function () {
    $count = app(ReservationSchedule::class)->expireHolds();
    $this->info("Expired {$count} reservation holds.");
})->purpose('Release pending reservations after their approval deadline');

Schedule::command('reservations:expire')->everyMinute()->withoutOverlapping();

Artisan::command('mail:diagnose {to? : Address to receive the test emails}', function (?string $to = null) {
    $to ??= config('mail.from.address');
    $this->line('Default mailer: '.config('mail.default'));

    foreach (['smtp', 'smtp_ssl'] as $mailer) {
        $config = config("mail.mailers.{$mailer}");
        $this->line("{$mailer}: {$config['host']}:{$config['port']} as ".($config['username'] ?: '(no username)'));

        try {
            Mail::mailer($mailer)->raw("Kermit's mail test via {$mailer}.", fn ($message) => $message->to($to)->subject("Kermit's mail test ({$mailer})"));
            $this->info("  Sent to {$to}.");
        } catch (Throwable $exception) {
            $this->error('  '.$exception::class.': '.$exception->getMessage());
        }
    }
})->purpose('Send a test email through each SMTP mailer and print any errors');

Artisan::command('products:optimize-images
    {--all : Also re-process pictures that are already WebP (run once after the picture standard changes)}
    {--clear-missing : Remove picture links whose file no longer exists}
    {--clear-logos : Remove the Kermit\'s logo used as a stand-in picture}
    {--prune : Also delete product pictures no product uses}
    {--dry-run : Only report what would change}', function (ProductImageProcessor $images) {
    $disk = Storage::disk('public');
    $dryRun = (bool) $this->option('dry-run');
    $backupDirectory = 'products/originals/'.now()->format('Ymd-His');
    $manifest = [];
    $counts = ['converted' => 0, 'logo' => 0, 'missing' => 0, 'failed' => 0];
    $paths = Product::query()
        ->whereNotNull('image_path')
        ->pluck('image_path')
        ->unique()
        ->reject(fn (string $path) => filter_var($path, FILTER_VALIDATE_URL));

    // Originals are moved aside, not deleted, and the manifest records which products used them.
    $replace = function (string $path, ?string $newPath, string $reason) use ($disk, $dryRun, $backupDirectory, &$manifest): void {
        $productIds = Product::query()->where('image_path', $path)->pluck('id')->all();
        $manifest[] = ['reason' => $reason, 'old_path' => $path, 'new_path' => $newPath, 'product_ids' => $productIds];

        if ($dryRun) {
            return;
        }

        Product::query()->whereKey($productIds)->toBase()->update(['image_path' => $newPath]);

        if ($disk->exists($path)) {
            $disk->move($path, $backupDirectory.'/'.$path);
        }
    };

    foreach ($paths as $path) {
        if (! $disk->exists($path)) {
            $counts['missing']++;
            $this->warn($this->option('clear-missing') ? 'Missing, link '.($dryRun ? 'would be ' : '')."removed: {$path}" : "Missing: {$path} (use --clear-missing to remove the link)");

            if ($this->option('clear-missing')) {
                $replace($path, null, 'missing');
            }

            continue;
        }

        $contents = $disk->get($path);

        if ($images->isBrandLogo($contents)) {
            $counts['logo']++;
            $this->warn($this->option('clear-logos') ? 'Logo stand-in '.($dryRun ? 'would be ' : '')."removed: {$path}" : "Logo stand-in: {$path} (use --clear-logos to remove it)");

            if ($this->option('clear-logos')) {
                $replace($path, null, 'logo');
            }

            continue;
        }

        if (! $this->option('all') && str_ends_with(strtolower($path), '.webp')) {
            continue;
        }

        $processed = $images->processContents($contents);

        if ($processed === null) {
            $counts['failed']++;
            $this->error("Could not read: {$path}");

            continue;
        }

        $counts['converted']++;
        $newPath = $dryRun ? 'products/(new).webp' : $images->put($processed);
        $this->line(sprintf('%s (%d KB) -> %s (%d KB)', $path, $disk->size($path) / 1024, $newPath, strlen($processed) / 1024));
        $replace($path, $newPath, 'converted');
    }

    if ($manifest !== [] && ! $dryRun) {
        $disk->put($backupDirectory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("Originals and manifest kept in storage/app/public/{$backupDirectory}");
    }

    $this->info(sprintf(
        '%s%d standardized, %d logo stand-ins, %d missing files, %d unreadable.',
        $dryRun ? '[dry run] ' : '',
        $counts['converted'],
        $counts['logo'],
        $counts['missing'],
        $counts['failed'],
    ));

    $used = Product::query()->whereNotNull('image_path')->pluck('image_path')->flip();
    $unused = collect($disk->allFiles('products'))
        ->reject(fn (string $path) => $used->has($path) || str_starts_with($path, 'products/originals/'));

    foreach ($unused as $path) {
        $this->line(($this->option('prune') && ! $dryRun ? 'Deleted unused: ' : 'Unused: ').$path);

        if ($this->option('prune') && ! $dryRun) {
            $disk->delete($path);
        }
    }
})->purpose('Bring product pictures to the house standard: 800x800 WebP on white, dish centered at the same scale');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
