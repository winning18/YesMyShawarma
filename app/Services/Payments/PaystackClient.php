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
     * $amount is in pesewas, same subunit Paystack expects everywhere else
     * in this client (see initializeTransaction) — omitted entirely for a
     * full refund of the original transaction, which is why $amount is
     * only added to the payload via array_filter when it's actually given.
     *
     * @return array<string, mixed>
     */
    public function refundTransaction(string $transactionReference, ?int $amount = null, ?string $merchantNote = null): array
    {
        return Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->post('/refund', array_filter([
                'transaction' => $transactionReference,
                'amount' => $amount,
                'merchant_note' => $merchantNote,
            ], fn ($value) => $value !== null))
            ->throw()
            ->json();
    }

    /**
     * Asks Paystack directly what actually happened to a transaction —
     * server-to-server, authenticated with our own secret key, never fed
     * by anything the client supplied. See payments.md's "narrow,
     * deliberate exception" for why this exists alongside the webhook
     * rather than instead of it.
     *
     * @return array<string, mixed>
     */
    public function verifyTransaction(string $reference): array
    {
        return Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->get("/transaction/verify/{$reference}")
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
