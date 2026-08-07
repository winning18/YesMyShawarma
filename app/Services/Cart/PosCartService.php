<?php

namespace App\Services\Cart;

/**
 * Identical to CartService in every respect except which session key it
 * reads/writes — a staff member's POS cart and a customer's checkout cart
 * must never share state, even in the same browser session.
 */
class PosCartService extends CartService
{
    protected function sessionKey(): string
    {
        return 'pos_cart';
    }
}
