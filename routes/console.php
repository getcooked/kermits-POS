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

Artisan::command('products:optimize-images {--prune : Also delete product pictures no product uses} {--dry-run : Only report what would change}', function (ProductImageProcessor $images) {
    $disk = Storage::disk('public');
    $dryRun = (bool) $this->option('dry-run');
    $paths = Product::query()
        ->whereNotNull('image_path')
        ->pluck('image_path')
        ->unique()
        ->reject(fn (string $path) => filter_var($path, FILTER_VALIDATE_URL) || str_ends_with(strtolower($path), '.webp'));

    foreach ($paths as $path) {
        if (! $disk->exists($path)) {
            $this->warn("Missing: {$path}");

            continue;
        }

        $processed = $images->processContents($disk->get($path));

        if ($processed === null) {
            $this->error("Could not read: {$path}");

            continue;
        }

        $newPath = preg_replace('/\.[^.\/]+$/', '', $path).'.webp';
        $this->line(sprintf('%s (%d KB) -> %s (%d KB)', $path, $disk->size($path) / 1024, $newPath, strlen($processed) / 1024));

        if (! $dryRun) {
            $disk->put($newPath, $processed);
            Product::query()->where('image_path', $path)->update(['image_path' => $newPath]);
            $disk->delete($path);
        }
    }

    $used = Product::query()->whereNotNull('image_path')->pluck('image_path')->flip();
    $unused = collect($disk->allFiles('products'))->reject(fn (string $path) => $used->has($path));

    foreach ($unused as $path) {
        $this->line(($this->option('prune') && ! $dryRun ? 'Deleted unused: ' : 'Unused: ').$path);

        if ($this->option('prune') && ! $dryRun) {
            $disk->delete($path);
        }
    }
})->purpose('Trim, square, resize and convert product pictures to WebP');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
