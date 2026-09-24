<?php

namespace App\Services\Drive;

use App\Contracts\Drive;
use App\Models\Setting;
use Google\Client;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class GoogleDriveService implements Drive
{
    private Client $client;

    private GoogleDrive $drive;

    private ?string $rootFolderId;

    public function __construct()
    {
        $this->client = new Client;
        $credentials = config('services.google.service_account_json');

        if (is_file($credentials)) {
            $this->client->setAuthConfig($credentials);
            $this->client->setScopes([GoogleDrive::DRIVE_FILE]);
            $this->drive = new GoogleDrive($this->client);
        }

        $this->rootFolderId = Setting::get('drive_root_folder_id')
            ?? config('services.google.drive_root_folder_id');
    }

    public function isConfigured(): bool
    {
        return isset($this->drive);
    }

    public function ensureRequestFolder(string $requestNumber, string $requesterName): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Google Drive is not configured.');
        }

        $slug = str()->slug($requesterName) ?: 'tanpa-nama';
        $folderName = $requestNumber.'-'.$slug;
        $year = now()->format('Y');

        $root = $this->rootFolderId ?: $this->rootFolderId();

        $yearFolder = $this->findFolderByName($root, $year) ?? $this->createFolder($root, $year);
        $requestFolder = $this->findFolderByName($yearFolder, $folderName) ?? $this->createFolder($yearFolder, $folderName);

        return [
            'id' => $requestFolder,
            'url' => 'https://drive.google.com/drive/folders/'.$requestFolder,
        ];
    }

    public function uploadFile(string $folderId, UploadedFile $file, string $fileName): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Google Drive is not configured.');
        }

        $fileMetadata = new GoogleDrive\DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
        ]);

        $content = file_get_contents($file->getRealPath()) ?: '';

        $uploaded = $this->drive->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $file->getMimeType(),
            'uploadType' => 'multipart',
            'fields' => 'id,name',
        ]);

        return [
            'id' => $uploaded->getId(),
            'url' => 'https://drive.google.com/file/d/'.$uploaded->getId().'/view',
        ];
    }

    private function rootFolderId(): string
    {
        $folder = $this->findFolderByName(null, Setting::get('company_name', 'Petty Cash Digitaliz'));

        if ($folder !== null) {
            return $folder;
        }

        $created = $this->drive->files->create(new GoogleDrive\DriveFile([
            'name' => Setting::get('company_name', 'Petty Cash Digitaliz'),
            'mimeType' => 'application/vnd.google-apps.folder',
        ]), ['fields' => 'id']);

        $rootId = $created->getId();

        Setting::set('drive_root_folder_id', $rootId);

        return $rootId;
    }

    private function createFolder(string $parentId, string $name): string
    {
        $folder = $this->drive->files->create(new GoogleDrive\DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]), ['fields' => 'id']);

        return $folder->getId();
    }

    private function findFolderByName(?string $parentId, string $name): ?string
    {
        $query = "mimeType='application/vnd.google-apps.folder' and name='".addslashes($name)."' and trashed=false";
        if ($parentId !== null) {
            $query .= " and '".$parentId."' in parents";
        }

        $response = $this->drive->files->listFiles([
            'q' => $query,
            'fields' => 'files(id,name)',
            'spaces' => 'drive',
        ]);

        $matches = collect($response->getFiles());

        if ($matches->isEmpty()) {
            return null;
        }

        Log::warning('Google Drive folder name collision detected for "'.$name.'", using the first result.');

        return $matches->first()->getId();
    }
}
