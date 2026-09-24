<?php

namespace App\Contracts;

interface WhatsAppSender
{
    /**
     * Send a WhatsApp message to the given phone number.
     *
     * @return array{provider_message_id: string|null, error: string|null}
     */
    public function send(string $phone, string $message): array;

    /**
     * Whether a real WhatsApp gateway is configured.
     */
    public function isConfigured(): bool;
}
