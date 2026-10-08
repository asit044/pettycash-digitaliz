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
    private ?Client $client = null;

    private ?GoogleDrive $drive = null;

    private ?string $rootFolderId = null;

    private bool $booted = false;

    public function __construct()
    {
        // Intentionally light: no file, config, or database access here so
        // application boot / artisan / migrations never depend on Drive.
        // All initialization is lazy on first actual Drive use.
    }

    public function isConfigured(): bool
    {
        $credentials = config('services.google.service_account_json');

        return is_string($credentials) && $credentials !== '' && is_file($credentials);
    }

    /**
     * Initialize API client on first use. Returns false when unconfigured
     * instead of throwing, so callers can fall back to local storage.
     */
    private function boot(): bool
    {
        if ($this->booted) {
            return isset($this->drive);
        }

        $this->booted = true;

        if (! $this->isConfigured()) {
            return false;
        }

        $this->client = new Client;
        $this->client->setAuthConfig(config('services.google.service_account_json'));
        $this->client->setScopes([GoogleDrive::DRIVE_FILE]);
        $this->drive = new GoogleDrive($this->client);

        $this->rootFolderId = Setting::get('drive_root_folder_id')
            ?? config('services.google.drive_root_folder_id');

        return true;
    }

    public function ensureRequestFolder(string $requestNumber, string $requesterName): array
    {
        if (! $this->boot()) {
            throw new \RuntimeException('Google Drive is not configured.');
        }

        // Convention: <Perusahaan>/<Tahun>/<MM - Bulan>/<Nomor> - <Nama Pengaju>
        // e.g. "Digitaliz/2026/10 - Oktober/KC-2026-0007 - Budi Santoso".
        $name = trim(preg_replace('/[\\\\\/:*?"<>|]+/', '', $requesterName)) ?: 'Tanpa Nama';
        $folderName = $requestNumber.' - '.$name;
        $year = now()->format('Y');
        $month = now()->format('m').' - '.now()->locale('id')->translatedFormat('F');

        $root = $this->rootFolderId ?: $this->rootFolderId();

        $yearFolder = $this->findFolderByName($root, $year) ?? $this->createFolder($root, $year);
        $monthFolder = $this->findFolderByName($yearFolder, $month) ?? $this->createFolder($yearFolder, $month);
        $requestFolder = $this->findFolderByName($monthFolder, $folderName) ?? $this->createFolder($monthFolder, $folderName);

        return [
            'id' => $requestFolder,
            'url' => 'https://drive.google.com/drive/folders/'.$requestFolder,
        ];
    }

    public function uploadFile(string $folderId, UploadedFile $file, string $fileName): array
    {
        if (! $this->boot()) {
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

        if ($matches->count() > 1) {
            Log::warning('Google Drive folder name collision detected for "'.$name.'", using the first result.');
        }

        return $matches->first()->getId();
    }
}
