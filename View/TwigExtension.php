<?php

namespace Wave\View;

class TwigExtension extends \Twig_Extension {


	public function getTokenParsers(){
		return array(
			new Tag\Register(),
			new Tag\Output(),
			new Tag\Img()
		);
	}
	
	public function getName(){
		return 'wave_view';
	}

}


?>