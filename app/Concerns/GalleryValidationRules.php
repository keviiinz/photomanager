<?php

namespace App\Concerns;

trait GalleryValidationRules
{
    /**
     * @return array<int, string>
     */
    protected function titleRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<int, string>
     */
    protected function clientNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<int, string>
     */
    protected function unlockCodeRules(): array
    {
        return ['required', 'string', 'min:6', 'max:32', 'alpha_num'];
    }

    /**
     * @return array<int, string>
     */
    protected function locationRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * @return array<int, string>
     */
    protected function availableUntilRules(): array
    {
        return ['nullable', 'date'];
    }
}
