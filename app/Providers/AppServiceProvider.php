<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\Ai\Claude;
use App\Services\Ai\TextModel;
use App\Services\Pdf\Pdftk;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Pdftk::class, fn () => new Pdftk(config('pdf.pdftk')));
        $this->app->bind(TextModel::class, fn () => new Claude(
            new Client(apiKey: (string) config('pdf.ai.key')),
            config('pdf.ai.model'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
