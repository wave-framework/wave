<?php

namespace Wave\Http\Response;

use Wave\Http\Request;
use Wave\Http\Response;

class HtmlResponse extends Response {


    public function prepare(Request $request){
        parent::prepare($request);

        $this->headers->set('Content-Type', 'text/html; charset=utf8');
        $this->headers->set('X-Wave-Response', 'html');

        return $this;
    }

}