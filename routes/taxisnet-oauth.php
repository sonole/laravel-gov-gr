<?php

use Sonole\GovGr\TaxisnetOAuth\Http\Controllers\TaxisnetOAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('taxisnet-oauth')->as('taxisnet-oauth.')->group(function () {
    Route::get('login', [TaxisnetOAuthController::class, 'redirect'])->name('redirect');
    Route::get('login-callback', [TaxisnetOAuthController::class, 'callback'])->name('callback');
    Route::post('logout', [TaxisnetOAuthController::class, 'logout'])->name('logout');
});

$overridePath = trim((string) parse_url(config('govgr.taxisnet-oauth.redirect_uri', ''), PHP_URL_PATH), '/');

if ($overridePath !== '' && $overridePath !== 'taxisnet-oauth/login-callback') {
    Route::middleware('web')->get($overridePath, [TaxisnetOAuthController::class, 'callback']);
}
