<?php
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, Accept,charset,boundary,Content-Length');
header('Access-Control-Allow-Origin: *');

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('token', 'App\Http\Controllers\Api\Auth\AuthController@authenticate');
    Route::post('refresh', 'App\Http\Controllers\Api\Auth\AuthController@refreshToken');
    Route::post('forgot-password', 'App\Http\Controllers\Api\Auth\AuthController@forgotPassword');
    Route::post('check-reset-code', 'App\Http\Controllers\Api\Auth\AuthController@checkResetCode');
    Route::post('reset-password', 'App\Http\Controllers\Api\Auth\AuthController@resetPassword');
});

// User Routes
Route::prefix('users')->middleware('auth:api')->group(function () {
    Route::get('/', 'App\Http\Controllers\Api\Users\UserController@getAllUsers');
    Route::get('/inactive', 'App\Http\Controllers\Api\Users\UserController@getAllInactiveUsers');
    Route::get('/me', 'App\Http\Controllers\Api\Users\UserController@getLoggedInUser');
    Route::post('/', 'App\Http\Controllers\Api\Users\UserController@createUser');
    Route::put('/filters', 'App\Http\Controllers\Api\Users\UserController@saveFilters');
    Route::post('/check-address', 'App\Http\Controllers\Api\Users\UserController@checkAddress');
    Route::put('/{id}', 'App\Http\Controllers\Api\Users\UserController@updateUser');
    Route::delete('/{id}', 'App\Http\Controllers\Api\Users\UserController@deleteUser');
    Route::get('/{id}', 'App\Http\Controllers\Api\Users\UserController@getUser');
    Route::post('/check-dup-email', 'App\Http\Controllers\Api\Users\UserController@checkDupEmail');
    Route::post('/import', 'App\Http\Controllers\Api\Users\UserController@importUsers');
    Route::prefix('address')->middleware('auth:api')->group(function () {
        Route::post('/', 'App\Http\Controllers\Api\Users\UserController@saveAddress');
        Route::post('/admin', 'App\Http\Controllers\Api\Users\UserController@saveAddressAdmin');
        Route::delete('/{id}', 'App\Http\Controllers\Api\Users\UserController@deleteAddress');
        Route::delete('/admin/{user_id}/{id}', 'App\Http\Controllers\Api\Users\UserController@deleteAddressAdmin');
        Route::post('/make-primary', 'App\Http\Controllers\Api\Users\UserController@makeAddressPrimary');
        Route::post('/make-primary/admin', 'App\Http\Controllers\Api\Users\UserController@makeAddressPrimaryAdmin');
    });
});

// Locality Routes
Route::prefix('locality')->middleware('auth:api')->group(function () {
    Route::get('/get-regions', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@getRegions');
    Route::get('/get-territories', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@getTerritories');
    Route::post('/check', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@checkLocality');
    Route::prefix('markets')->group(function () {
        Route::get('/', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@getMarkets');
        Route::get('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@getMarket');
        Route::post('/', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@createMarket');
        Route::delete('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@deleteMarket');
        Route::put('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@updateMarket');
        Route::prefix('regions')->group(function () {
            Route::get('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@getRegion');
            Route::post('/', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@createRegion');
            Route::put('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@updateRegion');
            Route::delete('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@deleteRegion');
            Route::prefix('territories')->group(function () {
                Route::get('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@getTerritory');
                Route::put('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@updateTerritory');
                Route::post('/', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@createTerritory');
                Route::delete('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@deleteTerritory');
            });
        });
    });
    Route::prefix('manufacturing-sites')->group(function () {
        Route::get('/', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@getManufacturingSites');
        Route::get('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@getManufacturingSite');
        Route::post('/', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@createManufacturingSite');
        Route::put('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@updateManufacturingSite');
        Route::delete('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@deleteManufacturingSite');
    });
    Route::prefix('repair-sites')->group(function () {
        Route::get('/', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@getRepairSites');
        Route::get('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@getRepairSite');
        Route::post('/', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@createRepairSite');
        Route::put('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@updateRepairSite');
        Route::delete('/{id}', 'App\Http\Controllers\Api\Admin\Localities\LocalityController@deleteRepairSite');
    });
});

//Asset Routes
Route::prefix('assets')->middleware('auth:api')->group(function () {
    Route::post('/history', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getAssetHistory');
    Route::post('/my-equipment', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getMyEquipment');
    Route::post('/selected-assets', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getSelectedAssets');
    Route::get('/equipment', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getEquipment');
    Route::get('/parent-assets', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getParentAssets');
    Route::get('/shippable', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getShippableAssets');
    Route::post('/receivable', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getReceivableAssets');
    Route::post('/search', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getAssets');
    Route::post('/check-title', 'App\Http\Controllers\Api\Admin\Assets\AssetController@checkTitle');
    Route::post('/check-mn', 'App\Http\Controllers\Api\Admin\Assets\AssetController@checkMnNumber');
    Route::post('/mass-update', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getMassUpdateAssets');
    Route::post('/mass-update/save', 'App\Http\Controllers\Api\Admin\Assets\AssetController@saveMassUpdate');
    Route::post('/', 'App\Http\Controllers\Api\Admin\Assets\AssetController@createAsset');
    Route::post('/duplicate', 'App\Http\Controllers\Api\Admin\Assets\AssetController@duplicateAsset');
    Route::post('/import', 'App\Http\Controllers\Api\Admin\Assets\AssetController@importAssets');
    Route::post('/import/accessories', 'App\Http\Controllers\Api\Admin\Assets\AssetController@importAccessories');
    Route::delete('/{id}', 'App\Http\Controllers\Api\Admin\Assets\AssetController@deleteAsset');
    Route::put('/{id}', 'App\Http\Controllers\Api\Admin\Assets\AssetController@updateAsset');
    Route::get('/{id}', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getAsset');
    Route::post('/add-transaction', 'App\Http\Controllers\Api\Admin\Assets\AssetController@createRMATransaction');
    Route::post('/delete-transaction', 'App\Http\Controllers\Api\Admin\Assets\AssetController@deleteTransaction');
    Route::post('/rma-transactions', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getRMATransactions');
});

//Application Segments Routes
Route::prefix('application-segments')->middleware('auth:api')->group(function () {
    Route::get('/', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getApplicationSegments');
    Route::get('/{id}', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getApplicationSegment');
    Route::post('/', 'App\Http\Controllers\Api\Admin\Assets\AssetController@createApplicationSegment');
    Route::put('/{id}', 'App\Http\Controllers\Api\Admin\Assets\AssetController@updateApplicationSegment');
    Route::delete('/{id}', 'App\Http\Controllers\Api\Admin\Assets\AssetController@deleteApplicationSegment');
});

//Equipment Request Routes
Route::prefix('equipment-requests')->middleware('auth:api')->group(function () {
    Route::get('/', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@getMyRequests');
    Route::get('/all', 'App\Http\Controllers\Api\Admin\EquipmentRequests\EquipmentRequestController@getRequests');
    Route::get('/{id}', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@getEquipmentRequest');
    Route::post('/', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@create');
    Route::post('/info-inquiry', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@createInfoInquiry');
    Route::post('/special-shipping', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@createSpecialShippingRequest');
    Route::post('/new-equipment', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@createNewEquipmentRequest');
    Route::post('/new-accessories', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@createNewAccessoriesRequest');
    Route::post('/fill', 'App\Http\Controllers\Api\Admin\EquipmentRequests\EquipmentRequestController@fill');
    Route::delete('/{id}', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@delete');
    Route::put('/toggle-accepted/{id}', 'App\Http\Controllers\Api\Admin\EquipmentRequests\EquipmentRequestController@toggleAccepted');
});

//Shipping Request Routes
Route::prefix('shipping-requests')->middleware('auth:api')->group(function () {
    Route::get('/', 'App\Http\Controllers\Api\Admin\EquipmentRequests\EquipmentRequestController@getShippingRequests');
    Route::get('/all', 'App\Http\Controllers\Api\Admin\EquipmentRequests\EquipmentRequestController@getShippingRequestsAsAdmin');
    Route::post('/', 'App\Http\Controllers\Api\Admin\EquipmentRequests\EquipmentRequestController@saveShippingRequest');
    Route::get('/shipments', 'App\Http\Controllers\Api\Admin\EquipmentRequests\EquipmentRequestController@getShipments');
    Route::post('/shipments/update', 'App\Http\Controllers\Api\Admin\EquipmentRequests\EquipmentRequestController@updateTrackingNumbers');
});

Route::prefix('receive-equipment')->middleware('auth:api')->group(function () {
    Route::get('/', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@getEquipmentReceive');
    Route::post('/accept', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@saveEquipmentReceive');
});

Route::prefix('customer-drop-off')->middleware('auth:api')->group(function () {
    Route::get('/drop-off-requests', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@getDropOffRequests');
    Route::post('/', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@customerDropOff');
    Route::post('/extend', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@extendRequest');
    Route::post('/customer-data', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@customerData');
    Route::post('/create-pick-up', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@requestPickUp');
    Route::get('/pick-up-requests', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@getPickUpRequests');
    Route::get('/extension-requests', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@getExtensionRequests');
    Route::post('/extension-requests/approve', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@approveExtensionRequest');
    Route::post('/extension-requests/reject', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@rejectExtensionRequest');
    Route::post('/start-pick-up', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@startPickUp');
    Route::post('/reject', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@rejectPickUp');
});

Route::prefix('damage-report')->middleware('auth:api')->group(function () {
    Route::post('/', 'App\Http\Controllers\Api\EquipmentRequests\EquipmentRequestController@submitDamageReport');
});

Route::prefix('help')->middleware('auth:api')->group(function () {
    Route::post('/', 'App\Http\Controllers\Api\Admin\Assets\AssetController@createHelpGuide');
    Route::get('/', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getHelpGuides');
    Route::post('/delete', 'App\Http\Controllers\Api\Admin\Assets\AssetController@deleteHelpGuide');
});

Route::prefix('reports')->middleware('auth:api')->group(function () {
    Route::get('/', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getReports');
    Route::post('/', 'App\Http\Controllers\Api\Admin\Assets\AssetController@getReport');
    Route::post('/create', 'App\Http\Controllers\Api\Admin\Assets\AssetController@createReport');
    Route::post('/delete', 'App\Http\Controllers\Api\Admin\Assets\AssetController@deleteReport');
    Route::post('/auto-report', 'App\Http\Controllers\Api\Admin\Assets\AssetController@setAutoReport');
    Route::post('/auto-report/delete', 'App\Http\Controllers\Api\Admin\Assets\AssetController@deleteAutoReport');
});

Route::get('/test', 'App\Http\Controllers\Api\Users\UserController@test');