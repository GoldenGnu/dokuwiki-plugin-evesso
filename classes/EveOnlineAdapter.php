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
        $data = array();

        /** var OAuth\OAuth2\Service\Generic $this->oAuth */
        $result = $JSON->decode($this->oAuth->request('/oauth/verify'));

        $data['user'] = $result['CharacterName'];
        $data['name'] = $result['CharacterName'];
        $data['mail'] = $result['CharacterID'].'@eveonline.com';
        return $data;
    }

	public function getScope() {
        return array(EveOnline::SCOPE_PUBLIC_DATA);
    }

	public function login() {
		$parameters = array();
		$parameters['state'] = urlencode(base64_encode(json_encode(array('state' => md5(rand())))));
		$this->storage->storeAuthorizationState('EveOnline', $parameters['state']);
		$url = $this->oAuth->getAuthorizationUri($parameters);
		send_redirect($url);
	}
}