<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicStorageController extends Controller
{
    public function __invoke(string $path): StreamedResponse
    {
        abort_if($path === '' || str_contains($path, '..'), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $response = $disk->response($path);
        $response->setPublic();
        $response->setMaxAge(86400);

        return $response;
    }
}
