<?php

namespace Wave\View\Tag;

use Twig\Compiler;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

class Output extends AbstractTokenParser
{

    public function parse(Token $token)
    {
        $lineno = $token->getLine();


        $type = $this->parser->getStream()->expect(Token::NAME_TYPE)->getValue();


        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        return new OutputNode($type, $lineno, $this->getTag());
    }

    public function getTag()
    {
        return 'output';
    }


}

class OutputNode extends Node
{


    const FORMAT_JS = '<script type=\"text/javascript\" src=\"%s\"></script>';
    const FORMAT_CSS = '<link href=\"%s\" rel=\"stylesheet\" media=\"%s\" />';

    public function __construct($type, $line, $tag = null)
    {
        parent::__construct(array(), array('type' => $type), $line, $tag);
    }

    public function compile(Compiler $compiler)
    {

        $compiler
            ->addDebugInfo($this);

        $type = $this->getAttribute('type');

        $template = $type == \Wave\View\Tag\Register::TYPE_JS ? self::FORMAT_JS : self::FORMAT_CSS;

        $compiler->write('foreach($this->env->_wave_register["' . $type . '"] as $priority => $files):')->raw("\n\t")
            ->write('foreach($files as $file => $extra):')->raw("\n\t")
            ->write('$code = sprintf("' . $template . '", $file, $extra);')->raw("\n\t")
            ->write('echo $code . "<!-- $priority -->\n";')->raw("\n\t")
            ->write('endforeach;')->raw("\n")
            ->write('endforeach;')->raw("\n");


    }

}


?>