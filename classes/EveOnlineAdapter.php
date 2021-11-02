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

        //Get access_token
        $access_token = $this->oAuth->getStorage()->retrieveAccessToken($this->oAuth->service())->getAccessToken();

        //Split JWT into 3 parts > Take the 2nd part (payload) > base64 decode it > json decode it
        $jwt_payload = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $access_token)[1]))));

        //Get character name and id
        $character_name=$jwt_payload->name; //Character Name
        $character_id=explode(":",$jwt_payload->sub)[2]; //Charater ID (remove the extra stuff)

        if (!isset($character_id)) {
            return $data;
        }

        //Set character name and id
        $data['user'] = $character_name;
        $data['name'] = $character_name;
        $data['mail'] = $character_id . '@eveonline.com';

        //Get character corporation, alliance, and faction
        $affiliation_post = $http->post('https://esi.evetech.net/latest/characters/affiliation/?datasource=tranquility', '[' . $character_id . ']');
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

        //Convert ids to names
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