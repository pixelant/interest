<?php

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'ext-interest-mapping' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:interest/Resources/Public/Icons/RemoteIdMapping.svg',
    ],
    'ext-interest-mapping-manual' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:interest/Resources/Public/Icons/ManualRemoteIdMapping.svg',
    ],
];
