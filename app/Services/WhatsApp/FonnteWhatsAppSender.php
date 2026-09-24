<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteWhatsAppSender implements WhatsAppSender
{
    public function isConfigured(): bool
    {
        return filled(config('services.fonnte.token'));
    }

    public function send(string $phone, string $message): array
    {
        if (! $this->isConfigured()) {
            return ['provider_message_id' => null, 'error' => 'Fonnte token is not configured.'];
        }

        $response = Http::asForm()
            ->withToken(config('services.fonnte.token'))
            ->timeout(30)
            ->post(config('services.fonnte.url', 'https://api.fonnte.com/send'), [
                'target' => $phone,
                'message' => $message,
            ]);

        $body = $response->json();

        if ($response->failed() || data_get($body, 'status') !== true) {
            $error = data_get($body, 'reason', $response->body());

            Log::warning('Fonnte failed to send WhatsApp message.', [
                'phone' => $phone,
                'error' => $error,
            ]);

            return ['provider_message_id' => null, 'error' => $error];
        }

        $id = data_get($body, 'id', data_get($body, 'data.0', null));

        return ['provider_message_id' => is_string($id) || is_int($id) ? (string) $id : null, 'error' => null];
    }
}
