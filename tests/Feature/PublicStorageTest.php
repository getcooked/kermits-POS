<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_disk_files_are_served_without_a_storage_symlink(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('payment/qr-code.jpg', 'qr-image');

        $this->get(route('public.media', ['path' => 'payment/qr-code.jpg']))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_public_storage_path_traversal_is_rejected(): void
    {
        $this->get(route('public.media', ['path' => '../.env']))->assertNotFound();
    }
}
