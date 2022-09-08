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
        $this->updateGroups();
        $this->startOAuthLogin();
    }

    public function updateGroups() {
        global $AUTH_ACL, $ID;
        $showMessage = false;
        $updateACL = false;
        foreach ($AUTH_ACL as $index => $value) { //Search
            $acl = preg_split("/\s/", $value);
            if ($this->startsWith($acl[1], "@eve%2d")) {
                $showMessage = true;
                if ($acl[2] != 0) {
                    $updateACL = true;
                }
            }
        }
        if ($updateACL) {
            $apa = plugin_load('admin', 'acl');
            foreach ($AUTH_ACL as $index => $value) {
                $acl = preg_split("/\s/", $value);
                if ($this->startsWith($acl[1], "@eve%2d") && $acl[2] != 0) {
                    if (method_exists($apa, "addOrUpdateACL")) {
                        $apa->addOrUpdateACL($acl[0], rawurldecode($acl[1]), 0); //Scope, User/Group
                    } else { //Greebo and bellow
                        $apa->_acl_del($acl[0], rawurldecode($acl[1])); // first make sure we won't end up with 2 lines matching this user and scope.
                        $apa->_acl_add($acl[0], rawurldecode($acl[1]), 0); //Scope, User/Group
                    }
                    $AUTH_ACL[$index] = $acl[0] . ' ' . $acl[1] . ' ' . '0';
                }
            }
        }
        if ($showMessage) {
            msg('<b>EVESSO:</b><br/>'
                    . 'The naming of eve groups was changed in this version of EVESSO.<br />'
                    . 'Access for all the deprecated groups have been set to <code>None</code>.<br />'
                    . 'See the <a href="https://github.com/GoldenGnu/dokuwiki-plugin-evesso#updating">readme</a> for the details on how the naming have changed.<br />'
                    . 'Update your <a href="' . wl($ID, array('do' => 'admin', 'page' => 'acl'), true, '&') .  '">ACL</a> settings to restore access.<br />'
                    . 'This message will remain until the deprecated groups are removed from ACL.<br />'
                    , 2, '', '', MSG_ADMINS_ONLY);
        }
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

        // add all enabled services as group, remove all disabled services
        if(isset($enabled['EveOnline'])) { //Add EveOnline
            $groups[] = 'eveonline';
        } else { //Remove EveOnline
            $idx = array_search('eveonline', $groups);
            if($idx !== false) unset($groups[$idx]);
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
            //Disable fullname field
            $pos = $form->findPositionByAttribute('name', 'fullname');
            $form->getElementAt($pos)->attr('disabled', 'disabled');
            //Remove all fields except username and fullname
            $start = $form->findPositionByAttribute('name', 'fullname') + 1;
            $done = 0;
            while ($form->elementCount() > $start && $done < 11) {
                $form->removeElement($start);
                $done++;
            }
            $pos = $form->elementCount();
            //Add corporation, alliance, faction fields
            foreach ($USERINFO['grps'] as $group) {
                if ($this->startsWith($group, helper_plugin_evesso::CORPORATION_PREFIX)) {
                    $corp = $this->replaceFirst($group, helper_plugin_evesso::CORPORATION_PREFIX);
                }
                if ($this->startsWith($group, helper_plugin_evesso::ALLIANCE_PREFIX)) {
                    $alliance = $this->replaceFirst($group, helper_plugin_evesso::ALLIANCE_PREFIX);
                }
                if ($this->startsWith($group, helper_plugin_evesso::FACTION_PREFIX)) {
                    $faction = $this->replaceFirst($group, helper_plugin_evesso::FACTION_PREFIX);
                }
            }
            if (isset($faction)) { //str_starts_with
                $this->insertTextInput($form, $pos, $faction, $this->getLang('faction'));
            }
            if (isset($alliance)) { //str_starts_with
                $this->insertTextInput($form, $pos, $alliance, $this->getLang('alliance'));
            }
            if (isset($corp)) { //str_starts_with
                $this->insertTextInput($form, $pos, $corp, $this->getLang('corporation'));
            }
        } else { //Hogfather and earlier
            //Remove all fields except username and fullname
            array_splice($form->_content, 3);
            //Disable fullname field
            $form->getElementAt(3)['disabled'] = 'disabled';
            //Add corporation, alliance, faction fields
            $pos = count($form->_content);
            foreach ($USERINFO['grps'] as $group) {
                if ($this->startsWith($group, helper_plugin_evesso::CORPORATION_PREFIX)) {
                    $corp = $this->replaceFirst($group, helper_plugin_evesso::CORPORATION_PREFIX);
                }
                if ($this->startsWith($group, helper_plugin_evesso::ALLIANCE_PREFIX)) {
                    $alliance = $this->replaceFirst($group, helper_plugin_evesso::ALLIANCE_PREFIX);
                }
                if ($this->startsWith($group, helper_plugin_evesso::FACTION_PREFIX)) {
                    $faction = $this->replaceFirst($group, helper_plugin_evesso::FACTION_PREFIX);
                }
            }
            $form->insertElement($pos, form_closefieldset());
            if (isset($faction)) { //str_starts_with
                $form->insertElement($pos, form_makeTextField($faction, $faction, $this->getLang('faction'), '', 'block', array('disabled' => 'disabled', 'size' => '50')));
            }
            if (isset($alliance)) { //str_starts_with
                $form->insertElement($pos, form_makeTextField($alliance, $alliance, $this->getLang('alliance'), '', 'block', array('disabled' => 'disabled', 'size' => '50')));
            }
            if (isset($corp)) { //str_starts_with
                $form->insertElement($pos, form_makeTextField($corp, $corp, $this->getLang('corporation'), '', 'block', array('disabled' => 'disabled', 'size' => '50')));
            }
        }
    }

    private function startsWith($haystack, $needle) {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }
    
    private function replaceFirst($haystack, $needle, $replace = '') {
        $pos = strpos($haystack, $needle);
        if ($pos !== false) {
            $haystack = substr_replace($haystack, $replace, $pos, strlen($needle));
        }
        return $haystack;
    }

    private function insertTextInput($form, $pos, $value, $name) {
        $textInput = $form->addTextInput($value, $name, $pos);
        $textInput->attr('size', '50');
        $textInput->attr('class', 'edit');
        $textInput->attr('value', $value);
        $textInput->attr('disabled', 'disabled');
        $label = $textInput->getLabel();
        $label->attr('class', 'block');
        $form->addHTML('<br>', $pos);
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
        /** @var helper_plugin_evesso $hlp */
        $hlp = plugin_load('helper', 'evesso');
        $service = $hlp->getService();

        /** @var Doku_Form $form */
        $form =& $event->data;
        $html = '';

        if ($hlp->isEveAuth()) {  //Set Html
            if(is_a($form, \dokuwiki\Form\Form::class)) { //Igor and later
                while ($form->elementCount() > 0) {
                    $form->removeElement(0);
                }
                $pos  = $form->elementCount() - 3; //At the end
            } else { //Hogfather and earlier
                $form->_content = array();
                $pos  =  0;
            }
            $html = $this->service_html($service);
        }else{ //PlainAuth and EveAuth
            $html .= $this->service_html($service);
            if(is_a($form, \dokuwiki\Form\Form::class)) { //Igor and later
                $pos  = $form->elementCount(); //At the end
           } else { //Hogfather and earlier
                $pos  =  count($form->_content);
           }
        }
        if(is_a($form, \dokuwiki\Form\Form::class)) { //Igor and later
            $form->addFieldsetOpen($this->getLang('loginwith'), ++$pos);
            $form->addHtml($html, ++$pos);
            $form->addFieldsetClose();
        } else { //Hogfather and earlier
            $form->insertElement(++$pos, form_openfieldset(array('_legend' => $this->getLang('loginwith'), 'class' => 'plugin_evesso')));
            $form->insertElement(++$pos, $html);
            $form->insertElement(++$pos, form_closefieldset());
        }
    }

    function service_html($service) {
        global $ID;
        $html = '';
        $html .= '<a href="' . wl($ID, array('evessologin' => $service)) . '" class="plugin_evesso_' . $service . '">';
        if ($this->getConf('login-button') == 'Button') {
            $html .= '<div class="eve-sso-login-white-large"></div>';
        } elseif ($this->getConf('login-button') == 'LargeLight') {
            $html .= '<div class="eve-sso-login-white-large"></div>';
        } elseif ($this->getConf('login-button') == 'LargeDark') {
            $html .= '<div class="eve-sso-login-black-large"></div>';
        } elseif ($this->getConf('login-button') == 'SmallLight') {
            $html .= '<div class="eve-sso-login-white-small"></div>';
        } elseif ($this->getConf('login-button') == 'SmallDark') {
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

        $hlp = plugin_load('helper', 'evesso');
        
        if($event->data == 'logout' && $hlp->isEveAuth()) {
            session_start();
            $_SESSION[DOKU_COOKIE]['oauth-logout'] = 'logout';
            session_write_close();
        }

        if ($hlp->isAuthPlain()) return true;

        if($event->data != 'login') return true;

        if($hlp->isEveAuthDirect()) {
            $lang['btn_login'] = $this->getLang('loginButton');
            $url = wl($ID, array('evessologin' => $hlp->getService()), true, '&');
            send_redirect($url);
        }
    }

}
// vim:ts=4:sw=4:et: