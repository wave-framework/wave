<?php

namespace Wave\Http\Exception;

use Wave\Http\Response;

class UnauthorizedException extends HttpException {

    protected function getStatusCode(){
        return Response::STATUS_UNAUTHORISED;
    }
}