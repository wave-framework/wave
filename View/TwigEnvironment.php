<?php



class Wave_View_TwigEnvironment extends Twig_Environment {
		
	public $_wave_register = array('css' => array(), 'js' => array());
	
	public function _wave_register($type, $path, $extras = null){
		$this->_wave_register[$type][$path] = $extras;
		
		if($type == Wave_View_Tag_Register::TYPE_JS)
			arsort($this->_wave_register[$type], SORT_NUMERIC);
	}


}


?>