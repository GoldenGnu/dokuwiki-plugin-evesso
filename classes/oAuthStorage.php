<?php

namespace OAuth\Plugin;

use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\Common\Token\TokenInterface;
use OAuth\OAuth2\Token\StdOAuth2Token;

/**
 * Class oAuthStorage
 */
class oAuthStorage implements TokenStorageInterface {

    /**
     * The path to the file where tokens for this service are stored
     *
     * @param string $service
     * @return string
     */
    protected function getStateFile() {
        return getCacheName(session_id(), '.oauth');
    }

    /**
     * Load the data from disk
     *
     * @param string $service
     * @return array
     */
    protected function loadStateFile() {
        $file = $this->getStateFile();
        if(file_exists($file)) {
            return unserialize(io_readFile($file, false));
        } else {
            return array();
        }
    }

    /**
     * Load the data from cookie
     *
     * @param string $service
     * @return array
     */
    protected function getLoadToken($service) {
        if (isset($_SESSION[DOKU_COOKIE]['evesso-storage']['token'])) {
            return unserialize($_SESSION[DOKU_COOKIE]['evesso-storage']['token']);
        } else {
            return null;
        }
    }

    /**
     * @param string $service
     *
     * @return TokenInterface
     *
     * @throws TokenNotFoundException
     */
    public function retrieveAccessToken($service) {
        $token = $this->getLoadToken($service);
        if(!isset($token)) {
            $this->clearAuthorizationState($service);
            throw new TokenNotFoundException('No token found in storage');
        }
        return $token;
    }

    /**
     * @param string         $service
     * @param TokenInterface $token
     *
     * @return TokenStorageInterface
     */
    public function storeAccessToken($service, TokenInterface $token) {
         $_SESSION[DOKU_COOKIE]['evesso-storage']['token'] = serialize($token);
    }

    /**
     * @param string $service
     *
     * @return bool
     */
    public function hasAccessToken($service) {
        $token = $this->getLoadToken($service);
        return isset($token);
    }

    /**
     * Delete the users token. Aka, log out.
     *
     * @param string $service
     *
     * @return TokenStorageInterface
     */
    public function clearToken($service) {
        if (isset($_SESSION[DOKU_COOKIE]['evesso-storage']['token'])) {
            unset($_SESSION[DOKU_COOKIE]['evesso-storage']['token']);
        }
    }

    /**
     * Delete *ALL* user tokens. Use with care. Most of the time you will likely
     * want to use clearToken() instead.
     *
     * @return TokenStorageInterface
     */
    public function clearAllTokens() {
        // TODO: Implement clearAllTokens() method.
    }

    /**
     * Store the authorization state related to a given service
     *
     * @param string $service
     * @param string $state
     *
     * @return TokenStorageInterface
     */
    public function storeAuthorizationState($service, $state) {
        $data = array();
        $data['state'] = $state;
        $file = $this->getStateFile();
        io_saveFile($file, serialize($data));
    }

    /**
     * Check if an authorization state for a given service exists
     *
     * @param string $service
     *
     * @return bool
     */
    public function hasAuthorizationState($service) {
        $data = $this->loadStateFile();
        return isset($data['state']);
    }

    /**
     * Retrieve the authorization state for a given service
     *
     * @param string $service
     *
     * @throws \OAuth\Common\Storage\Exception\TokenNotFoundException
     * @return string
     */
    public function retrieveAuthorizationState($service) {
        $data = $this->loadStateFile();
        if(!isset($data['state'])) {
            throw new TokenNotFoundException('No state found in storage');
        }
        return $data['state'];
    }

    /**
     * Clear the authorization state of a given service
     *
     * @param string $service
     *
     * @return TokenStorageInterface
     */
    public function clearAuthorizationState($service) {
        $file = $this->getStateFile();
        @unlink($file);
        $file = getCacheName('oauth', '.purged');
        //Only do this once
        if(file_exists($file)) {
           return; 
        }
        $this->clearAllAuthorizationStates();
        io_saveFile($file, 'oauth purged');
    }

    /**
     * Delete *ALL* user authorization states. Use with care. Most of the time you will likely
     * want to use clearAuthorization() instead.
     *
     * @return TokenStorageInterface
     */
    public function clearAllAuthorizationStates() {
        global $conf;
        $directory = $conf['cachedir'];
        $this->removeRecursive($directory);
    }

    function removeRecursive($directory) {
        array_map('unlink', glob("$directory/*.oauth"));
        foreach (glob("$directory/*", GLOB_ONLYDIR) as $dir) {
            $this->removeRecursive($dir);
        }
        return true;
    }
}