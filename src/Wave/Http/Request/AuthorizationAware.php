<?php


namespace Wave\Http\Request;

use Wave\Http\Request;

interface AuthorizationAware
{

    /**
     * Evaluate this object for the required authorization level needed by a controller function.
     *
     * @param array $levels the required access levels from the route annotation
     * @param Request $request the current request object
     * @return bool if the current user has authorization or not
     * @throws \Wave\Http\Exception\ForbiddenException|\Wave\Http\Exception\UnauthorizedException
     */
    public function hasAuthorization(array $levels, Request $request);

}