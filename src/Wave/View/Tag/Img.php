<?php

namespace Wave\View\Tag;

use Twig\Compiler;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Wave;

class Img extends AbstractTokenParser {

    const TYPE_JS = 'js';
    const TYPE_CSS = 'css';

    public function parse(Token $token) {
        $lineno = $token->getLine();

        $path = $this->parser->getStream()->expect(Token::STRING_TYPE)->getValue();

        if(!preg_match('/http(s)?\:\/\//', $path))
            $path = Wave\Config::get('deploy')->assets . $path;

        $attributes = array();
        if($this->parser->getStream()->test(Token::STRING_TYPE)) {
            $str = $this->parser->getStream()->expect(Token::STRING_TYPE)->getValue();
            $attributes['title'] = $str;
            $attributes['alt'] = $str;
        }
        if(!$this->parser->getStream()->test(Token::BLOCK_END_TYPE)) {
            $array = $this->parser->getExpressionParser()->parseArrayExpression();
            foreach($array->getIterator() as $key => $node) {
                $attributes[$key] = $node->getAttribute('value');
            }
        }

        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);


        return new ImgNode($path, $attributes, $lineno, $this->getTag());
    }

    public function getTag() {
        return 'img';
    }


}

class ImgNode extends Node {

    public function __construct($src, $attributes, $line, $tag = null) {
        parent::__construct(array(), array('src' => $src, 'attributes' => $attributes), $line, $tag);
    }

    public function compile(Compiler $compiler) {

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