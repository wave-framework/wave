<?php

namespace Wave;

use Wave\Http\Request;

interface IAuthable {

    public static function loadByIdentifier(array $params);

    public function hasAccess(array $level, Request $request);

    public static function noAuthAction(array $data);

    public function getAuthKey();

    public function getCSRFKey();

    public function confirmCSRFKey($key);

}


?>