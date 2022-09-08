<?php
/**
 * Options for the evesso plugin
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */

class setting_plugin_evesso extends setting {

    function update($input) {
        return true;
    }

    public function html(\admin_plugin_config $plugin, $echo = false) {
        /** @var helper_plugin_evesso $hlp */
        $hlp = plugin_load('helper', 'evesso');

        $key   = htmlspecialchars($this->_key);
        $value = '<code>'.$hlp->getRedirectURI().'</code>';

        $label = '<label for="config___'.$key.'">'.$this->prompt($plugin).'</label>';
        $input = '<div>'.$value.'</div>';
        return array($label, $input);
    }

}

class plugin_evesso extends setting {

    function update($input) {
        return true;
    }

    public function html(\admin_plugin_config $plugin, $echo = false) {
        /** @var helper_plugin_evesso $hlp */
        $hlp = plugin_load('helper', 'evesso');

        $key   = htmlspecialchars($this->_key);
        $value = '<code>'.$hlp->getRedirectURI().'</code>';

        $label = '<label for="config___'.$key.'">'.$this->prompt($plugin).'</label>';
        $input = '<div>'.$value.'</div>';
        return array($label, $input);
    }

}

$meta['info']                = array('plugin_evesso');
$meta['custom-redirectURI']  = array('string','_caution' => 'danger');
$meta['eveonline-key']       = array('string');
$meta['eveonline-secret']    = array('string');
$meta['singleService']       = array('multichoice',
                                    '_caution' => 'danger',
                                    '_other' => 'never',
                                    '_choices' => array(
                                        '',
                                        'EveOnlinePage',
                                        'EveOnline',
                                        )
                                    );
$meta['register-on-auth']    = array('onoff','_caution' => 'security');
$meta['require-corporation']   = array('string', '_caution' => 'security');
$meta['require-alliance']      = array('string', '_caution' => 'security');
$meta['require-faction']       = array('string', '_caution' => 'security');
$meta['login-button']        = array('multichoice',
                                    '_choices' => array(
                                        'Text',
                                        'LargeLight',
                                        'LargeDark',
                                        'SmallLight',
                                        'SmallDark'
                                        ));
