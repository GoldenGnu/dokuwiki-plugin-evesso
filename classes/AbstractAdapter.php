<?php

namespace OAuth\Plugin;

use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\ServiceFactory;

/**
 * Class AbstractAdapter
 *
 * For each service that shall be used for logging into DokuWiki a subclass of this abstract
 * class has to be created. It defines how to talk to the Service's API to retrieve user
 * information
 *
 * @package OAuth\Plugin
 */
abstract class AbstractAdapter {

    /** @var \OAuth\Common\Service\AbstractService|\OAuth\OAuth2\Service\AbstractService|\OAuth\OAuth2\Service\AbstractService */
    public $oAuth = null;
    /** @var \helper_plugin_evesso */
    protected $hlp = null;
    /** @var \OAuth\Plugin\oAuthStorage */
    protected $storage = null;

    /**
     * Constructor
     *
     * @param $url
     */
    public function __construct($url) {
        $this->hlp = plugin_load('helper', 'evesso');

        $credentials = new Credentials(
            $this->hlp->getKey($this->getAdapterName()),
            $this->hlp->getSecret($this->getAdapterName()),
            $url
        );

        $this->storage = new oAuthStorage();

        $serviceFactory = new ServiceFactory();
        $serviceFactory->setHttpClient(new oAuthHTTPClient());
        $this->oAuth = $serviceFactory->createService(
            $this->getServiceName(),
            $credentials,
            $this->storage,
            $this->getScope()
        );
    }

    /**
     * Check if the initialization worked
     *
     * @return bool
     */
    public function isInitialized() {
        if(is_null($this->oAuth)) {
            return false;
        }
        return true;
    }

    /**
     * Redirects to the service for requesting access
     *
     * This is the first step of oAuth authentication
     *
     * This implementation tries to abstract away differences between oAuth1 and oAuth2,
     * but might need to be overwritten for specific services
     */
    public function login() {
        if(is_a($this->oAuth, 'OAuth\OAuth2\Service\AbstractService')) { /* oAuth2 handling */

            $url = $this->oAuth->getAuthorizationUri();
        } else { /* oAuth1 handling */

            // extra request needed for oauth1 to request a request token :-)
            $token = $this->oAuth->requestRequestToken();

            $url = $this->oAuth->getAuthorizationUri(array('oauth_token' => $token->getRequestToken()));
        }

        send_redirect($url);
    }

    /**
     * Clear storage token for user
     *
     */
    public function logout() {
        if ($this->isInitialized()) {
            $this->oAuth->getStorage()->clearToken($this->oAuth->service());
        }
    }

    /**
     * Check access_token
     *
     * Update as needed
     *
     * @return bool true if access_token is valid. false otherwise
     */
    public function checkToken() {
        global $INPUT;

        if ($INPUT->get->has('code')) {
            if (!$this->requestAccessToken()) { //Request access token (Second step of oAuth authentication)
                return false;
            }
        } else {
            //Check if access token is still valid, if not, refresh the access_token
            if (!$this->checkAccessToken() && !$this->refreshAccessToken()) {
                return false;
            }
        }

        $validDomains = $this->hlp->getValidDomains();
        if (count($validDomains) > 0) {
            $userData = $this->getUser();
            if (!$this->hlp->checkMail($userData['mail'])) {
                msg(sprintf($this->hlp->getLang("rejectedEMail"),join(', ', $validDomains)),-1);
                send_redirect(wl('', array('do' => 'login',),false,'&'));
            }
        }
        return true;
    }

    /**
     * Request access token
     * 
     * Second step of oAuth authentication
     * 
     * @global type $INPUT
     * @global \OAuth\Plugin\type $conf
     * @return boolean true if successful. false otherwise
     */
    private function requestAccessToken() {
        global $INPUT, $conf;

        try {
            $this->oAuth->requestAccessToken($INPUT->get->str('code'), $INPUT->get->str('state', null));
            $this->oAuth->getStorage()->clearAuthorizationState($this->oAuth->service());
            return true;
        } catch (TokenResponseException $e) {
            msg($e->getMessage(), -1);
            if ($conf['allowdebug']) {
                msg('<pre>' . hsc($e->getTraceAsString()) . '</pre>', -1);
            }
            return false;
        }
    }

    /**
     * Check if access token is still valid
     * 
     * @global type $conf
     * @return boolean true if access_token is vaild. false otherwise
     */
    private function checkAccessToken() {
        global $conf;
        try {
            if ($this->oAuth->getStorage()->retrieveAccessToken($this->oAuth->service())->getEndOfLife() - 90 > time()) {
                return true; // access_token is still vaild - already validated
            }
        } catch (TokenNotFoundException $e) {
            msg($e->getMessage(), -1);
            if ($conf['allowdebug']) {
                msg('<pre>' . hsc($e->getTraceAsString()) . '</pre>', -1);
            }
            return false; // oAuth storage have no token
        }
    }

    /**
     * Refresh access_token (from refresh_token)
     * 
     * @global \OAuth\Plugin\type $conf
     * @return boolean true if successful. false otherwise
     */
    private function refreshAccessToken() {
        global $conf;
        try {
            $this->oAuth->refreshAccessToken($this->oAuth->getStorage()->retrieveAccessToken($this->oAuth->service()));
            return true;
        } catch (TokenNotFoundException | TokenResponseException $e) {
            msg($e->getMessage(), -1);
            if ($conf['allowdebug']) {
                msg('<pre>' . hsc($e->getTraceAsString()) . '</pre>', -1);
            }
            return false;
        }
    }

    /**
     * Return the name of the oAuth service class to use
     *
     * This should match with one of the files in
     * phpoauth/src/oAuth/oAuth[12]/Service/*
     *
     * By default extracts the name from the class name
     *
     * @return string
     */
    public function getServiceName() {
        return $this->getAdapterName();
    }

    /**
     * Retrun the name of this Adapter
     *
     * It specifies which configuration setting should be used
     *
     * @return string
     */
    public function getAdapterName() {
        $name = preg_replace('/Adapter$/', '', get_called_class());
        $name = str_replace('OAuth\\Plugin\\', '', $name);
        return $name;
    }

    /**
     * Return the scope to request
     *
     * This should return the minimal scope needed for accessing the user's data
     *
     * @return array
     */
    public function getScope() {
        return array();
    }

    /**
     * Retrieve the user's data
     *
     * The array needs to contain at least 'email', 'name', 'user', 'grps'
     *
     * @return array
     */
    abstract public function getUser();
}
