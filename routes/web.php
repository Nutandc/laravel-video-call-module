<?php

use App\Http\Controllers\VideoChatController;
use App\Http\Controllers\WebrtcStreamingController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/', function () {
    return view('welcome');
});


Route::group(['middleware' => ['auth']], function () {

    Route::get('/video-chat', function () {
        // fetch all users apart from the authenticated user
        $users = User::where('id', '<>', Auth::id())->get();
        return view('video-chat', ['users' => $users]);
    });

//    Route::post('/video/upload-signal', [VideoChatController::class, 'uploadSignal']);

    Route::post('/video/call-user', [VideoChatController::class, 'callUser']);
    Route::post('/video/notify', [VideoChatController::class, 'notify']);
    Route::post('/video/accept-call', [VideoChatController::class, 'acceptCall']);


    // Endpoints to alert call or receive call.
    Route::post('/store-signal-data', [VideoChatController::class, 'storeSignalData']);
    Route::post('/store-signal-data-for-offer', [WebrtcStreamingController::class, 'storeSignalData']);
    Route::get('/get-signal-data/{id}', [VideoChatController::class, 'getSignalData']);


    // Agora Video Call Endpoints
    Route::get('/agora-chat', 'App\Http\Controllers\AgoraVideoController@index');
    Route::post('/agora/token', 'App\Http\Controllers\AgoraVideoController@token');
    Route::post('/agora/call-user', 'App\Http\Controllers\AgoraVideoController@callUser');


    // WebRTC Group Call Endpoints
    // Initiate Stream, Get a shareable broadcast link
    Route::get('/streaming', 'App\Http\Controllers\WebrtcStreamingController@index');
    Route::get('/streaming/{streamId}', 'App\Http\Controllers\WebrtcStreamingController@consumer');
    Route::post('/stream-offer', 'App\Http\Controllers\WebrtcStreamingController@makeStreamOffer');
    Route::post('/stream-answer', 'App\Http\Controllers\WebrtcStreamingController@makeStreamAnswer');
});

/**
 * When you clone the repository, comment out
 *  Auth::routes(['register' => false]);
 * and uncomment
 *   Auth::routes()
 * so that you can register new users. I disabled the registration endpoint so that my hosted demo won't be abused.
 *
 */
// Auth::routes();
Auth::routes(['register' => false]);
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
