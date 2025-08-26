<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\Test2Controller;
use App\Http\Controllers\ProfileController;

Route::get('/query1', [TestController::class,'query1']);
Route::get('/query2', [TestController::class,'query2']);
Route::get('/query3', [TestController::class,'query3']);
Route::get('/query4', [TestController::class,'query4']);
Route::get('/query5', [TestController::class,'query5']);
Route::get('/query6', [TestController::class,'query6']);
Route::get('/query7', [TestController::class,'query7']);
Route::get('/query8', [TestController::class,'query8']);
Route::get('/query9', [TestController::class,'query9']);
Route::get('/query10', [TestController::class,'query10']);
Route::get('/query11', [TestController::class,'query11']);
Route::get('/query12', [TestController::class,'query12']);
Route::get('/query13', [TestController::class,'query13']);
Route::get('/query14', [TestController::class,'query14']);
Route::get('/query15', [TestController::class,'query15']);
Route::get('/query16', [TestController::class,'query16']);
Route::get('/query17', [TestController::class,'query17']);
Route::get('/query18', [TestController::class,'query18']);
Route::get('/query19', [TestController::class,'query19']);
Route::get('/query20', [TestController::class,'query20']);


Route::get('/test/query1', [Test2Controller::class,'query1']);
Route::get('/test/query2', [Test2Controller::class,'query2']);
Route::get('/test/query3', [Test2Controller::class,'query3']);
Route::get('/test/query4', [Test2Controller::class,'query4']);
Route::get('/test/query5', [Test2Controller::class,'query5']);
Route::get('/test/query6', [Test2Controller::class,'query6']);
Route::get('/test/query7', [Test2Controller::class,'query7']);
Route::get('/test/query8', [Test2Controller::class,'query8']);
Route::get('/test/query9', [Test2Controller::class,'query9']);
Route::get('/test/query10', [Test2Controller::class,'query10']);
Route::get('/test/query11', [Test2Controller::class,'query11']);
Route::get('/test/query12', [Test2Controller::class,'query12']);
Route::get('/test/query13', [Test2Controller::class,'query13']);
Route::get('/test/query14', [Test2Controller::class,'query14']);
Route::get('/test/query15', [Test2Controller::class,'query15']);
Route::get('/test/query16', [Test2Controller::class,'query16']);
Route::get('/test/query17', [Test2Controller::class,'query17']);
Route::get('/test/query18', [Test2Controller::class,'query18']);
Route::get('/test/query19', [Test2Controller::class,'query19']);
Route::get('/test/query20', [Test2Controller::class,'query20']);
Route::get('/test/query21', [Test2Controller::class,'query21']);
Route::get('/test/query22', [Test2Controller::class,'query22']);
Route::get('/test/query23', [Test2Controller::class,'query23']);
Route::get('/test/query24', [Test2Controller::class,'query24']);
Route::get('/test/query25', [Test2Controller::class,'query25']);

