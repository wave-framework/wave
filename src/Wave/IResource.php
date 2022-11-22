<?php


interface Wave_IResource
{

    const RESOURCE_SHOW = 'show';
    const RESOURCE_CREATE = 'create';
    const RESOURCE_GET = 'get';
    const RESOURCE_UPDATE = 'update';
    const RESOURCE_DELETE = 'delete';


    /**
     *    ~Route GET /entity
     **/
    public function show();

    /**
     *    ~Route POST /entity
     **/
    public function create();

    /**
     *    ~Route GET /entity/<id>@int
     **/
    public function get();

    /**
     *    ~Route POST /entity/<id>@int
     **/
    public function update();

    /**
     *    ~Route POST /entity/<id>@int/delete
     **/
    public function delete();

}


?>