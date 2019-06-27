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

    public function html(&$plugin, $echo = false) {
        /** @var helper_plugin_evesso $hlp */
        $hlp = plugin_load('helper', 'evesso');

        $key   = htmlspecialchars($this->_key);
        $value = '<code>'.$hlp->redirectURI().'</code>';

        $label = '<label for="config___'.$key.'">'.$this->prompt($plugin).'</label>';
        $input = '<div>'.$value.'</div>';
        return array($label, $input);
    }

}

$meta['info']                = array('plugin_evesso');
$meta['custom-redirectURI']  = array('string','_caution' => 'warning');
$meta['eveonline-key']       = array('string');
$meta['eveonline-secret']    = array('string');
$meta['mailRestriction']     = array('string','_pattern' => '!^(@[^,@]+(\.[^,@]+)+(,|$))*$!'); // https://regex101.com/r/mG4aL5/3
$meta['singleService']       = array('multichoice', '_caution' => 'danger',
                                     '_choices' => array(
                                         '',
                                         'EveOnline'));
$meta['register-on-auth']    = array('onoff','_caution' => 'security');
