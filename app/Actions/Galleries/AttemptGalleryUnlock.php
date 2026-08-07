<?php

namespace App\Actions\Galleries;

use App\Enums\UnlockAttemptResult;
use App\Models\Gallery;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AttemptGalleryUnlock
{
    public function __invoke(Gallery $gallery, ?User $user, string $code, string $ip): UnlockAttemptResult
    {
        $key = "gallery-unlock:{$gallery->id}:{$ip}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return UnlockAttemptResult::TooManyAttempts;
        }

        if (! Hash::check($code, $gallery->unlock_code)) {
            RateLimiter::hit($key, 60);

            activity('gallery')
                ->causedBy($user)
                ->performedOn($gallery)
                ->withProperties(['ip' => $ip])
                ->event('unlock_failed')
                ->log("Intentó desbloquear la galería \"{$gallery->title}\" con un código incorrecto");

            return UnlockAttemptResult::InvalidCode;
        }

        RateLimiter::clear($key);

        if ($user) {
            $gallery->unlockFor($user);
        } else {
            $gallery->unlockForGuest();
        }

        activity('gallery')
            ->causedBy($user)
            ->performedOn($gallery)
            ->withProperties(['ip' => $ip])
            ->event('unlocked')
            ->log("Desbloqueó la galería \"{$gallery->title}\"");

        return UnlockAttemptResult::Unlocked;
    }
}
