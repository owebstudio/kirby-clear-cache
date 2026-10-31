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
    'translations' => [
        'de' => require __DIR__ . '/translations/de.php',
        'en' => require __DIR__ . '/translations/en.php',
        'tr' => require __DIR__ . '/translations/tr.php'
    ],
    'areas' => [
        'clear-cache' => function () {
            return [
                'label' => t('owebstudio.clear-cache.title'),
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
                                'title' => t('owebstudio.clear-cache.title'),
                                'props' => ClearCache::view(),
                            ];
                        }
                    ]
                ],
                'buttons' => [
                    'clear-cache' => function () {
                        if (ClearCache::canClear() === false) {
                            return null;
                        }

                        return [
                            'icon' => 'trash',
                            'text' => t('owebstudio.clear-cache.title'),
                            'options' => 'clear-cache'
                        ];
                    }
                ],
                'dropdowns' => [
                    'clear-cache' => function () {
                        return ClearCache::dropdown();
                    }
                ],
                'dialogs' => [
                    'clear-cache.type' => [
                        'pattern' => 'clear-cache/(:any)',
                        'load' => function (string $type) {
                            return ClearCache::dialog($type);
                        },
                        'submit' => function (string $type) {
                            return ClearCache::submit($type);
                        }
                    ],
                    'clear-cache.dir' => [
                        'pattern' => 'clear-cache/(:any)/(:any)',
                        'load' => function (string $type, string $dir) {
                            return ClearCache::dialog($type, $dir);
                        },
                        'submit' => function (string $type, string $dir) {
                            return ClearCache::submit($type, $dir);
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

                    if (empty($type) === true || is_string($type) === false) {
                        throw new Exception('Invalid request!');
                    }

                    if (empty($dir) === true) {
                        ClearCache::clearType($type);
                    } elseif (is_string($dir) === true) {
                        ClearCache::clearDir($type, $dir);
                    } else {
                        throw new Exception('Invalid directory!');
                    }

                    return true;
                }
            ],
            [
                'pattern' => 'clear-cache/stats',
                'method' => 'GET',
                'action' => function () {
                    if (ClearCache::canClear() === false) {
                        throw new PermissionException();
                    }

                    return ClearCache::stats();
                }
            ]
        ]
    ],
    'commands' => [
        'clear-cache' => [
            'description' => 'Lists or clears caches, media files, old lock files and failed login attempts',
            'args' => [
                'type' => [
                    'description' => 'cache, media or other (lists everything when empty)'
                ],
                'name' => [
                    'description' => 'A single entry, e.g. pages (clears all entries of the type when empty)'
                ]
            ],
            'command' => function ($cli): void {
                ClearCache::command($cli);
            }
        ]
    ]
]);
