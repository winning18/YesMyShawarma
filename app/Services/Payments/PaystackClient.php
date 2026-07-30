<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;

class PaystackClient
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $baseUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function initializeTransaction(array $payload): array
    {
        return Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->post('/transaction/initialize', $payload)
            ->throw()
            ->json();
    }

    /**
     * Paystack signs webhook bodies with HMAC-SHA512 over the raw payload
     * using the secret key (see payments.md: "Verify the signature on every
     * request before parsing the body"). hash_equals is timing-safe.
     */
    public function verifiesSignature(string $rawBody, ?string $signature): bool
    {
        if (! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $this->secretKey), $signature);
    }
}
