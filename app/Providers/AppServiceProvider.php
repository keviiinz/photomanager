<?php

namespace App\Providers;

use App\Models\Gallery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGallerySharePreview();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Give the public gallery page a real link-preview when shared (WhatsApp, iMessage, etc.),
     * pulled from the gallery's own data rather than the generic app title/icon.
     */
    protected function configureGallerySharePreview(): void
    {
        View::composer('partials.head', function ($view) {
            $gallery = request()->route('gallery');

            if (! $gallery instanceof Gallery) {
                return;
            }

            $view->with([
                'ogGallery' => $gallery,
                'ogImage' => $gallery->media()->where('is_featured', true)->first(),
            ]);
        });
    }
}
