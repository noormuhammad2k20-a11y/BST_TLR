<?php

namespace App\Providers;

use App\View\Composers\LayoutComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Shared chrome (sidebar badges, bell, drawer) resolved once per request.
        View::composer([
            'layouts.sidebar', 'layouts.header', 'layouts.app',
            'cloth-store.layouts.sidebar', 'cloth-store.layouts.header', 'cloth-store.layouts.app'
        ], LayoutComposer::class);

        // Surface N+1 queries in the log during development without ever
        // breaking a page for the user.
        Model::preventLazyLoading(!$this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
            Log::warning(sprintf(
                'Lazy loaded relation [%s] on [%s] — consider eager loading it.',
                $relation,
                $model::class
            ));
        });
    }
}
