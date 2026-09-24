<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface Drive
{
    /**
     * Ensure the folder for a given request exists and return its metadata.
     *
     * @return array{id: string, url: string}
     */
    public function ensureRequestFolder(string $requestNumber, string $requesterName): array;

    /**
     * Upload a file into the given folder.
     *
     * @return array{id: string, url: string} drive id + view/download url
     */
    public function uploadFile(string $folderId, UploadedFile $file, string $fileName): array;

    /**
     * Whether a real Google Drive integration is configured.
     */
    public function isConfigured(): bool;
}
