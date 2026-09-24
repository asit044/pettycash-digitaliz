<?php

namespace App\Providers;

use App\Contracts\Drive;
use App\Contracts\WhatsAppSender;
use App\Models\PettyCashRequest;
use App\Models\RequestFile;
use App\Services\Drive\GoogleDriveService;
use App\Services\WhatsApp\FonnteWhatsAppSender;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Drive::class, GoogleDriveService::class);
        $this->app->bind(WhatsAppSender::class, FonnteWhatsAppSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('view-any-requests', fn ($user) => in_array($user->role, ['admin', 'finance', 'head']));
        Gate::define('create-requests', fn ($user) => $user->role === 'requester');

        Gate::define('view-request', function ($user, PettyCashRequest $request) {
            return $user->role === 'requester'
                ? (int) $request->requester_id === (int) $user->id
                : in_array($user->role, ['admin', 'finance', 'head']);
        });

        Gate::define('review-requests', fn ($user) => $user->role === 'admin');
        Gate::define('fill-budget-code', fn ($user) => $user->role === 'admin');
        Gate::define('process-requests', fn ($user) => $user->role === 'finance');
        Gate::define('manage-settings', fn ($user) => $user->role === 'admin');
        Gate::define('export-reports', fn ($user) => in_array($user->role, ['admin', 'finance']));

        Gate::define('download-request-file', function ($user, RequestFile $file) {
            return $user->role === 'requester'
                ? (int) $file->request->requester_id === (int) $user->id
                : in_array($user->role, ['admin', 'finance', 'head']);
        });
    }
}
