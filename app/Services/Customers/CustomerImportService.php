<?php

namespace App\Services\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Customers → Import contacts: adds outside leads (a CSV of phone numbers,
 * optionally names) to `customers`, so SMS broadcasts reach them. No
 * separate leads table (owner, 2026-10-21): a lead is just a customer row
 * with no orders yet, and if they order later findOrCreateByPhone() finds
 * that same row, so their history joins up.
 *
 * Numbers already in the list are never changed, except to fill a missing
 * name — an imported row must never overwrite what a real customer told
 * us, nor clear their broadcast opt-out.
 */
class CustomerImportService
{
    public const MAX_ROWS = 5000;

    private const PHONE_HEADER = '/phone|mobile|number|contact|tel|whatsapp/i';

    private const NAME_HEADER = '/name/i';

    public function __construct(private readonly CustomerService $customers) {}

    /**
     * @return array{added: int, existing: int, names_filled: int, duplicates: int, invalid: int, invalid_rows: list<array{line: int, value: string}>, truncated: bool}
     */
    public function import(string $path): array
    {
        [$rows, $truncated] = $this->readCsv($path);
        [$phoneCol, $nameCol, $rows] = $this->columns($rows);

        $result = ['added' => 0, 'existing' => 0, 'names_filled' => 0, 'duplicates' => 0, 'invalid' => 0, 'invalid_rows' => [], 'truncated' => $truncated];
        $contacts = [];

        foreach ($rows as [$line, $cells]) {
            $raw = trim((string) ($cells[$phoneCol] ?? ''));

            if ($raw === '') {
                continue;
            }

            $phone = $this->normalise($raw);

            if ($phone === null) {
                $result['invalid']++;
                if (count($result['invalid_rows']) < 20) {
                    $result['invalid_rows'][] = ['line' => $line, 'value' => $raw];
                }

                continue;
            }

            if (isset($contacts[$phone])) {
                $result['duplicates']++;

                continue;
            }

            $name = $nameCol !== null ? trim((string) ($cells[$nameCol] ?? '')) : '';
            $contacts[$phone] = $name !== '' ? mb_substr($name, 0, 255) : null;
        }

        DB::transaction(function () use ($contacts, &$result) {
            foreach (array_chunk($contacts, 500, preserve_keys: true) as $chunk) {
                $existing = Customer::whereIn('phone', array_keys($chunk))->get()->keyBy('phone');

                foreach ($chunk as $phone => $name) {
                    $customer = $existing->get($phone);

                    if (! $customer) {
                        Customer::create(['phone' => $phone, 'name' => $name]);
                        $result['added']++;

                        continue;
                    }

                    $result['existing']++;

                    if ($name !== null && blank($customer->name)) {
                        $customer->update(['name' => $name]);
                        $result['names_filled']++;
                    }
                }
            }
        });

        return $result;
    }

    /**
     * A Ghana number in any common form, or null. Also accepts the 9-digit
     * form a spreadsheet leaves behind when it drops the leading zero
     * ("241234567").
     */
    private function normalise(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        if (strlen($digits) === 9 && ! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        return $this->customers->isValidGhanaPhone($digits) ? $this->customers->normalizeGhanaPhone($digits) : null;
    }

    /**
     * @return array{0: list<array{0: int, 1: list<string>}>, 1: bool}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $first = (string) fgets($handle);
        rewind($handle);

        // Excel in some locales saves with semicolons.
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $rows = [];
        $line = 0;
        $truncated = false;

        while (($cells = fgetcsv($handle, null, $delimiter)) !== false) {
            $line++;

            if ($line === 1 && isset($cells[0])) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
            }

            if ($cells === [null]) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS + 1) {
                $truncated = true;
                break;
            }

            $rows[] = [$line, array_map(fn ($cell) => (string) $cell, $cells)];
        }

        fclose($handle);

        return [$rows, $truncated];
    }

    /**
     * Finds the phone and name columns from a header row when there is one
     * ("Phone", "Mobile number", "Name"...), otherwise assumes phone first,
     * name second.
     *
     * @param  list<array{0: int, 1: list<string>}>  $rows
     * @return array{0: int, 1: ?int, 2: list<array{0: int, 1: list<string>}>}
     */
    private function columns(array $rows): array
    {
        if ($rows === []) {
            return [0, null, []];
        }

        $header = $rows[0][1];
        $phoneCol = null;
        $nameCol = null;

        foreach ($header as $i => $cell) {
            // Name first: "Contact name" is a name column, not a phone one.
            if (preg_match(self::NAME_HEADER, $cell)) {
                $nameCol ??= $i;
            } elseif (preg_match(self::PHONE_HEADER, $cell)) {
                $phoneCol ??= $i;
            }
        }

        if ($phoneCol !== null) {
            return [$phoneCol, $nameCol, array_slice($rows, 1)];
        }

        $rows = $nameCol !== null ? array_slice($rows, 1) : $rows;
        $sample = $rows[0][1] ?? [];

        // No phone header: whichever of the first two columns looks like a number.
        $phoneCol = ($this->normalise($sample[0] ?? '') === null && $this->normalise($sample[1] ?? '') !== null) ? 1 : 0;

        return [$phoneCol, $nameCol ?? ($phoneCol === 0 ? 1 : 0), $rows];
    }
}
