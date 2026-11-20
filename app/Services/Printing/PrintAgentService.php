<?php

namespace App\Services\Printing;

use App\Models\Branch;
use App\Models\PrintAgent;
use Illuminate\Support\Str;

/**
 * Connecting an Express POS Print Agent to a branch (step 8b). A manager
 * asks for a one-time code on the Printers page; the agent trades it for a
 * long secret it keeps. Only SHA-256 hashes of both are stored.
 */
class PrintAgentService
{
    /** A connection code works for this long. */
    public const CODE_MINUTES = 15;

    /** Letters and digits that can't be mistaken for each other when typed. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * @return array{0: PrintAgent, 1: string} the agent and its one-time code (shown once)
     */
    public function startConnecting(Branch $branch, string $name, ?PrintAgent $agent = null): array
    {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        $fields = [
            'name' => trim($name),
            'pairing_code_hash' => hash('sha256', $code),
            'pairing_expires_at' => now()->addMinutes(self::CODE_MINUTES),
        ];

        $agent ? $agent->update($fields) : $agent = PrintAgent::create(['branch_id' => $branch->id, ...$fields]);

        return [$agent, substr($code, 0, 4).'-'.substr($code, 4)];
    }

    /**
     * Trades a one-time code for the agent's secret, or null if the code
     * is wrong or expired. Connecting again replaces any earlier secret.
     */
    public function connect(string $code): ?array
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        $agent = PrintAgent::where('pairing_code_hash', hash('sha256', $normalized))
            ->where('pairing_expires_at', '>', now())
            ->first();

        if (! $agent) {
            return null;
        }

        $token = Str::random(64);
        $agent->update([
            'token_hash' => hash('sha256', $token),
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
            'last_seen_at' => now(),
        ]);

        return ['agent' => $agent, 'token' => $token];
    }

    public function authenticate(?string $token): ?PrintAgent
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        return PrintAgent::where('token_hash', hash('sha256', $token))->first();
    }

    public function seen(PrintAgent $agent): void
    {
        $agent->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    /** The branch has a print agent checking in right now. */
    public function isOnline(int $branchId): bool
    {
        return PrintAgent::where('branch_id', $branchId)
            ->whereNotNull('token_hash')
            ->where('last_seen_at', '>', now()->subSeconds(PrintAgent::ONLINE_SECONDS))
            ->exists();
    }

    public function disconnect(PrintAgent $agent): void
    {
        $agent->delete();
    }
}
