<?php



class Wave_View_Tag_Output extends Twig_TokenParser {

	public function parse(Twig_Token $token) {
		$lineno = $token->getLine();	
		
		
		$type = $this->parser->getStream()->expect(Twig_Token::NAME_TYPE)->getValue();
		
		
		$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
		
		return new Wave_View_Tag_Output_Node($type, $lineno, $this->getTag());
	}
	
	public function getTag(){
		return 'output';
	}
	

}

class Wave_View_Tag_Output_Node extends Twig_Node {
	
	
	const FORMAT_JS 	= '<script type=\"text/javascript\" src=\"%s\"></script>';
	const FORMAT_CSS	= '<link href=\"%s\" rel=\"stylesheet\" media=\"%s\" />';
	
	public function __construct($type, $line, $tag = null){
		parent::__construct(array(), array('type' => $type), $line, $tag);
	}
	
	public function compile(Twig_Compiler $compiler){
		
		$compiler
			->addDebugInfo($this);
		
		$type = $this->getAttribute('type');
					
		if($type == Wave_View_Tag_Register::TYPE_JS){
			$template = self::FORMAT_JS;
			
			$compiler->write('foreach($this->env->_wave_register["'.$type.'"] as $file => $extra):')->raw("\n\t")
				->write('$code = sprintf("'.$template.'", $file);')->raw("\n\t")
					->write('echo $code . "<!-- $extra -->\n";')->raw("\n")
				->write('endforeach;')->raw("\n");
		}
		else if($type == Wave_View_Tag_Register::TYPE_CSS){
			$template = self::FORMAT_CSS;
			
			$compiler->write('foreach($this->env->_wave_register["'.$type.'"] as $file => $extra):')->raw("\n\t")
				->write('$code = sprintf("'.$template.'", $file, $extra);')->raw("\n\t")
					->write('echo $code . "\n";')->raw("\n")
				->write('endforeach;')->raw("\n");
		}	
		
	}
	
}


?>