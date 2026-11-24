<?php

namespace App\Services\Setup;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes a client's sandbox copy safe to work in (owner, 2026-11-24; Ghana's
 * Data Protection Act): every customer's name, phone, email and saved
 * addresses are replaced with made-up ones, exact delivery locations are
 * blurred to about a kilometre, free-text notes are cleared, Paystack's
 * stored payloads, visitor tracking, sign-in sessions and queued jobs are
 * removed, and the client's own website domain is disconnected. The menu,
 * prices, orders, totals, staff accounts and settings stay, so the copy
 * still works and looks like theirs.
 *
 * Run only on a sandbox (expresspos:scrub-sandbox refuses a live install).
 */
class SandboxScrubber
{
    /** Tables emptied outright: logs, sign-ins and anything waiting to be sent. */
    private const EMPTIED = [
        'page_views', 'visitor_sessions', 'sessions', 'password_reset_tokens', 'personal_access_tokens',
        'push_subscriptions', 'newsletter_subscribers', 'jobs', 'failed_jobs', 'job_batches',
    ];

    /**
     * @return array<string, int> what was changed, table => rows
     */
    public function scrub(): array
    {
        $done = [];

        DB::transaction(function () use (&$done) {
            $done['customers'] = $this->eachRow('customers', fn (object $row) => [
                'name' => $row->name === null ? null : 'Customer '.$row->id,
                'phone' => $row->phone === null ? null : $this->phone($row->id),
                'email' => $row->email === null ? null : 'customer'.$row->id.'@example.test',
                'password' => null,
                'suspension_reason' => null,
            ]);

            $done['customer_addresses'] = $this->eachRow('customer_addresses', fn (object $row) => [
                'landmark' => 'Landmark '.$row->id,
                'ghanapost_code' => null,
                'area_other' => $row->area_other === null ? null : 'Area '.$row->id,
                'lat' => $row->lat === null ? null : round((float) $row->lat, 2),
                'lng' => $row->lng === null ? null : round((float) $row->lng, 2),
            ]);

            $done['orders'] = $this->eachRow('orders', function (object $row) {
                if ($row->delivery_address_snapshot === null) {
                    return null;
                }
                $address = json_decode($row->delivery_address_snapshot, true) ?: [];
                foreach (['landmark', 'ghanapost_code', 'area_other', 'notes', 'street', 'directions'] as $key) {
                    if (array_key_exists($key, $address) && $address[$key] !== null) {
                        $address[$key] = $key === 'landmark' ? 'Landmark' : null;
                    }
                }
                foreach (['lat', 'lng'] as $key) {
                    if (isset($address[$key]) && is_numeric($address[$key])) {
                        $address[$key] = round((float) $address[$key], 2);
                    }
                }

                return ['delivery_address_snapshot' => json_encode($address)];
            }, ['id', 'delivery_address_snapshot']);

            $done['order_items'] = DB::table('order_items')->whereNotNull('notes')->update(['notes' => null]);
            $done['table_tabs'] = DB::table('table_tabs')->update(['customer_name' => null, 'customer_phone' => null]);
            $done['complimentary_orders'] = DB::table('complimentary_orders')->where('recipient_type', 'customer')->update(['recipient_name' => 'Customer']);
            $done['sms_broadcast_recipients'] = $this->eachRow('sms_broadcast_recipients', fn (object $row) => ['phone' => $this->phone($row->id)], ['id']);
            $done['payments'] = DB::table('payments')->whereNotNull('raw_payload')->update(['raw_payload' => null]);

            foreach (self::EMPTIED as $table) {
                if (Schema::hasTable($table)) {
                    $done[$table] = DB::table($table)->delete();
                }
            }

            // The sandbox never answers on the client's own website domain.
            DB::table('settings')->where('key', SettingsService::WEBSITE_DOMAIN)->delete();
        });

        return $done;
    }

    /** A made-up but well-formed Ghanaian number, unique per row: +233 then nine digits. */
    private function phone(int $id): string
    {
        return '+2339'.str_pad((string) $id, 8, '0', STR_PAD_LEFT);
    }

    /**
     * @param  callable(object): ?array<string, mixed>  $change
     * @param  list<string>  $columns
     */
    private function eachRow(string $table, callable $change, array $columns = ['*']): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $count = 0;
        DB::table($table)->select($columns)->orderBy('id')->chunkById(500, function ($rows) use ($table, $change, &$count) {
            foreach ($rows as $row) {
                if (($values = $change($row)) !== null) {
                    DB::table($table)->where('id', $row->id)->update($values);
                    $count++;
                }
            }
        });

        return $count;
    }
}
