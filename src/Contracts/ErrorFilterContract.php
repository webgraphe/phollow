<?php

namespace Webgraphe\Phollow\Contracts;

use Webgraphe\Phollow\Documents\Error;

interface ErrorFilterContract
{
    /**
     * @param Error $error
     * @return bool
     */
    public function __invoke(Error $error);
}
