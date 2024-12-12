<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\DowngradeLevelSetList;

return RectorConfig::configure()
	->withPaths(
		array(
			__DIR__ . '/vendor/rowbot/url',
		)
	)
	->withDowngradeSets(
		false,
		false,
		false,
		false,
		false,
		true
	)
	->withTypeCoverageLevel( 0 );
