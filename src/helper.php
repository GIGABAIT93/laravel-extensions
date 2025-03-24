<?php

if (!function_exists('extensions')) {
    /**
     * Get the instance of the Extensions manager.
     *
     * @return \Gigabait93\Extensions\Services\Extensions
     */
    function extensions()
    {
        return app(\Gigabait93\Extensions\Services\Extensions::class);
    }
}