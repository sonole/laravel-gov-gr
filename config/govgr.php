<?php

return [

    'audit' => [
        // Table the audit rows are stored in (and the migration creates).
        // Change it if your app already has its own audit_records table.
        // Set it BEFORE running "php artisan migrate".
        'table' => env('GOVGR_AUDIT_TABLE', 'audit_records'),

        // Sent as the auditUnit field. The name of your organization/unit
        // as registered with the Interoperability Center (ΚΕΔ).
        'unit' => env('GOVGR_AUDIT_UNIT'),

        // Tried in order, first non-null value wins. Add/remove/reorder per
        // app, e.g. drop the header entries entirely if you're not behind
        // any proxy, or add your own via a custom IpResolver:
        // ['driver' => 'custom', 'class' => \App\Audit\MyIpResolver::class],
        'ip_resolver' => [
            ['driver' => 'request'],
            ['driver' => 'header', 'header' => 'CF-Connecting-IP'],
            ['driver' => 'header', 'header' => 'X-Forwarded-For', 'take' => 'first'],
        ],

        // Reported as auditUserIp for calls made with no request at all
        'system_ip' => env('GOVGR_AUDIT_SYSTEM_IP'),

        // Identifies the "system" for calls with no authenticated request
        'system_user_email' => env('GOVGR_AUDIT_SYSTEM_USER_EMAIL'),

        // The Eloquent model class queried for system_user_email, matched
        // against system_user_email_column.
        'system_user_model' => env('GOVGR_AUDIT_SYSTEM_USER_MODEL'),

        // The column on system_user_model matched against system_user_email.
        // Override if that model doesn't use "email" (e.g. a "username" or
        // "login" column instead).
        'system_user_email_column' => env('GOVGR_AUDIT_SYSTEM_USER_EMAIL_COLUMN', 'email'),
    ],

    'amka' => [
        'env' => env('AMKA_ENV', 'pilot'),

        // confirmRelAmkaService
        'relationship' => [
            'pilot' => [
                'endpoint' => env('AMKA_RELATIONSHIP_PILOT_ENDPOINT', 'https://test.gsis.gr/esbpilot/confirmRelAmkaService'),
                'username' => env('AMKA_RELATIONSHIP_PILOT_USERNAME'),
                'password' => env('AMKA_RELATIONSHIP_PILOT_PASSWORD'),
            ],
            'production' => [
                // or https://www1.gsis.gr/esb/confirmRelAmkaService
                'endpoint' => env('AMKA_RELATIONSHIP_PRODUCTION_ENDPOINT', 'https://ked.gsis.gr/esb/confirmRelAmkaService'),
                'username' => env('AMKA_RELATIONSHIP_PRODUCTION_USERNAME'),
                'password' => env('AMKA_RELATIONSHIP_PRODUCTION_PASSWORD'),
            ],
        ],

        // amkaInfoService
        'info' => [
            'pilot' => [
                'endpoint' => env('AMKA_INFO_PILOT_ENDPOINT', 'https://test.gsis.gr/esbpilot/amkaInfoService'),
                'username' => env('AMKA_INFO_PILOT_USERNAME'),
                'password' => env('AMKA_INFO_PILOT_PASSWORD'),
            ],
            'production' => [
                'endpoint' => env('AMKA_INFO_PRODUCTION_ENDPOINT', 'https://ked.gsis.gr/esb/amkaInfoService'),
                'username' => env('AMKA_INFO_PRODUCTION_USERNAME'),
                'password' => env('AMKA_INFO_PRODUCTION_PASSWORD'),
            ],
        ],
    ],

    'bank-confirm' => [
        'env' => env('BANK_CONFIRM_ENV', 'pilot'),

        'pilot' => [
            'endpoint' => env('BANK_CONFIRM_PILOT_ENDPOINT', 'https://test.gsis.gr/esbpilot/confirmBankService'),
            'username' => env('BANK_CONFIRM_PILOT_USERNAME'),
            'password' => env('BANK_CONFIRM_PILOT_PASSWORD'),
        ],

        'production' => [
            'endpoint' => env('BANK_CONFIRM_PRODUCTION_ENDPOINT', 'https://ked.gsis.gr/esb/confirmBankService'),
            'username' => env('BANK_CONFIRM_PRODUCTION_USERNAME'),
            'password' => env('BANK_CONFIRM_PRODUCTION_PASSWORD'),
        ],
    ],

    'ergani' => [
        'env' => env('ERGANI_ENV', 'pilot'),

        'pilot' => [
            'endpoint' => env('ERGANI_PILOT_ENDPOINT', 'https://test.gsis.gr/esbpilot/erganiService'),
            'username' => env('ERGANI_PILOT_USERNAME'),
            'password' => env('ERGANI_PILOT_PASSWORD'),
        ],

        'production' => [
            'endpoint' => env('ERGANI_PRODUCTION_ENDPOINT', 'https://ked.gsis.gr/esb/erganiService'),
            'username' => env('ERGANI_PRODUCTION_USERNAME'),
            'password' => env('ERGANI_PRODUCTION_PASSWORD'),
        ],
    ],

    'gemi' => [
        'env' => env('GEMI_ENV', 'pilot'),

        // checkAfmExistsRequest
        'validation' => [
            'pilot' => [
                'endpoint' => env('GEMI_VALIDATION_PILOT_ENDPOINT', 'https://test.gsis.gr/esbpilot/gemiValidationService'),
                'username' => env('GEMI_VALIDATION_PILOT_USERNAME'),
                'password' => env('GEMI_VALIDATION_PILOT_PASSWORD'),
            ],
            'production' => [
                'endpoint' => env('GEMI_VALIDATION_PRODUCTION_ENDPOINT'),
                'username' => env('GEMI_VALIDATION_PRODUCTION_USERNAME'),
                'password' => env('GEMI_VALIDATION_PRODUCTION_PASSWORD'),
            ],
        ],

        // getCompanyBoardV3
        'board' => [
            'pilot' => [
                'endpoint' => env('GEMI_BOARD_PILOT_ENDPOINT', 'https://test.gsis.gr/esbpilot/gemiBoardRestService/getCompanyBoardV3'),
                'username' => env('GEMI_BOARD_PILOT_USERNAME'),
                'password' => env('GEMI_BOARD_PILOT_PASSWORD'),
            ],
            'production' => [
                'endpoint' => env('GEMI_BOARD_PRODUCTION_ENDPOINT', 'https://ked.gsis.gr/esb/gemiBoardRestService/getCompanyBoardV3'),
                'username' => env('GEMI_BOARD_PRODUCTION_USERNAME'),
                'password' => env('GEMI_BOARD_PRODUCTION_PASSWORD'),
            ],
        ],

        // getBasicInfoV3
        'basic_info' => [
            'pilot' => [
                'endpoint' => env('GEMI_BASIC_INFO_PILOT_ENDPOINT', 'https://test.gsis.gr/esbpilot/gemiBasicInfoRestService/getBasicInfoV3'),
                'username' => env('GEMI_BASIC_INFO_PILOT_USERNAME'),
                'password' => env('GEMI_BASIC_INFO_PILOT_PASSWORD'),
            ],
            'production' => [
                'endpoint' => env('GEMI_BASIC_INFO_PRODUCTION_ENDPOINT', 'https://ked.gsis.gr/esb/gemiBasicInfoRestService/getBasicInfoV3'),
                'username' => env('GEMI_BASIC_INFO_PRODUCTION_USERNAME'),
                'password' => env('GEMI_BASIC_INFO_PRODUCTION_PASSWORD'),
            ],
        ],

        // companyBalanceSheets
        'balance_sheets' => [
            'pilot' => [
                'endpoint' => env('GEMI_BALANCE_SHEETS_PILOT_ENDPOINT', 'https://test.gsis.gr/esbpilot/gemiBalanceRestService/companyBalanceSheets'),
                'username' => env('GEMI_BALANCE_SHEETS_PILOT_USERNAME'),
                'password' => env('GEMI_BALANCE_SHEETS_PILOT_PASSWORD'),
            ],
            'production' => [
                'endpoint' => env('GEMI_BALANCE_SHEETS_PRODUCTION_ENDPOINT', 'https://ked.gsis.gr/esb/gemiBalanceRestService/companyBalanceSheets'),
                'username' => env('GEMI_BALANCE_SHEETS_PRODUCTION_USERNAME'),
                'password' => env('GEMI_BALANCE_SHEETS_PRODUCTION_PASSWORD'),
            ],
        ],
    ],

    'taxisnet-oauth' => [
        'env' => env('TAXISNET_OAUTH_ENV', 'pilot'),

        'pilot' => [
            'base_url' => env('TAXISNET_OAUTH_PILOT_BASE_URL', 'https://test.gsis.gr/oauth2server'),
            'client_id' => env('TAXISNET_OAUTH_PILOT_CLIENT_ID'),
            'client_secret' => env('TAXISNET_OAUTH_PILOT_CLIENT_SECRET'),
        ],
        'production' => [
            'base_url' => env('TAXISNET_OAUTH_PRODUCTION_BASE_URL', 'https://oauth2.gsis.gr/oauth2server'),
            'client_id' => env('TAXISNET_OAUTH_PRODUCTION_CLIENT_ID'),
            'client_secret' => env('TAXISNET_OAUTH_PRODUCTION_CLIENT_SECRET'),
        ],

        'scope' => env('TAXISNET_OAUTH_SCOPE', 'read'),

        // Dev-only override: substituted for whatever redirect_uri the app passes when
        // calling authorizationUrl()/exchangeCodeForToken(), so a non-registered instance
        // can transparently claim the one pre-registered callback URL. See
        // bin/taxisnet-local-domain.sh for how to make that URL actually resolve back to
        // this app locally.
        'redirect_uri' => env('TAXISNET_OAUTH_REDIRECT_URI'),

        // Only set to true when redirect_uri above is a hostname that a local DNS/TLS
        // proxy resolves back to this very app (see bin/taxisnet-local-domain.sh). NEVER
        // set this when redirect_uri is a genuine remote server you don't control, this would
        // incorrectly target it. When true, starting the login from a different
        // hostname (e.g. your normal localhost bookmark) bounces the browser to
        // redirect_uri's host first, so the CSRF state ends up in a session cookie
        // scoped to the same hostname GSIS's callback will land on.
        'redirect_uri_resolves_locally' => env('TAXISNET_OAUTH_REDIRECT_URI_RESOLVES_LOCALLY', false),

        // Set to false to build your own routes/controller instead of using the
        // package's, e.g. if you want the redirect/callback/logout at different URIs.
        // See TaxisnetOAuth\Http\Controllers\TaxisnetOAuthController for what to replicate.
        'routes_enabled' => env('TAXISNET_OAUTH_ROUTES_ENABLED', true),

        // Guard logged out by the default TaxisnetOAuthLogoutHandler before bouncing
        // through GSIS's own logout endpoint. Null uses your app's default guard.
        'logout_guard' => env('TAXISNET_OAUTH_LOGOUT_GUARD'),

        // Where the browser lands after GSIS's logout redirect completes.
        'logout_redirect_to' => env('TAXISNET_OAUTH_LOGOUT_REDIRECT_TO', '/'),

        // TaxisnetOAuth doesn't write to audit_records at all by default.
        // Set to true to log each successfully verified login there too.
        //The audit_records table must then exist (run migrations), same as for the audited ΚΕΔ services.
        'audit_enabled' => env('TAXISNET_OAUTH_AUDIT_ENABLED', false),
    ],

    'tax-registry' => [
        'env' => env('TAX_REGISTRY_ENV', 'pilot'),

        // registryService (ΚΕΔ, audited)
        'registry' => [
            'pilot' => [
                'endpoint' => env('TAX_REGISTRY_REGISTRY_PILOT_ENDPOINT', 'https://test.gsis.gr/esbpilot/registryService'),
                'username' => env('TAX_REGISTRY_REGISTRY_PILOT_USERNAME'),
                'password' => env('TAX_REGISTRY_REGISTRY_PILOT_PASSWORD'),
            ],
            'production' => [
                'endpoint' => env('TAX_REGISTRY_REGISTRY_PRODUCTION_ENDPOINT', 'https://ked.gsis.gr/esb/registryService'),
                'username' => env('TAX_REGISTRY_REGISTRY_PRODUCTION_USERNAME'),
                'password' => env('TAX_REGISTRY_REGISTRY_PRODUCTION_PASSWORD'),
            ],
        ],

        // RgWsPublic2 (public AADE AFM lookup)
        'public' => [
            'pilot' => [
                'endpoint' => env('TAX_REGISTRY_PUBLIC_PILOT_ENDPOINT'),
                'username' => env('TAX_REGISTRY_PUBLIC_PILOT_USERNAME'),
                'password' => env('TAX_REGISTRY_PUBLIC_PILOT_PASSWORD'),
            ],
            'production' => [
                'endpoint' => env('TAX_REGISTRY_PUBLIC_PRODUCTION_ENDPOINT', 'https://www1.gsis.gr/wsaade/RgWsPublic2/RgWsPublic2'),
                'username' => env('TAX_REGISTRY_PUBLIC_PRODUCTION_USERNAME'),
                'password' => env('TAX_REGISTRY_PUBLIC_PRODUCTION_PASSWORD'),
            ],

            // This call's own envelope carries no auditRecord block (unlike registryService
            // above), so nothing structurally requires a local audit_records row. Set to
            // true to log these calls there too - the audit_records table must then exist
            // (run migrations), same as for the audited ΚΕΔ services.
            'audit_enabled' => env('TAX_REGISTRY_PUBLIC_AUDIT_ENABLED', false),
        ],
    ],
];
