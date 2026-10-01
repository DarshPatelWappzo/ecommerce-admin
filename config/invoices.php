<?php

return [
    'prefix' => 'INV',
    'require_reviewed_draft' => false,
    /*
     * Key by the exact tenant database name; there is no shared seller fallback.
     * Seller details are optional and never block issuance. Available name, address,
     * country_code, state_code, tax_registered and gstin values are snapshotted.
     * Other optional settings: terms, prefix, require_reviewed_draft.
     */
    'tenants' => [],
];
