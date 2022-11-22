<?php

namespace Wave\Http\Response;

use Wave\Http\Request;
use Wave\Http\Response;
use Wave\Utils\XML;

class XmlResponse extends Response
{

    private $data;

    public function prepare(Request $request)
    {
        parent::prepare($request);

        $this->headers->set('Cache-Control', 'no-cache, must-revalidate');
        $this->headers->set('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        $this->headers->set('Content-Type', 'text/xml; charset=utf-8');
        $this->headers->set('X-Wave-Response', 'xml');

        return $this;
    }

    public function setContent($data, $convert = true)
    {
        if ($convert) {
            $this->data = $data;
            $data = XML::encode($data);
        }

        parent::setContent($data);
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

}