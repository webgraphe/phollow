<?php

namespace Webgraphe\Phollow\Contracts;

use Webgraphe\Phollow\Document;

interface OnNewDocumentContract
{
    public function __invoke(Document $document);
}
