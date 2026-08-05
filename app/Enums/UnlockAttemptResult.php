<?php

namespace App\Enums;

enum UnlockAttemptResult
{
    case Unlocked;
    case InvalidCode;
    case TooManyAttempts;
}
