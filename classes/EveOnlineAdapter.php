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
        $JSON = new \JSON(JSON_LOOSE_TYPE);
        $http = new \DokuHTTPClient();
        $data = array();

        /** var OAuth\OAuth2\Service\Generic $this->oAuth */
        $result = $JSON->decode($this->oAuth->request('/oauth/verify'));

        $data['user'] = $result['CharacterName'];
        $data['name'] = $result['CharacterName'];
        $data['mail'] = $result['CharacterID'] . '@eveonline.com';

        if (!isset($result['CharacterID'])) {
            return $data;
        }

        $post = $http->post('https://esi.evetech.net/latest/characters/affiliation/?datasource=tranquility', '[' . $result['CharacterID'] . ']');
        if ($post === false) {
            return $data;
        }
        $result = $JSON->decode($post);

        $ids = array();
        foreach ($result as $entry) {
            if (isset($entry['alliance_id'])) {
                $ids[] = $entry['alliance_id'];
            }
            if (isset($entry['faction_id'])) {
                $ids[] = $entry['faction_id'];
            }
            $ids[] = $entry['corporation_id'];
        }

        $post = $http->post('https://esi.evetech.net/latest/universe/names/?datasource=tranquility', '[' . implode(",", $ids) . ']');
        if ($post === false) {
            return $data;
        }
        $result = $JSON->decode($post);

        foreach ($result as $entry) {
            $name = strtolower(str_replace(" ", "_", $entry['name']));
            $name = str_replace(".", "-", $name);
            $category = $entry['category'];
            if ($category == 'corporation') {
                $data['grps'][] = 'eve-corp-' . $name;
            } elseif ($category == 'alliance') {
                $data['grps'][] = 'eve-alliance-' . $name;
            } elseif ($category == 'corporation') {
                $data['grps'][] = 'evefaction-' . $name;
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