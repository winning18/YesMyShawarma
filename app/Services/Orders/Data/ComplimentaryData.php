<?php

namespace App\Services\Orders\Data;

/**
 * What a freebie needs beyond the order itself — see FreebieService.
 */
final class ComplimentaryData
{
    /**
     * @param  string  $recipientType  ComplimentaryOrder::RECIPIENT_CUSTOMER | RECIPIENT_STAFF
     * @param  ?int  $recipientUserId  the staff member, for RECIPIENT_STAFF
     * @param  ?string  $recipientName  the customer's name, for RECIPIENT_CUSTOMER
     */
    public function __construct(
        public readonly string $recipientType,
        public readonly ?int $recipientUserId,
        public readonly ?string $recipientName,
        public readonly string $reason,
    ) {}
}
