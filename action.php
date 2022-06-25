<?php
/**
 * DokuWiki Plugin oauth (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_evesso extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        global $conf;
        if($conf['authtype'] != 'evesso') return;

        $conf['profileconfirm'] = false; // password confirmation doesn't work with oauth only users

        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'handle_start');
        $controller->register_hook('HTML_LOGINFORM_OUTPUT', 'BEFORE', $this, 'handle_loginform');
        $controller->register_hook('HTML_UPDATEPROFILEFORM_OUTPUT', 'BEFORE', $this, 'handle_profileform');
        $controller->register_hook('FORM_LOGIN_OUTPUT', 'BEFORE', $this, 'handle_loginform');
        $controller->register_hook('FORM_UPDATEPROFILE_OUTPUT', 'BEFORE', $this, 'handle_profileform');
        $controller->register_hook('AUTH_USER_CHANGE', 'BEFORE', $this, 'handle_usermod');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_dologin');
    }

    /**
     * Start an oAuth login or restore  environment after successful login
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_start(Doku_Event &$event, $param) {
        global $ID;
        if (isset($_SESSION[DOKU_COOKIE]['oauth-logout'])){
            unset($_SESSION[DOKU_COOKIE]['oauth-logout']);
            send_redirect(wl($ID));
            return;
        }
        if (isset($_SESSION[DOKU_COOKIE]['oauth-done']['do']) || !empty($_SESSION[DOKU_COOKIE]['oauth-done']['rev'])){
            $this->restoreSessionEnvironment();
            return;
        }

        $this->startOAuthLogin();
    }

    private function startOAuthLogin() {
        global $INPUT, $ID;

        /** @var helper_plugin_evesso $hlp */
        $hlp         = plugin_load('helper', 'evesso');
        $servicename = $INPUT->str('evessologin');
        $service     = $hlp->loadService($servicename);
        if(is_null($service)) return;

        // remember service in session
        session_start();
        $_SESSION[DOKU_COOKIE]['oauth-inprogress']['service'] = $servicename;
        $_SESSION[DOKU_COOKIE]['oauth-inprogress']['id']      = $ID;
        session_write_close();

        $service->login();
    }

    private function restoreSessionEnvironment() {
        global $INPUT, $ACT, $TEXT, $PRE, $SUF, $SUM, $RANGE, $DATE_AT, $REV;
        $ACT = $_SESSION[DOKU_COOKIE]['oauth-done']['do'];
        $_REQUEST = $_SESSION[DOKU_COOKIE]['oauth-done']['$_REQUEST'];

        $REV   = $INPUT->int('rev');
        $DATE_AT = $INPUT->str('at');
        $RANGE = $INPUT->str('range');
        if($INPUT->post->has('wikitext')) {
            $TEXT = cleanText($INPUT->post->str('wikitext'));
        }
        $PRE = cleanText(substr($INPUT->post->str('prefix'), 0, -1));
        $SUF = cleanText($INPUT->post->str('suffix'));
        $SUM = $INPUT->post->str('summary');

        unset($_SESSION[DOKU_COOKIE]['oauth-done']);
    }

    /**
     * Save groups for all the services a user has enabled
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_usermod(Doku_Event &$event, $param) {
        global $ACT;
        global $USERINFO;
        global $auth;
        global $INPUT;

        if($event->data['type'] != 'modify') return;
        if($ACT != 'profile') return;

        // we want to modify the user's groups
        $groups = $USERINFO['grps']; //current groups
        if(isset($event->data['params'][1]['grps'])) {
            // something already defined new groups
            $groups = $event->data['params'][1]['grps'];
        }

        /** @var helper_plugin_evesso $hlp */
        $hlp = plugin_load('helper', 'evesso');

        // get enabled and configured services
        $enabled  = $INPUT->arr('oauth_group');
        $services = $hlp->listServices();
        $services = array_map(array($auth, 'cleanGroup'), $services);

        // add all enabled services as group, remove all disabled services
        foreach($services as $service) {
            if(isset($enabled[$service])) {
                $groups[] = $service;
            } else {
                $idx = array_search($service, $groups);
                if($idx !== false) unset($groups[$idx]);
            }
        }
        $groups = array_unique($groups);

        // add new group array to event data
        $event->data['params'][1]['grps'] = $groups;

    }

    /**
     * Add service selection to user profile
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_profileform(Doku_Event &$event, $param) {
        global $USERINFO;
        /** @var auth_plugin_authplain $auth */
        global $auth;

        /** @var helper_plugin_evesso $hlp */
        $hlp = plugin_load('helper', 'evesso');
        
        /** @var Doku_Form $form */
        $form =& $event->data;
        if (!in_array('eveonline', $USERINFO['grps'])) {
            return; //Continue as normal
        }
        if(is_a($form, \dokuwiki\Form\Form::class)) { //Igor and later
            //Disable fields
            $disable = array('fullname');
            foreach ($disable as $dis) { //Disable
                $pos = $form->findPositionByAttribute('name', $dis);
                $form->getElementAt($pos)->attr('disabled', 'disabled');
                $form->removeElement(++$pos);
            }
            //Remove fields
            $remove = array('email', 'newpass', 'passchk');
            foreach ($remove as $dis) {
                $pos = $form->findPositionByAttribute('name', $dis);
                $form-> removeElement($pos);
                $form-> removeElement($pos++);
            }
            //Remove buttons
            $remove = array('submit', 'reset');
            foreach ($remove as $dis) {
                $pos = $form->findPositionByAttribute('type', $dis);
                $form-> removeElement($pos);
            }
        } else { //Hogfather and earlier
             array_splice($form->_content, 3); //Remove everything except Username and Real name
             $form->getElementAt(3)['disabled'] = 'disabled'; //Disable: Real name
        }
    }

    private function insertElement($form, $pos, $out) {
        if(is_a($form, \dokuwiki\Form\Form::class)) { //Igor and later
            $form->addHtml($out, $pos);
        } else { //Hogfather and earlier
            $form->insertElement($pos, $out);
        }
    }

    /**
     * Add the oAuth login links
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_loginform(Doku_Event &$event, $param) {
        global $conf;

        /** @var helper_plugin_evesso $hlp */
        $hlp = plugin_load('helper', 'evesso');
        $singleService = $this->getConf('singleService');
        $enabledServices = $hlp->listServices();

        /** @var Doku_Form $form */
        $form =& $event->data;
        $html = '';

        $validDomains = $hlp->getValidDomains();

        if (count($validDomains) > 0) {
            $html .= sprintf($this->getLang('eMailRestricted'), join(', ', $validDomains));
        }

        if ($singleService == '') {

            foreach($hlp->listServices() as $service) {
                $html .= $this->service_html($service);
            }
            if(!$html) return;

        }else{
            if (in_array($singleService, $enabledServices, true) === false) {
                msg($this->getLang('wrongConfig'),-1);
                return;
            }
            $form->_content = array(); //Ignore by Igor and later
            $html = $this->service_html($singleService);

        }
        if(is_a($form, \dokuwiki\Form\Form::class)) { //Igor and later
            $pos  = $form->elementCount(); //At the end
        } else { //Hogfather and earlier
            $pos  =  count($form->_content);
        }
        $this->insertElement($form, ++$pos, form_openfieldset(array('_legend' => $this->getLang('loginwith'), 'class' => 'plugin_evesso')));
        $this->insertElement($form, ++$pos, $html);
        $this->insertElement($form, ++$pos, form_closefieldset());
    }

    function service_html($service) {
        global $ID;
        $html = '';
        $html .= '<a href="' . wl($ID, array('evessologin' => $service)) . '" class="plugin_evesso_' . $service . '">';
        if ($this->getConf('login-button') == 'Button') {
            $html .= '<div class="eve-sso-login-white-large"></div>';
        } elseif ($this->getConf('login-button') == 'Large Light Button') {
            $html .= '<div class="eve-sso-login-white-large"></div>';
        } elseif ($this->getConf('login-button') == 'Large Dark Button') {
            $html .= '<div class="eve-sso-login-black-large"></div>';
        } elseif ($this->getConf('login-button') == 'Small Light Button') {
            $html .= '<div class="eve-sso-login-white-small"></div>';
        } elseif ($this->getConf('login-button') == 'Small Dark Button') {
            $html .= '<div class="eve-sso-login-black-small"></div>';
        } else {
            $html .= $this->getLang('loginButton');
        }
        $html .= '</a> ';
        return $html;
    }

    public function handle_dologin(Doku_Event &$event, $param) {
        global $lang;
        global $ID;

        $singleService = $this->getConf('singleService');

        if($event->data == 'logout' && $singleService != '') {
            session_start();
            $_SESSION[DOKU_COOKIE]['oauth-logout'] = 'logout';
            session_write_close();
        }

        if ($singleService == '') return true;

        $lang['btn_login'] = $this->getLang('loginButton');

        if($event->data != 'login') return true;



        /** @var helper_plugin_evesso $hlp */
        $hlp = plugin_load('helper', 'evesso');
        $enabledServices = $hlp->listServices();
        if (in_array($singleService, $enabledServices, true) === false) {
            msg($this->getLang('wrongConfig'),-1);
            return false;
        }

        $url = wl($ID, array('evessologin' => $singleService), true, '&');
        send_redirect($url);
    }

}
// vim:ts=4:sw=4:et:
