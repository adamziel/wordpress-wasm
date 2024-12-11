<?php

declare(strict_types=1);

namespace Rowbot\URL\String;

class EncodeSet
{
    public const C0_CONTROL = 'c0_control';
    public const FRAGMENT = 'fragment';
    public const QUERY = 'query';
    public const SPECIAL_QUERY = 'special_query';
    public const PATH = 'path';
    public const USERINFO = 'userinfo';
    public const COMPONENT = 'component';
    public const X_WWW_URLENCODED = 'x_www_urlencoded';
}
