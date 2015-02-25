<?php

namespace Wave\View;

class TwigEnvironment extends \Twig_Environment {

    public $_wave_register = array('css' => array(), 'js' => array());

    public function _wave_register($type, $path, $extras = null, $priority = 0, $cache_key = null) {
        if(is_string($cache_key)) {
            if(strpos($path, '?') !== false) $path .= '&';
            else $path .= '?';
            $path .= 'v=' . $cache_key;
        }

        $this->_wave_register[$type][$priority][$path] = $extras;

        krsort($this->_wave_register[$type], SORT_NUMERIC);
    }


}


?>