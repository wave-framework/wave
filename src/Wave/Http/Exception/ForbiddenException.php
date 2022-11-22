<?php

namespace Wave\Http\Exception;

use Wave\Http\Response;

class ForbiddenException extends HttpException
{


    protected function getStatusCode()
    {
        return Response::STATUS_FORBIDDEN;
    }
}