<?php

namespace Wave\DB;

use Wave;

class Exception extends Wave\Exception
{
    const SQLSTATE_DUPLICATE_KEY = 1062;
}