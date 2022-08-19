<?php

use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\UserAuthController;
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
Route::post('/register', [UserAuthController::class, 'register']);
Route::post('/login', [UserAuthController::class, 'login']);

Route::middleware('auth:api')->group( function () {
    //Route::resource('expenses', ExpenseController::class);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::get('/show/expenses', [ExpenseController::class, 'showExpenses']);
    Route::get('/show/balances', [ExpenseController::class, 'showBalances']);
    Route::get('/expense_types', [ExpenseController::class, 'expenseTypes']);
});
/* Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
}); */
