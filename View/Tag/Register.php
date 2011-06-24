<?php



class Wave_View_Tag_Register extends Twig_TokenParser {

	const TYPE_JS 	= 'js';
	const TYPE_CSS	= 'css';

	public function parse(Twig_Token $token) {
		$lineno = $token->getLine();
		
		$extras = '';
		
		$type = $this->parser->getStream()->expect(Twig_Token::NAME_TYPE)->getValue();
		
		if(!in_array($type, array(self::TYPE_JS, self::TYPE_CSS)))
			throw new Twig_SyntaxError("Register type must be 'css' or 'js'.", $lineno, $token->getFilename());
		
		$file = $this->parser->getStream()->expect(Twig_Token::STRING_TYPE)->getValue();
		
		if($type == self::TYPE_CSS){
			if($this->parser->getStream()->test(Twig_Token::STRING_TYPE))
				$extras = $this->parser->getStream()->expect(Twig_Token::STRING_TYPE)->getValue();
			else
				$extras = 'screen, print';
		}
		else if($type == self::TYPE_JS){
			if($this->parser->getStream()->test(Twig_Token::NUMBER_TYPE))
				$extras = $this->parser->getStream()->expect(Twig_Token::NUMBER_TYPE)->getValue();
			else
				$extras = 0;
		}
		
		$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
		
		return new Wave_View_Tag_Register_Node($type, $file, $extras, $lineno, $this->getTag());
	}
	
	public function getTag(){
		return 'register';
	}
	

}

class Wave_View_Tag_Register_Node extends Twig_Node {
		
	public function __construct($type, $file, $extras, $line, $tag = null){
		parent::__construct(array(), array('type' => $type, 'file' => $file, 'extras' => $extras), $line, $tag);
	}
	
	public function compile(Twig_Compiler $compiler){
		
		$type = $this->getAttribute('type');
		$file = $this->getAttribute('file');
		$extras = $this->getAttribute('extras');
		
		if(!preg_match('/http(s)?\:\/\//', $file))
			$file = Wave_Config::get('deploy')->assets . $file;
				
		$compiler
			->addDebugInfo($this)
			->write('$this->env->_wave_register("'.$type.'", "'.$file.'", "'.$extras.'");')
			->raw("\n");
	}
	
}


?>