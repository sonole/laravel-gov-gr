<?php

namespace Sonole\GovGr\Tests;

use Sonole\GovGr\Amka\Facades\Amka;
use Sonole\GovGr\BankConfirm\Facades\BankConfirm;
use Sonole\GovGr\Ergani\Facades\Ergani;
use Sonole\GovGr\Gemi\Facades\Gemi;
use Sonole\GovGr\GovGrServiceProvider;
use Sonole\GovGr\TaxisnetOAuth\Facades\TaxisnetOAuth;
use Sonole\GovGr\TaxRegistry\Facades\TaxRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    const SYSTEM_USER_EMAIL = 'system@example.com';
    const SYSTEM_USER_ID = 99;

    protected function getPackageProviders($app): array
    {
        return [
            GovGrServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Gemi' => Gemi::class,
            'Amka' => Amka::class,
            'Ergani' => Ergani::class,
            'TaxRegistry' => TaxRegistry::class,
            'BankConfirm' => BankConfirm::class,
            'TaxisnetOAuth' => TaxisnetOAuth::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->startSession();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
        });

        DB::table('users')->insert(['id' => self::SYSTEM_USER_ID, 'email' => self::SYSTEM_USER_EMAIL]);
    }

    protected function defineEnvironment($app): void
    {
        // Needed for the "web" middleware group (EncryptCookies) the TaxisnetOAuth
        // routes run under.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('govgr.audit.unit', 'TEST');
        $app['config']->set('govgr.audit.system_user_email', self::SYSTEM_USER_EMAIL);
        $app['config']->set('govgr.audit.system_ip', '198.51.100.1');

        $app['config']->set('govgr.gemi.validation.pilot.endpoint', 'https://pilot.test/gemiValidationService');
        $app['config']->set('govgr.gemi.validation.pilot.username', 'pilot-user');
        $app['config']->set('govgr.gemi.validation.pilot.password', 'pilot-pass');
        $app['config']->set('govgr.gemi.board.pilot.endpoint', 'https://pilot.test/gemiBoardRestService/getCompanyBoardV3');
        $app['config']->set('govgr.gemi.board.pilot.username', 'pilot-user');
        $app['config']->set('govgr.gemi.board.pilot.password', 'pilot-pass');
        $app['config']->set('govgr.gemi.basic_info.pilot.endpoint', 'https://pilot.test/gemiBasicInfoRestService/getBasicInfoV3');
        $app['config']->set('govgr.gemi.basic_info.pilot.username', 'pilot-user');
        $app['config']->set('govgr.gemi.basic_info.pilot.password', 'pilot-pass');
        $app['config']->set('govgr.gemi.balance_sheets.pilot.endpoint', 'https://pilot.test/gemiBalanceRestService/companyBalanceSheets');
        $app['config']->set('govgr.gemi.balance_sheets.pilot.username', 'pilot-user');
        $app['config']->set('govgr.gemi.balance_sheets.pilot.password', 'pilot-pass');

        $app['config']->set('govgr.amka.info.pilot.endpoint', 'https://pilot.test/amkaInfoService');
        $app['config']->set('govgr.amka.info.pilot.username', 'pilot-user');
        $app['config']->set('govgr.amka.info.pilot.password', 'pilot-pass');
        $app['config']->set('govgr.amka.info.production.endpoint', 'https://production.test/amkaInfoService');
        $app['config']->set('govgr.amka.info.production.username', 'production-user');
        $app['config']->set('govgr.amka.info.production.password', 'production-pass');
        $app['config']->set('govgr.amka.relationship.pilot.endpoint', 'https://pilot.test/confirmRelAmkaService');
        $app['config']->set('govgr.amka.relationship.pilot.username', 'pilot-user');
        $app['config']->set('govgr.amka.relationship.pilot.password', 'pilot-pass');

        $app['config']->set('govgr.ergani.pilot.endpoint', 'https://pilot.test/erganiService');
        $app['config']->set('govgr.ergani.pilot.username', 'pilot-user');
        $app['config']->set('govgr.ergani.pilot.password', 'pilot-pass');

        $app['config']->set('govgr.afe.pilot.endpoint', 'https://pilot.test/afe4Service');
        $app['config']->set('govgr.afe.pilot.username', 'pilot-user');
        $app['config']->set('govgr.afe.pilot.password', 'pilot-pass');

        $app['config']->set('govgr.tax-registry.registry.pilot.endpoint', 'https://pilot.test/registryService');
        $app['config']->set('govgr.tax-registry.registry.pilot.username', 'pilot-user');
        $app['config']->set('govgr.tax-registry.registry.pilot.password', 'pilot-pass');
        $app['config']->set('govgr.tax-registry.public.pilot.endpoint', 'https://pilot.test/RgWsPublic2');
        $app['config']->set('govgr.tax-registry.public.pilot.username', 'public-user');
        $app['config']->set('govgr.tax-registry.public.pilot.password', 'public-pass');

        $app['config']->set('govgr.efka.pilot.endpoint', 'https://pilot.test/efkaInsuranceService');
        $app['config']->set('govgr.efka.pilot.username', 'pilot-user');
        $app['config']->set('govgr.efka.pilot.password', 'pilot-pass');

        $app['config']->set('govgr.bank-confirm.pilot.endpoint', 'https://pilot.test/confirmBankService');
        $app['config']->set('govgr.bank-confirm.pilot.username', 'pilot-user');
        $app['config']->set('govgr.bank-confirm.pilot.password', 'pilot-pass');

        $app['config']->set('govgr.taxisnet-oauth.pilot.base_url', 'https://pilot.test/oauth2server');
        $app['config']->set('govgr.taxisnet-oauth.pilot.client_id', 'pilot-client-id');
        $app['config']->set('govgr.taxisnet-oauth.pilot.client_secret', 'pilot-client-secret');
    }
}