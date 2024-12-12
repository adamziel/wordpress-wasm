<?php

declare(strict_types=1);

namespace Rowbot\URL;

final class APIParserResult
{
    /**
     * @readonly
     * @var \Rowbot\URL\URLRecord|null
     */
    public $url;

    /**
     * @readonly
     * @var \Rowbot\URL\APIParserErrorType
     */
    public $error;

    /**
     * @param \Rowbot\URL\APIParserErrorType::* $error
     */
    public function __construct(?URLRecord $urlRecord, $error)
    {
        $this->url = $urlRecord;
        $this->error = $error;
    }
}
