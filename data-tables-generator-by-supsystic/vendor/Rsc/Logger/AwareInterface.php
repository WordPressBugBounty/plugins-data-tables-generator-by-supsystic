<?php

/**
 * Describes a logger-aware instance
 */
interface RscDtgs_Logger_AwareInterface
{
    /**
     * Sets a logger instance on the object
     *
     * @param RscDtgs_Logger_Interface $logger
     * @return null
     */
    public function setLogger(RscDtgs_Logger_Interface $logger);
}