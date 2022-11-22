<?php

namespace Wave\Http\Response;

use Wave\Http\Request;
use Wave\Http\Response;

class RedirectResponse extends Response
{

    private $target;

    public function __construct($url, $status = self::STATUS_FOUND, array $headers = array())
    {
        parent::__construct('', $status, $headers);

        $this->setTarget($url);
    }

    public function getTarget()
    {
        return $this->target;
    }

    public function setTarget($target)
    {
        $this->target = $target;

        $this->setContent(
            sprintf(
                '<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta http-equiv="refresh" content="1;url=%1$s" />

        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($target, ENT_QUOTES, 'UTF-8')
            )
        );

        $this->headers->set('Location', $target);
    }


}