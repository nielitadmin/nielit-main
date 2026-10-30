<?php
/**
 * Central payment portal (MIS source). Copy to config/payment_hub.php and fill in the shared secret from Admin → Sources.
 */
return [
    'enabled' => false,
    'api_base' => 'https://payment.nielitbhubaneswar.in',
    'source' => 'mis',
    'shared_secret' => '',
    /** Optional: copy from payment hub Admin → Invoice & GST (enables “View invoice” to open the portal PDF). */
    'invoice_public_secret' => '',
];
