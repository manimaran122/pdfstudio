<?php

use App\Http\Controllers\PageImageController;
use App\Http\Controllers\RecentFilesController;
use App\Support\PdfTools;
use App\Support\RecentFiles;
use App\Support\ToolCatalog;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('tools.index', [
        'categories' => ToolCatalog::categories(),
        'toolCount' => ToolCatalog::toolCount(),
        'recent' => RecentFiles::all(5),
    ]);
})->name('home');

Route::view('/tools/merge', 'tools.merge')->name('tools.merge');

Route::get('/recent', [RecentFilesController::class, 'index'])->name('recent');
Route::get('/recent/{id}/download', [RecentFilesController::class, 'download'])
    ->where('id', '[A-Za-z0-9]{16}')
    ->name('recent.download');

Route::get('/workspace/{workspace}/{file}/{page}/{size}', PageImageController::class)
    ->whereUuid('workspace')->whereNumber('page')
    ->name('workspace.page');

Route::get('/tools/{tool}', function (string $tool) {
    abort_unless(PdfTools::has($tool), 404);

    return view('tools.show', ['tool' => $tool, 'title' => ToolCatalog::find($tool)[0]['name']]);
})->name('tools.show');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});
