<?php

use Oweb\ClearCache;
use Kirby\Cms\App as Kirby;
use Kirby\Exception\Exception;
use Kirby\Exception\PermissionException;

load([
    'oweb\\clearcache' => __DIR__ . '/lib/ClearCache.php'
]);

Kirby::plugin('owebstudio/clear-cache', [
    'permissions' => [
        'clear' => false
    ],
    'areas' => [
        'clear-cache' => function () {
            return [
                'label' => 'Clear cache',
                'icon' => 'trash',
                'menu' => function () {
                    return ClearCache::canClear();
                },
                'link' => 'clear-cache',
                'views' => [
                    [
                        'pattern' => 'clear-cache',
                        'action' => function () {
                            if (ClearCache::canClear() === false) {
                                throw new PermissionException();
                            }

                            return [
                                'component' => 'clearcache',
                                'title' => 'Clear cache',
                                'props' => ClearCache::props(),
                            ];
                        }
                    ]
                ]
            ];
        }
    ],
    'api' => [
        'routes' => [
            [
                'pattern' => 'clear-cache',
                'method' => 'POST',
                'action' => function () {
                    if (ClearCache::canClear() === false) {
                        throw new PermissionException();
                    }

                    $type = get('type');
                    $dir = get('dir');

                    if (empty($type) === true) {
                        throw new Exception('Invalid request!');
                    }

                    if (empty($dir) === true) {
                        ClearCache::clearType($type);
                    } else {
                        ClearCache::clearDir($type, $dir);
                    }

                    return true;
                }
            ]
        ]
    ]
]);
