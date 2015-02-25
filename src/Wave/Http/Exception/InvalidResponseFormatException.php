<?php

namespace Wave\Http\Exception;

use Wave\Http\Response;

class InvalidResponseFormatException extends HttpException {

    protected function getStatusCode() {
        return Response::STATUS_NOT_ACCEPTABLE;
    }

}