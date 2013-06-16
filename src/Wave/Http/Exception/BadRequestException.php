<?php


namespace Wave\Http\Exception;

use Wave\Http\Response;

class BadRequestException extends HttpException {

    protected function getStatusCode(){
        return Response::STATUS_BAD_REQUEST;
    }

}