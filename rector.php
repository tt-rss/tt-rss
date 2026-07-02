<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
	->withPhpVersion(Rector\ValueObject\PhpVersion::PHP_82)
	->withPhpSets(php82: true)
	->withCache(__DIR__ . '/.rector-tmp', FileCacheStorage::class)
	->withPaths([__DIR__])
	->withSkip([
		__DIR__ . '/lib',
		__DIR__ . '/plugins.local',
		__DIR__ . '/vendor',
		// not a fan
		Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector::class,
		// noisy at the moment (needs more review)
		Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector::class,
	]);
