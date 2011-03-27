<?php



class Wave_View_TwigExtension extends Twig_Extension {


	public function getTokenParsers(){
		return array(
			new Wave_View_Tag_Register(),
			new Wave_View_Tag_Output(),
			new Wave_View_Tag_Img()
		);
	}
	
	public function getName(){
		return 'wave_view';
	}

}


?>