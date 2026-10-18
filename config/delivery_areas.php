<?php

/*
|--------------------------------------------------------------------------
| Delivery areas per branch
|--------------------------------------------------------------------------
|
| Neighbourhoods each branch delivers to, keyed by branch slug, as named by
| the owner (2026-10-18). Display/SEO only — shown on the branch's page and
| as `areaServed` in its Restaurant structured data so searches like
| "shawarma in Kwabenya" can find it. It does NOT limit delivery: fees are
| priced by distance with no cap (DeliveryFeeCalculator). Not exhaustive —
| the page adds "and nearby areas". A branch missing here shows no list.
|
*/

return [

    'ga-odumase' => [
        'Amasaman', 'Kwabenya', 'Ablekuman', '3-Junction', 'Olebu', 'Sonitra', 'Vicolace',
    ],

    'pokuase-y-junction' => [
        'Pokuase', 'Ofankor', 'Achimota', 'Mayera', 'Katapor', 'Tantra Hill',
    ],

    'gimpa-branch' => [
        'GIMPA Campus', 'Legon', 'Haatso', 'Christian Village', 'Madina', 'Wisconsin', 'Agbogba',
    ],

];
