<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allow shared contact accounts (TESTING)
    |--------------------------------------------------------------------------
    |
    | When false (production default) a contact number/email maps to a single
    | account: connecting a guardian is refused if that contact already belongs
    | to a non-guardian account (e.g. a student).
    |
    | When true, one mobile number may be reused across Student and Guardian
    | login accounts. Login resolves the intended account by its password. Email
    | addresses remain unique. The opt-in shared-mobile migration must also have
    | run before duplicates can be stored. Keep this false in production.
    |
    */
    'allow_shared_contact_accounts' => (bool) env('ALLOW_SHARED_CONTACT_ACCOUNTS', false),
];
