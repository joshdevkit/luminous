<?php

namespace Core\Database\Traits;

use Core\Support\Carbon;

trait HasTimestamps
{
    protected function updateTimestamps(): void
    {
        $now = Carbon::now(config('app.timezone'))->format($this->dateFormat);

        if (!$this->exists && defined('static::CREATED_AT') && static::CREATED_AT) {
            $this->setAttribute(static::CREATED_AT, $now);
        }

        if (defined('static::UPDATED_AT') && static::UPDATED_AT) {
            $this->setAttribute(static::UPDATED_AT, $now);
        }
    }
}
