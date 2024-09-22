<?php


class RscDtgs_Environment_Aware implements RscDtgs_Environment_AwareInterface
{

    /**
     * @var RscDtgs_Environment
     */
    protected $environment;

    /**
     * Sets the environment.
     *
     * @param RscDtgs_Environment $environment
     */
    public function setEnvironment(RscDtgs_Environment $environment)
    {
        $this->environment = $environment;
    }
}