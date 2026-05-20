<?php

namespace SV\ExpiringUserUpgrades;

/**
 * Add-on globals.
 */
class Globals
{
    /** @var bool */
    public static $forceDowngradeAlert = false;

    /** @var null|string */
    public static $downgradeReason = null;

    /**
     * Private constructor, use statically.
     */
    private function __construct()
    {
    }
}
