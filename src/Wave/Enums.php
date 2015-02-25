<?php

namespace Wave;

abstract class Method {

    const ANY = '*';
    const POST = 'POST';
    const GET = 'GET';
    const PUT = 'PUT';
    const CLI = 'CLI';
    const DELETE = 'DELETE';
    const UPDATE = 'UPDATE';
    const CREATE = 'CREATE';

    static $ALL = array(
        self::ANY, self::POST, self::GET,
        self::PUT, self::CLI, self::DELETE, self::UPDATE,
        self::CREATE
    );

}


?>