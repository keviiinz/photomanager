<?php

namespace App\Providers;

use App\Models\Gallery;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
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
        $this->configurePasswordResetEmail();
    }

    /**
     * Send the "forgot password" email in Spanish instead of Laravel's default English copy.
     */
    protected function configurePasswordResetEmail(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            $expires = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject(__('Restablece tu contraseña de :app', ['app' => config('app.name')]))
                ->greeting(__('Hola, :name', ['name' => $notifiable->name]))
                ->line(__('Recibimos una solicitud para restablecer la contraseña de tu cuenta.'))
                ->action(__('Restablecer contraseña'), $url)
                ->line(__('Este enlace caduca en :count minutos.', ['count' => $expires]))
                ->line(__('Si no fuiste tú, puedes ignorar este correo: tu contraseña no cambiará.'))
                ->salutation(__('Saludos,')."\n\n".config('app.name'));
        });
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

        // Mirrors App\Rules\SecurePassword so password managers suggest passwords that pass validation.
        Password::defaults(fn (): Password => Password::min(8)->numbers()->symbols());
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
