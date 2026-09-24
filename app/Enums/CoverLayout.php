<?php

namespace App\Enums;

/**
 * How the cover (hero) of a public gallery page is laid out.
 */
enum CoverLayout: string
{
    case Center = 'center';
    case Left = 'left';
    case Novel = 'novel';
    case Vintage = 'vintage';
    case Frame = 'frame';
    case Stripe = 'stripe';

    public function label(): string
    {
        return match ($this) {
            self::Center => __('Centro'),
            self::Left => __('Izquierda'),
            self::Novel => __('Novela'),
            self::Vintage => __('Vintage'),
            self::Frame => __('Marco'),
            self::Stripe => __('Raya'),
        };
    }
}
