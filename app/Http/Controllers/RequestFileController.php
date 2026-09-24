<?php

namespace App\Http\Controllers;

use App\Models\RequestFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestFileController extends Controller
{
    public function __invoke(RequestFile $file): StreamedResponse
    {
        abort_unless(auth()->user()->can('download-request-file', $file), 403);

        abort_if($file->request === null || $file->storage_path === null, 404);

        abort_unless(Storage::disk('local')->exists($file->storage_path), 404);

        return Storage::disk('local')->download($file->storage_path, $file->original_name);
    }
}
