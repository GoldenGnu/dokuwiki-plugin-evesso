<?php
/**
 * DokuWiki Plugin evesso (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_evesso extends DokuWiki_Plugin {

    public const CORPORATION_PREFIX = '(corporation)';
    public const ALLIANCE_PREFIX = '(alliance)';
    public const FACTION_PREFIX = '(faction)';

    /**
     * Load the needed libraries and initialize the named oAuth service
     *
     * @param string $servicename
     * @return null|\OAuth\Plugin\AbstractAdapter
     */
    public function loadService(&$servicename) {
        if(!$servicename) return null;

        require_once(__DIR__.'/phpoauthlib/src/OAuth/bootstrap.php');
        require_once(__DIR__.'/classes/AbstractAdapter.php');
        require_once(__DIR__.'/classes/oAuthHTTPClient.php');
        require_once(__DIR__.'/classes/oAuthStorage.php');
        require_once(__DIR__.'/classes/EveOnlineAdapter.php');
        /** @var \OAuth\Plugin\AbstractAdapter $service */
        $service = new \OAuth\Plugin\EveOnlineAdapter();
        if(!$service->isInitialized()) {
            msg("Failed to initialize $service authentication service. Check credentials", -1);
            return null;
        }

        // The generic service can be externally configured
        if(is_a($service->oAuth, 'OAuth\\OAuth2\\Service\\Generic')) {
            $service->oAuth->setAuthorizationEndpoint($this->getAuthEndpoint());
            $service->oAuth->setAccessTokenEndpoint($this->getTokenEndpoint());
        }

        return $service;

    }

    /**
     * The redirect URI used in all oAuth requests
     *
     * @return string
     */
    public function getRedirectURI() {
        if ($this->getConf('custom-redirectURI') !== '') {
            return $this->getConf('custom-redirectURI');
        } else {
            return DOKU_URL . DOKU_SCRIPT;
        }
    }

    /**
     * Get service name
     *
     * @return string
     */
    public function getService() {
        return 'EveOnline';
    }

    public function isAuthPlain() {
        return $this->getConf('singleService') == '';
    }

    public function isEveAuth() {
        return $this->getConf('singleService') != '';
    }

    public function isEveAuthDirect() {
        return $this->getConf('singleService') == 'EveOnline';
    }

    /**
     * Return the configured key for the given service
     *
     * @param $service
     * @return string
     */
    public function getKey() {
        return $this->getConf('eveonline-key');
    }

    /**
     * Return the configured secret for the given service
     *
     * @param $service
     * @return string
     */
    public function getSecret() {
        return $this->getConf('eveonline-secret');
    }

    /**
     * Return the configured Authentication Endpoint URL for the given service
     *
     * @param $service
     * @return string
     */
    public function getAuthEndpoint() {
        return $this->getConf('eveonline-authurl');
    }

    /**
     * Return the configured Access Token Endpoint URL for the given service
     *
     * @param $service
     * @return string
     */
    public function getTokenEndpoint() {
        return $this->getConf('eveonline-tokenurl');
    }

    /**
     * @return array
     */
    public function getGroup($name) {
        if ($this->getConf($name) === '') {
            return array();
        }
        $validGroups = explode(',', trim($this->getConf($name), ','));
        $validGroups = array_map('trim', $validGroups);
        return $validGroups;
    }

    /**
     * @param array $groups
     *
     * @return bool
     */
    public function inGroup($groups, $names, $empty = true) {
        $validGroups = array();
        foreach ($names as $name => $prefix) {
            foreach ($this->getGroup($name) as $group) {
                $validGroups[] = $prefix.$group;
            }
        }

        if (count($validGroups) == 0) {
            return $empty; //nothing set
        }

        foreach ($validGroups as $validGroup) {
            if (in_array($validGroup, $groups, true)) {
                return true;
            }
        }
        return false;
    }
    /**
     * @param array $groups
     *
     * @return bool
     */
    public function checkGroups($groups) {
        if (in_array('admin', $groups, true)) { //Always allow admins
            return true;
        }
        $require = array(
            'require-corporation' => helper_plugin_evesso::CORPORATION_PREFIX,
            'require-alliance' => helper_plugin_evesso::ALLIANCE_PREFIX,
            'require-faction' => helper_plugin_evesso::FACTION_PREFIX,
            );
        if ($this->inGroup($groups, $require)) {
            return true;
        }
        return false;
    }

    /**
     * @param array $session cookie auth session
     *
     * @return bool
     */
    public function validBrowserID ($session) {
        return $session['buid'] == auth_browseruid();
    }

    /**
     * @param array $session cookie auth session
     *
     * @return bool
     */
    public function isSessionTimedOut ($session) {
        global $conf;
        return $session['time'] < time() - $conf['auth_security_timeout'];
    }

    /**
     * @return bool
     */
    public function isGETRequest () {
        global $INPUT;
        $result = $INPUT->server->str('REQUEST_METHOD') === 'GET';
        return $result;
    }

    /**
     * check if we are handling a request to doku.php. Only doku.php defines $updateVersion
     *
     * @return bool
     */
    public function isDokuPHP() {
        global $updateVersion;
        return isset($updateVersion);
    }
}

// vim:ts=4:sw=4:et:
