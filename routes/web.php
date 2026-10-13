<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('khoneh', 'dashboard')->name('dashboard');

        // Route::livewire('b', 'pages::projects.index')->name('projects.index');
        Route::livewire('b/no', 'pages::projects.create')->name('projects.create');
        Route::livewire('b/{project}', 'pages::projects.show')->name('projects.show');
        Route::livewire('b/{project}/v', 'pages::projects.edit')->name('projects.edit');



        Route::livewire('b/{project}/y', 'pages::projects.records.index')->name('projects.records.index');
        Route::livewire('b/{project}/y/no', 'pages::projects.records.create')->name('projects.records.create');
        Route::livewire('b/{project}/y/daron-rizy', 'pages::projects.records.import')->name('projects.records.import');
        Route::livewire('b/{project}/y/{record}/v', 'pages::projects.records.edit')->name('projects.records.edit');
        Route::livewire('b/{project}/y/{record}', 'pages::projects.records.show')->name('projects.records.show');
    });



require __DIR__.'/settings.php';
