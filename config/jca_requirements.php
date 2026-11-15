<?php

return [
    // Owner-confirmed minimum catalog. Each school year receives its own editable
    // database rules; this template only fills missing records.
    'document_types' => [
        'BIRTH_CERTIFICATE' => 'Birth Certificate',
        'FORM_138' => 'School Form 9 (SF9)',
        'FORM_137' => 'Form 137',
        'CERTIFICATE_LOV' => 'Certificate of LOV',
    ],
    'categories' => [
        'regular' => ['BIRTH_CERTIFICATE', 'FORM_138'],
        'transferee' => ['BIRTH_CERTIFICATE', 'FORM_138', 'FORM_137'],
        'support_review' => ['BIRTH_CERTIFICATE', 'CERTIFICATE_LOV'],
    ],
    'notes' => 'Submit the hard-copy requirements to the JCA Registrar for in-person verification. No online upload is required.',
];
