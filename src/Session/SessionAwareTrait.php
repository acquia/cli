<?php

namespace Acquia\Ads\Session;

/**
 * Class SessionAwareTrait
 * Provides the basic properties needed to fulfill the SessionAwareInterface.
 * @package Acquia\Ads\Session
 */
trait SessionAwareTrait
{

    /**
     * @var Session
     */
    protected $session;

    /**
     * @inheritdoc
     */
    public function setSession(Session $session)
    {
        $this->session = $session;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function session()
    {
        return $this->session;
    }

    /**
     * @inheritdoc
     */
    public function getUser()
    {
        return $this->session()->getUser();
    }
}
