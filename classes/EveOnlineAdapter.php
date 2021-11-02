<?php

namespace OAuth\Plugin;

use OAuth\OAuth2\Service\EveOnline;

/**
 * Class DoorkeeperAdapter
 *
 * This is an example on how to implement your own adapter for making DokuWiki login against
 * a custom oAuth provider. The used Generic Service backend expects the authorization and
 * token endpoints to be configured in the DokuWiki backend.
 *
 * Your custom API to access user data has to be implemented in the getUser function. The one here
 * is setup to work with the demo setup of the "Doorkeeper" ruby gem.
 *
 * @link https://github.com/doorkeeper-gem/doorkeeper
 * @package OAuth\Plugin
 */
class EveOnlineAdapter extends AbstractAdapter {

    /**
     * Retrieve the user's data
     *
     * The array needs to contain at least 'user', 'mail', 'name' and optional 'grps'
     *
     * @return array
     */
    public function getUser() {
        $http = new \DokuHTTPClient();
        $data = array();

        /** var OAuth\OAuth2\Service\Generic $this->oAuth */
        $result = json_decode($this->oAuth->request('/oauth/verify'), true);

        $data['user'] = $result['CharacterName'];
        $data['name'] = $result['CharacterName'];
        $data['mail'] = $result['CharacterID'] . '@eveonline.com';

        if (!isset($result['CharacterID'])) {
            return $data;
        }

        //Get character corporation, alliance, and faction
        $affiliation_post = $http->post('https://esi.evetech.net/latest/characters/affiliation/?datasource=tranquility', '[' . $result['CharacterID'] . ']');
        if ($affiliation_post === false) {
            return $data;
        }
        $affiliation_result = json_decode($affiliation_post, true);

        $ids = array();
        foreach ($affiliation_result as $entry) {
            if (isset($entry['alliance_id'])) {
                $ids[] = $entry['alliance_id'];
            }
            if (isset($entry['faction_id'])) {
                $ids[] = $entry['faction_id'];
            }
            $ids[] = $entry['corporation_id'];
        }

        $names_post = $http->post('https://esi.evetech.net/latest/universe/names/?datasource=tranquility', '[' . implode(",", $ids) . ']');
        if ($names_post === false) {
            return $data;
        }
        $names_result = json_decode($names_post, true);

        foreach ($names_result as $entry) {
            $name = strtolower(str_replace(" ", "_", str_replace(".", "-", $entry['name'])));
            $category = $entry['category'];
            if ($category == 'corporation') {
                $data['grps'][] = 'eve-corp-' . $name;
            } elseif ($category == 'alliance') {
                $data['grps'][] = 'eve-alliance-' . $name;
            } elseif ($category == 'faction') {
                $data['grps'][] = 'eve-faction-' . $name;
            }
        }

        return $data;
    }

    public function getScope() {
        return array(EveOnline::SCOPE_PUBLIC_DATA);
    }

    public function login() {
        $parameters = array();
        $parameters['state'] = urlencode(base64_encode(json_encode(array('state' => md5(rand())))));
        $this->storage->storeAuthorizationState($this->oAuth->service(), $parameters['state']);
        $url = $this->oAuth->getAuthorizationUri($parameters);
        send_redirect($url);
    }
}