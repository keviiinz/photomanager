<?php

namespace App\Http\Controllers;

use App\Models\HomeImage;
use Illuminate\Support\Facades\Storage;

class HomeImageController extends Controller
{
    /**
     * Stream a home page image. These are public marketing assets, not
     * access-controlled like gallery media, so the response is cacheable.
     */
    public function show(HomeImage $homeImage)
    {
        $response = Storage::disk($homeImage->disk)->response($homeImage->path);
        $response->setPublic()->setMaxAge(86400);

        return $response;
    }
}
