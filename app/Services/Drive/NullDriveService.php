<?php

namespace App\Services\Drive;

use App\Contracts\Drive;
use Illuminate\Http\UploadedFile;

class NullDriveService implements Drive
{
    public function ensureRequestFolder(string $requestNumber, string $requesterName): array
    {
        return [
            'id' => 'local-'.$requestNumber,
            'url' => null,
        ];
    }

    public function uploadFile(string $folderId, UploadedFile $file, string $fileName): array
    {
        return [
            'id' => null,
            'url' => null,
        ];
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
