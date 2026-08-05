<?php

namespace App\Policies;

use App\Models\Gallery;
use App\Models\User;

class GalleryPolicy
{
    /**
     * Determine whether the user can view the model's management page.
     */
    public function view(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->photographer_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isPhotographer();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->photographer_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->photographer_id;
    }
}
