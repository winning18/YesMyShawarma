<?php

return [

    /*
     * Whether a module with no stored switch counts as on. A real install
     * keeps this false — a new business starts as a plain counter POS and
     * modules are switched on during setup (owner, 2026-11-15); Yes My
     * Shawarma's switches are stored, all on. The test suite sets it true
     * (phpunit.xml) so every test runs against a fully switched-on install
     * unless it switches a module off itself.
     */
    'default_on' => (bool) env('MODULES_DEFAULT_ON', false),

];
