# laravel-gov-gr

[![Latest Version](https://img.shields.io/packagist/v/sonole/laravel-gov-gr.svg)](https://packagist.org/packages/sonole/laravel-gov-gr)
[![Total Downloads](https://img.shields.io/packagist/dt/sonole/laravel-gov-gr.svg)](https://packagist.org/packages/sonole/laravel-gov-gr)
[![License](https://img.shields.io/packagist/l/sonole/laravel-gov-gr.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/sonole/laravel-gov-gr.svg)](composer.json)

**A Laravel client for Greek government and business APIs, in one package:** GEMI (business
registry), AMKA (social security number registry), ERGANI (labor/employment registry), AADE
Tax Registry (ΑΦΜ lookups), Bank Confirm (IBAN/ΑΦΜ verification), and TAXISnet (gov.gr) OAuth2
login. Supports PHP 8.1+ and Laravel 10, 11, 12, and 13.

Most of these are ΚΕΔ (Interoperability Center) SOAP/REST services that require your own
registered credentials per environment (pilot/production). TaxisnetOAuth is different: it's
a browser-redirect OAuth2 login flow against GSIS, not a server-to-server API call.

## Install

```bash
composer require sonole/laravel-gov-gr
php artisan vendor:publish --tag=gov-config
```

Publishing the config gives you `config/govgr.php` to edit directly. Everything in it reads
from `.env` by default, so for most setups you never need to touch the file itself.

You only need `php artisan migrate` if you're going to call one of the audited ΚΕΔ services
(Gemi, Amka, Ergani, TaxRegistry, BankConfirm). TaxisnetOAuth and TaxRegistry's public
`lookupByAfm()` don't touch that table by default, so an app that only uses those never
needs to migrate anything from this package, unless it opts in to auditing them too (see
[Audit logging](#audit-logging)). If you forget and call an audited service before
migrating, it throws a clear `RuntimeException` telling you to run the migration, instead
of a raw SQL error.

## Audit logging

Every ΚΕΔ call (Gemi, Amka, Ergani, TaxRegistry, BankConfirm) writes one row to
`audit_records` per physical API call, via the migration this package ships. If your app already has an `audit_records` table of its
own, set `GOVGR_AUDIT_TABLE=govgr_audit_records` (any name) in `.env` **before** running
`php artisan migrate`; the migration, model and report command all follow it. Two things are
required for any of these services to work:

```env
GOVGR_AUDIT_UNIT="Your Organization Name"
```

This is your unit name as registered with ΚΕΔ. It's sent as `auditUnit` on every SOAP call
and the services throw if it's missing.

The other piece is where the caller's IP/identity comes from. By default it just reads the
current request. If you're behind a proxy (Cloudflare, a load balancer) configure
`govgr.audit.ip_resolver` in the published config to read the right header instead.

For calls made outside a request (queued jobs, scheduled commands), set:

```env
GOVGR_AUDIT_SYSTEM_IP=...
GOVGR_AUDIT_SYSTEM_USER_EMAIL=...
```

Export audit records at any time with:

```bash
php artisan govgr:audit-report --service=gemi --from=2026-08-01
```

This is useful as proof of test calls when requesting production credentials from ΚΕΔ.

### Opt-in logging for the two calls that don't require it

`TaxRegistry::lookupByAfm()` (AADE's public RgWsPublic2 lookup) and `TaxisnetOAuth`'s login
callback don't need a local audit trail the way the ΚΕΔ services do; neither sends anything
ΚΕΔ-audit-shaped in its own request, and TaxisnetOAuth is a browser login, not an API call at
all. Both are off by default and independent of each other, so turning one on doesn't require
migrating just for the other:

```env
TAX_REGISTRY_PUBLIC_AUDIT_ENABLED=true
TAXISNET_OAUTH_AUDIT_ENABLED=true
```

Once either is `true`, the same `audit_records` table (and the same `RuntimeException` if
it's missing) applies to that service's calls too.

## Services

Each service is a facade backed by a singleton. All of them accept an optional
`?Request $request` last argument, used to resolve the caller's identity/IP for the audit
record. Pass it explicitly from a controller if you're not resolving it from the current
request automatically.

Set credentials per service via `.env`, e.g. for Gemi's validation endpoint:

```env
GEMI_ENV=pilot
GEMI_VALIDATION_PILOT_USERNAME=...
GEMI_VALIDATION_PILOT_PASSWORD=...
```

See `config/govgr.php` for every service's exact env var names, they follow the same
`{SERVICE}_{SECTION}_{ENV}_{FIELD}` pattern throughout.

### Gemi

```php
use Gemi;

Gemi::validate($afm);        // the raw checkAfmExists answer (no spec for a typed one)
Gemi::getGemiNumbers($afm);  // string[]
Gemi::getGemiNumber($afm);   // ?string

$company = Gemi::basicInfo($afm);   // Company: ->name, ->legalTypeDescr, ->statusDescr, ->address, ->branchType,
                                    // ->applications[], ->activities[], ->capital, ->stocks[], ->isDeleted(), ->raw
$board = Gemi::companyBoard($afm);  // CompanyBoard: ->members[] (natural persons), ->entities[] (legal persons),
                                    // ->activeMembers(), ->representatives()
$sheets = Gemi::balanceSheets($afm); // BalanceSheet[]: ->year, ->netTurnover, ->totalAssets, ->isXbrl, ->files[]
```

Every object keeps the service's record on `->raw` and the `audit_records` row on `->auditRecord`. Board
members' home address, birth date and identity document are real personal data; handle them with care.

### Amka

```php
use Amka;

$c = Amka::confirmRelAmka($relAmka, parentAmka: $parentAmka); // RelationshipConfirmation: ->confirmed, ->info, ->raw
$all = Amka::confirmRelAmkaMultiple([$a, $b], parentAmka: $parentAmka); // a list of them, one per AMKA
$person = Amka::amka2data($amka);   // AmkaPerson: every documented field (->gender, ->idType, ->birthDate,
                                     // ->surnameLatin, ->birthMunicipality, ->address, ->isDeceased, ...)
$person->raw;                        // the service's record as returned
$person->auditRecord;                // the audit_records row of the call

// The current AMKA of someone, from their details. The same AmkaPerson, minus what data2amka doesn't
// answer: gender, ID type, birth place, address and phones stay null.
$amka = Amka::data2amka(surname: $surname, firstName: $firstName, birthDate: '01/02/1990')->amka;

// The same person with the ΠΑΑΥΠΑ indication: ->status is AmkaStatus::Active for a regular AMKA, or one of the
// temporary / cancelled kinds (->status->isCancelled(), ->isVaccinationOnly()). amka2dataLE is the lighter field set.
$person = Amka::amka2dataE($amka);
$person = Amka::amka2dataLE($amka);
// Both throw GovNotFoundException when nobody matches.
```

### Ergani

```php
use Ergani;

$job = Ergani::employeeRelation($afm);  // ?EmploymentRelation: ->employerAfm, ->employerName, ->monthlySalary,
                                        // ->hourlyWage, ->weeklyHours; null when there is no current job
$status = Ergani::employmentRelationship($afm, '03/12/2020'); // EmploymentStatus: ->kind (EmploymentKind::Indefinite,
                                        // FixedTerm, Project) and ->type (EmploymentType::FullTime, PartTime, Rotating)
$history = Ergani::employmentHistoryByPeriod($afm, '01/01/2024', '31/12/2024'); // EmploymentHistoryEntry[]:
                                        // ->employerName, ->headquarters, ->workplace, ->occupationDescr, ->salary, ->from, ->to
```

Dates are sent as DD/MM/YYYY, as the service wants them. Every object keeps the service's record on `->raw`
and the `audit_records` row on `->auditRecord`.

### TaxRegistry

```php
use TaxRegistry;

$entity = TaxRegistry::retrieveInfoByAFM($afm); // RegistryEntity: ->kind is NaturalPerson or LegalEntity,
                                                 // ->raw the whole record, ->auditRecord the audit row
// throws GovNotFoundException (unknown/invalid AFM) or GovDeactivatedException
TaxRegistry::lookupByAfm($afm); // the public RgWsPublic2 lookup, separate credentials
```

### Errors and validation

The Amka and TaxRegistry calls that return an object (`amka2data`, `data2amka`, `amka2dataE`, `amka2dataLE`,
`confirmRelAmka`, `confirmRelAmkaMultiple`, `retrieveInfoByAFM`) end in one of three ways besides success, so
callers only catch these:

| Exception | Meaning |
| --- | --- |
| `GovNotFoundException` | the service answered and has nothing: unknown AFM / AMKA, nobody matches the details |
| `GovDeactivatedException` | the AFM exists but is deactivated (`retrieveInfoByAFM` only) |
| `GovUnavailableException` | no answer: transport failure, a 5xx, or bad credentials (`GovAuthenticationException`, a subtype) |

AFM and AMKA arguments are checked, checksum included, **before** anything is sent, so a typo costs neither a ΚΕΔ
request nor an audit row. A bad one throws `InvalidArgumentException`. Set `GOVGR_VALIDATE_INPUT=false` if your test
environment uses dummy numbers that the service accepts but the checksum does not.

The Gemi and Ergani objects keep those services' existing exceptions: a ΚΕΔ or business error (for instance Gemi's
"Δεν βρέθηκε επιχείρηση") throws `GovServiceException`, and bad credentials `GovAuthenticationException`.

The same checks are Laravel validation rules, so a form can reject a value before the call is ever made:

```php
use Sonole\GovGr\Rules\ValidAfm;
use Sonole\GovGr\Rules\ValidAmka;

$request->validate(['afm' => [new ValidAfm], 'amka' => [new ValidAmka]]); // en and el messages included
ValidAfm::passes($afm); // or the plain boolean check
```

### Faking (local development and tests)

Every ΚΕΔ facade has a `fake()` that replaces the service with one that never calls the network, needs no
credentials and writes no `audit_records` row. The methods and the returned objects stay the same, so the code
under test doesn't change.

```php
TaxRegistry::fake();   // an AFM starting 09, 8 or 9 is a legal entity, any other a natural person built from the AFM
Amka::fake();          // data2amka() builds a valid AMKA from the details, amka2data() derives the person from it
Gemi::fake();          // any 9-digit AFM is a registered ΙΚΕ with a GEMI number, a board and two balance sheets
Ergani::fake();        // any 9-digit AFM has a full-time, indefinite job with an employer derived from the AFM
BankConfirm::fake();   // every IBAN / AFM pair is confirmed

// Override single answers: an object, the service's record array, or an exception to throw.
TaxRegistry::fake(['123456789' => new GovNotFoundException('Ανύπαρκτος Α.Φ.Μ.')]);
Amka::fake(amka2data: ['01019000015' => $person], data2amka: ['000000001' => $person], confirmations: ['02029039233' => false]);
Gemi::fake(companies: ['123456789' => $company], boards: [...], balanceSheets: [...]);
Ergani::fake(relations: ['123456789' => null /* no current job */], statuses: [...], history: [...]);
BankConfirm::fake(['GR16...' => false]);
```

Set `GOVGR_FAKE=true` (config `govgr.fake`) to install all five fakes at boot. It is refused in production. To fake
only locally, point the config at your environment: `'fake' => env('GOVGR_FAKE', env('APP_ENV') === 'local')`. `confirmRelAmka()` confirms every relAmka as "ΤΕΚΝΟ" unless `confirmations` says otherwise, and `amka2dataE()` /
`amka2dataLE()` give the `amka2data()` person as an active AMKA. TaxRegistry's other methods (`lookupByAfm`, ...)
are not faked and throw `BadMethodCallException`.

### BankConfirm

```php
use BankConfirm;

BankConfirm::confirmAccount($iban, $afm); // the raw answer: ['result' => ['bank' => ..., 'confirmed' => 'true'], ...]
```

There is no typed result here: `docs/` has no specification for this service, so the shape is only known from
calls.

Every facade's exact method signatures are in its `Facades\*` class docblock, e.g.
`src/Gemi/Facades/Gemi.php`.

## TaxisnetOAuth

Lets a user log in to your app with their TAXISnet (gov.gr) credentials instead of a
password. GSIS verifies the credentials and hands back the user's ΑΦΜ and identity fields.
This package owns the whole redirect/callback/logout protocol, the same way Fortify owns
its own auth actions. The only thing left for your app is deciding what a verified identity
means for your users.

### Setup

You need a `client_id`/`client_secret` registered with GSIS for a specific `redirect_uri`.
Set them per environment:

```env
TAXISNET_OAUTH_ENV=pilot
TAXISNET_OAUTH_PILOT_CLIENT_ID=...
TAXISNET_OAUTH_PILOT_CLIENT_SECRET=...
```

The package registers its own routes by default:

```
GET  taxisnet-oauth/login            taxisnet-oauth.redirect
GET  taxisnet-oauth/login-callback   taxisnet-oauth.callback
POST taxisnet-oauth/logout           taxisnet-oauth.logout
```

Add a link to `route('taxisnet-oauth.redirect')` on your login page and you're done, GSIS
redirects back to `login-callback` on its own.

If your registered `redirect_uri` doesn't point at `taxisnet-oauth/login-callback` (e.g. an
existing GSIS registration with its own path), set `TAXISNET_OAUTH_REDIRECT_URI` to the
full registered URL. The package detects the path and registers a matching route for it
automatically, no manual route needed.

Set `TAXISNET_OAUTH_ROUTES_ENABLED=false` if you'd rather build your own routes/controller.
`TaxisnetOAuth\Http\Controllers\TaxisnetOAuthController` is what to replicate.

### Login/logout buttons

The package ships two ready-to-use Blade components, so you don't have to build the
markup yourself:

```blade
<x-taxisnet-oauth::login-button />
<x-taxisnet-oauth::logout-button />
```

They're plain HTML/CSS, no Tailwind or Alpine required. The login button is an `<a>` to
`route('taxisnet-oauth.redirect')`, the logout button is a `<form>` posting to
`route('taxisnet-oauth.logout')` (logout has to be a POST, GSIS's logout is triggered via a
real browser redirect from your own server-side logout, not a link). Both style themselves
correctly in light and dark mode on their own: they follow `prefers-color-scheme` by
default, and also respect a manual `dark`/`light` class or `data-theme` attribute on
`<html>` if your app toggles theme that way, so they match whatever theming approach you
already use.

Pass any extra classes or attributes and they merge with the component's own:

```blade
<x-taxisnet-oauth::login-button class="w-full" />
<x-taxisnet-oauth::logout-button class="w-full" />
```

Override the label via the slot, or the target URL if you've disabled the package's own
routes:

```blade
<x-taxisnet-oauth::login-button href="https://your-app.test/custom-login-route">
    Sign in with gov.gr
</x-taxisnet-oauth::login-button>
```

Publish and edit them directly if you want to change the markup itself:

```bash
php artisan vendor:publish --tag=taxisnet-oauth-views
```

### Testing it locally

GSIS only accepts a `redirect_uri` that's already registered for your `client_id`, and it
won't accept `localhost`. `bin/taxisnet-local-domain.sh` (see its own header comment for how
and why) points the real registered domain at your own machine for local testing, with TLS
and DNS handled for you.

Set your `.env` to the pilot credentials registered for that domain, and tell the package
the redirect_uri should resolve locally:

```env
TAXISNET_OAUTH_REDIRECT_URI_RESOLVES_LOCALLY=true
TAXISNET_OAUTH_PILOT_CLIENT_ID=<client id>
TAXISNET_OAUTH_PILOT_CLIENT_SECRET=<client secret>
TAXISNET_OAUTH_REDIRECT_URI=https://registered.domain.gr/taxisnet_authorize
```

Then, with your Laravel dev server running (`php artisan serve`), point the registered
domain at it:

```bash
vendor/sonole/laravel-gov-gr/bin/taxisnet-local-domain.sh registered.domain.gr
```

log in through `taxisnet-oauth.redirect` as normal and GSIS redirects back to the registered domain, which
now resolves to your machine.

### Actually using it: implement the two contracts

The package never touches your `User` model or session on its own. `handleCallback()` just
verifies the login and hands you a `TaxisnetOAuthIdentity` (ΑΦΜ, name, whether it's a legal
entity). What happens next is app-specific, so it's delegated to two contracts you bind in
your own service provider.

**`TaxisnetOAuthLoginResponder`** decides what happens once GSIS has verified someone's
identity:

```php
namespace App\Taxisnet;

use Sonole\GovGr\TaxisnetOAuth\Contracts\TaxisnetOAuthLoginResponder;
use Sonole\GovGr\TaxisnetOAuth\Support\TaxisnetOAuthIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MyLoginResponder implements TaxisnetOAuthLoginResponder
{
    public function respond(TaxisnetOAuthIdentity $identity, Request $request): Response
    {
        if ($identity->isLegalEntity()) {
            return redirect()->route('login')->with(
                'status', 'Company logins are not supported here, please sign in with an individual account.'
            );
        }

        $user = \App\Models\User::where('afm', $identity->afm)->first();

        if (! $user) {
            return redirect()->route('login')->with(
                'status', "No account found for VAT {$identity->afm}. Contact an administrator to get one created."
            );
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
```

**`TaxisnetOAuthLogoutHandler`** decides how your own session ends before the browser is
bounced through GSIS's logout. The default just logs out the default guard and invalidates
the session, override it if you log out a different guard or need to revoke something else.

Bind both in your `AppServiceProvider` (or your own dedicated provider):

```php
use Sonole\GovGr\TaxisnetOAuth\Contracts\TaxisnetOAuthLoginResponder;
use Sonole\GovGr\TaxisnetOAuth\Contracts\TaxisnetOAuthLogoutHandler;

public function register(): void
{
    $this->app->bind(TaxisnetOAuthLoginResponder::class, \App\Taxisnet\MyLoginResponder::class);
    $this->app->bind(TaxisnetOAuthLogoutHandler::class, \App\Taxisnet\MyLogoutHandler::class);
}
```

Without a binding, the package falls back to `DefaultTaxisnetOAuthLoginResponder` (returns
the identity as JSON) and `DefaultTaxisnetOAuthLogoutHandler` (default guard logout).