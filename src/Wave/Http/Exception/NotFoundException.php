<?php

namespace Wave\Http\Exception;

use Wave\Http\Response;

class NotFoundException extends HttpException
{

    protected function getStatusCode()
    {
        return Response::STATUS_NOT_FOUND;
    }
}