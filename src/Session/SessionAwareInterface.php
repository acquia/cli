<?php

namespace Acquia\Ads\Session;

use Acquia\Ads\Models\User;

/**
 * Interface SessionAwareInterface
 * Provides an interface for direct injection of the session helper.
 * @package Acquia\Ads\Session
 */
interface SessionAwareInterface
{

    /***
     * Inject a session object.
     *
     * @param Session $session
     * @return void
     */
    public function setSession(Session $session);

    /**
     * Get the current user's session object.
     *
     * @return Session
     */
    public function session();

    /**
     * Get the user model of the logged in user.
     *
     * @return User
     */
    public function getUser();
}
