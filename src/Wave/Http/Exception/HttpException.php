<?php


namespace Wave\Http\Exception;

use Wave\Exception;
use Wave\Http\Request;
use Wave\Http\Response;

class HttpException extends Exception {

    protected $request;
    protected $response;


    public function __construct($message, Request $request = null, Response $response = null){

        $this->request = $request;
        $this->response = $response;

        parent::__construct($message, $this->getStatusCode());

    }

    protected function getStatusCode(){
        return Response::STATUS_SERVER_ERROR;
    }

}