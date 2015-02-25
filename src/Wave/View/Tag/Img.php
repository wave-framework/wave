<?php

namespace Wave\View\Tag;

use Wave;

class Img extends \Twig_TokenParser {

    const TYPE_JS = 'js';
    const TYPE_CSS = 'css';

    public function parse(\Twig_Token $token) {
        $lineno = $token->getLine();

        $path = $this->parser->getStream()->expect(\Twig_Token::STRING_TYPE)->getValue();

        if(!preg_match('/http(s)?\:\/\//', $path))
            $path = Wave\Config::get('deploy')->assets . $path;

        $attributes = array();
        if($this->parser->getStream()->test(\Twig_Token::STRING_TYPE)) {
            $str = $this->parser->getStream()->expect(\Twig_Token::STRING_TYPE)->getValue();
            $attributes['title'] = $str;
            $attributes['alt'] = $str;
        }
        if(!$this->parser->getStream()->test(\Twig_Token::BLOCK_END_TYPE)) {
            $array = $this->parser->getExpressionParser()->parseArrayExpression();
            foreach($array->getIterator() as $key => $node) {
                $attributes[$key] = $node->getAttribute('value');
            }
        }

        $this->parser->getStream()->expect(\Twig_Token::BLOCK_END_TYPE);


        return new ImgNode($path, $attributes, $lineno, $this->getTag());
    }

    public function getTag() {
        return 'img';
    }


}

class ImgNode extends \Twig_Node {

    public function __construct($src, $attributes, $line, $tag = null) {
        parent::__construct(array(), array('src' => $src, 'attributes' => $attributes), $line, $tag);
    }

    public function compile(\Twig_Compiler $compiler) {

        $src = $this->getAttribute('src');
        $attributes = $this->getAttribute('attributes');

        if(!isset($attributes['width']) && !isset($attributes['height'])) {
            try {
                $img = getimagesize($src);

                $attributes['width'] = $img[0];
                $attributes['height'] = $img[1];
            } catch(\Exception $e) {
            }
        }

        $attributes['src'] = $src;

        $compiled = array();
        foreach($attributes as $key => $value)
            $compiled[] = $key . '="' . $value . '"';

        $compiler
            ->addDebugInfo($this)
            ->write('echo ')
            ->string('<img ' . implode(' ', $compiled) . ' />')
            ->raw(";\n");
    }

}


?>