<?php

namespace Webgraphe\Phollow\Contracts;

interface ErrorCollectionContract extends \JsonSerializable
{
    /**
     * @return ErrorContract[]
     */
    public function getErrors();

    /**
     * @param int $id
     * @return ErrorContract|null
     */
    public function getError($id);
}
