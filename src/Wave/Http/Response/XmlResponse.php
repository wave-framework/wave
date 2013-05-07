<?php

namespace Wave\Http\Response;

use Wave\Http\Request;
use Wave\Http\Response;

class XmlResponse extends Response {


    public function prepare(Request $request){
        parent::prepare($request);

        $this->headers->set('Cache-Control', 'no-cache, must-revalidate');
        $this->headers->set('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        $this->headers->set('Content-Type', 'text/xml; charset=utf-8');
        $this->headers->set('X-Wave-Response', 'xml');

        return $this;
    }

}