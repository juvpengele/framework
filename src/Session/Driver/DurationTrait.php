<?php

namespace Bow\Session\Driver;

trait DurationTrait
{
    /**
     * Create the timestamp
     *
     * @param int max_lifetime
     * @return string
     */
    private function createTimestamp($max_lifetime = null)
    {
        $lifetime = !is_null($max_lifetime) ? $max_lifetime : (config('session.lifetime') * 60);

        return date('Y-m-d H:i:s', time() + (int) $lifetime);
    }
}
