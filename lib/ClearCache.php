<?php

namespace Oweb;

use Kirby\Cache\FileCache;
use Kirby\Cache\NullCache;
use Kirby\Exception\Exception;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use ReflectionClass;
use Throwable;

class ClearCache
{
    /**
     * @var array
     */
    public static $props;

    public static function relativePath(string $root, string $dir)
    {
        return str_replace(kirby()->root('index'), '', kirby()->root($root)) . '/' . $dir;
    }

    /**
     * The path from the index folder, or the full path for folders outside of it
     *
     * @param string $path
     * @return string
     */
    public static function relative(string $path): string
    {
        $index = static::realpath(kirby()->root('index'));
        $path  = static::realpath($path) ?? str_replace('\\', '/', $path);

        return str_starts_with($path, $index . '/') === true ? substr($path, strlen($index)) : $path;
    }

    /**
     * Admins can always clear, unless the area is disabled for their role.
     * Other roles need the `owebstudio.clear-cache.clear` permission.
     *
     * @return bool
     */
    public static function canClear(): bool
    {
        if (kirby()->panel()->access()->area('clear-cache') === false) {
            return false;
        }

        $user = kirby()->user();

        if ($user->isAdmin() === true) {
            return true;
        }

        return $user->role()->permissions()->for('owebstudio.clear-cache', 'clear') === true;
    }

    /**
     * @return array
     */
    public static function props()
    {
        if (static::$props !== null) {
            return static::$props;
        }

        return static::$props = [
            'cache' => ClearCache::cacheDirs(),
            'media' => ClearCache::mediaDirs(),
            'other' => ClearCache::otherDirs(),
        ];
    }

    /**
     * Props for the Panel view, without the paths on the server
     *
     * @return array
     */
    public static function view(): array
    {
        return array_map(
            fn (array $dirs) => array_values(array_map(fn (array $dir) => A::without($dir, 'path'), $dirs)),
            static::props()
        );
    }

    /**
     * The caches of Kirby, the config and the plugins
     *
     * @return array
     */
    public static function cacheDirs()
    {
        $dirs = [];

        foreach (static::cacheNames() as $name) {
            try {
                $cache = kirby()->cache($name);
            } catch (Throwable) {
                continue;
            }

            if (
                $cache instanceof NullCache ||
                ($cache instanceof FileCache && static::containsRoot($cache->root()) === true)
            ) {
                continue;
            }

            $paths = $cache instanceof FileCache ? static::cachePaths($cache) : [];

            $dirs[$name] = [
                'name' => $name,
                'path' => $paths,
                'text' => match ($name) {
                    'pages'   => 'Pages',
                    'uuid'    => 'UUIDs',
                    'updates' => 'Updates',
                    default   => $name
                },
                'info' => match (true) {
                    $cache instanceof FileCache === false => preg_replace('/Cache$/', '', (new ReflectionClass($cache))->getShortName()),
                    count($paths) > 1                    => static::relativePath('cache', '*/' . static::cacheKey($cache)),
                    default                              => static::relative($cache->root())
                }
            ];
        }

        return $dirs;
    }

    /**
     * Names of all active caches, without the index
     * of unsaved changes that Kirby rebuilds on its own
     *
     * @return array
     */
    public static function cacheNames(): array
    {
        $kirby = kirby();
        $names = array_keys($kirby->core()->caches());

        foreach ($kirby->options() as $key => $value) {
            if ($key === 'cache' && is_array($value) === true) {
                $names = [...$names, ...array_keys($value)];
            } elseif (str_starts_with($key, 'cache.') === true) {
                $names[] = substr($key, 6);
            }
        }

        foreach ($kirby->plugins() as $plugin) {
            $prefix  = $plugin->prefix();
            $options = $kirby->option($prefix . '.cache');

            if (
                $options === true ||
                (is_array($options) === true && array_intersect(array_keys($options), ['active', 'type', 'root', 'prefix', 'extension']) !== [])
            ) {
                $names[] = $prefix;
            } elseif (is_array($options) === true) {
                foreach (array_keys($options) as $key) {
                    $names[] = $prefix . '.' . $key;
                }
            }
        }

        return array_values(array_diff(array_unique($names), ['changes']));
    }

    /**
     * The folders of a file cache, for the current domain
     * as well as for other domains and the command line
     *
     * @param FileCache $cache
     * @return array
     */
    public static function cachePaths(FileCache $cache): array
    {
        $key = static::cacheKey($cache);

        if ($key === null) {
            return [$cache->root()];
        }

        $paths = [];

        foreach (Dir::dirs(kirby()->root('cache'), null, true) as $dir) {
            if (is_dir($dir . '/' . $key) === true) {
                $paths[] = $dir . '/' . $key;
            }
        }

        return $paths;
    }

    /**
     * Path of the cache inside the folder of the current domain,
     * or null if the cache has its own root or prefix
     *
     * @param FileCache $cache
     * @return string|null
     */
    public static function cacheKey(FileCache $cache): string|null
    {
        $domain = static::realpath(kirby()->root('cache')) . '/' . str_replace(['/', ':'], '_', kirby()->system()->indexUrl());
        $root   = static::realpath($cache->root());

        if ($root === null || str_starts_with($root, $domain . '/') === false) {
            return null;
        }

        return substr($root, strlen($domain) + 1);
    }

    /**
     * Empties a cache for all domains
     *
     * @param string $name
     * @return void
     */
    public static function clearCache(string $name): void
    {
        $cache = kirby()->cache($name);

        if ($cache instanceof FileCache && static::containsRoot($cache->root()) === true) {
            throw new Exception('Invalid directory!');
        }

        $cache->flush();

        if ($cache instanceof FileCache) {
            foreach (static::cachePaths($cache) as $path) {
                if (static::isRemovable($path, kirby()->root('cache')) === true) {
                    static::remove($path);
                }
            }
        }
    }

    /**
     * @return array
     */
    public static function mediaDirs()
    {
        return static::folders('media');
    }

    /**
     * Folders in the given root that can be removed safely
     *
     * @param string $root
     * @return array
     */
    public static function folders(string $root): array
    {
        $dirs = [];

        foreach (Dir::dirs(kirby()->root($root), null, true) as $dir) {
            if (static::isRemovable($dir, kirby()->root($root)) === false) {
                continue;
            }

            $basename = basename($dir);

            $dirs[$basename] = [
                'name' => $basename,
                'path' => $dir,
                'text' => $basename,
                'info' => static::relativePath($root, $basename)
            ];
        }

        return $dirs;
    }

    /**
     * @return array[]
     */
    public static function otherDirs()
    {
        $dirs = [];

        // lock
        $locksPath = kirby()->root('content');
        if (static::lockFiles($locksPath)) {
            $dirs['lock'] = [
                'name' => 'lock',
                'path' => $locksPath,
                'text' => 'Content lock files',
                'info' => static::relativePath('content', '*.lock')
            ];
        }

        // logins
        $loginsFile = kirby()->root('accounts') . '/.logins';
        if (F::exists($loginsFile) === true) {
            $dirs['logins'] = [
                'name' => 'logins',
                'path' => $loginsFile,
                'text' => 'Users login data',
                'info' => static::relativePath('accounts', '.logins')
            ];
        }

        return $dirs;
    }

    /**
     * @param string $type
     * @return void
     * @throws Exception
     */
    public static function clearType(string $type): void
    {
        foreach (static::props()[$type] ?? [] as $dir) {
            static::clearDir($type, $dir);
        }

        // folders of removed caches and caches with their own prefix
        if ($type === 'cache') {
            foreach (static::folders('cache') as $dir) {
                static::remove($dir['path']);
            }
        }
    }

    /**
     * @param string $type
     * @param string|array $dir
     * @return void
     * @throws Exception
     */
    public static function clearDir(string $type, $dir): void
    {
        if (is_array($dir) === false) {
            $dir = static::props()[$type][$dir] ?? null;

            if (empty($dir) === true) {
                throw new Exception('Invalid directory!');
            }
        }

        switch ($type) {
            case 'cache':
                static::clearCache($dir['name']);
                break;
            case 'media':
                if (static::isRemovable($dir['path'], kirby()->root($type)) === false) {
                    throw new Exception('Invalid directory!');
                }

                static::remove($dir['path']);

                // cached pages would link to thumbs whose jobs are gone
                if (in_array($dir['name'], ['panel', 'plugins'], true) === false) {
                    static::clearCache('pages');
                }
                break;
            case 'other':
                switch ($dir['name']) {
                    case 'logins':
                        F::unlink($dir['path']);
                        break;
                    case 'lock':
                        static::clearLock($dir['path']);
                        break;
                }
                break;
        }
    }

    /**
     * Checks that the path is inside the given root and doesn't contain
     * another root of the site, e.g. when the media root is the index folder
     *
     * @param string $path
     * @param string $root
     * @return bool
     */
    public static function isRemovable(string $path, string $root): bool
    {
        $root = static::realpath($root);
        $link = is_link($path);

        // links are removed, never followed
        $path = $link === true
            ? static::realpath(dirname($path)) . '/' . basename($path)
            : static::realpath($path);

        if ($root === null || $path === null || str_starts_with($path, $root . '/') === false) {
            return false;
        }

        return $link === true || static::containsRoot($path) === false;
    }

    /**
     * Checks if the path is or contains a root of the site
     * other than the cache and media folders
     *
     * @param string $path
     * @return bool
     */
    public static function containsRoot(string $path): bool
    {
        $path = static::realpath($path) ?? $path;

        foreach (kirby()->roots()->toArray() as $key => $root) {
            if (in_array($key, ['cache', 'media'], true) === true) {
                continue;
            }

            $root = static::realpath($root);

            if ($root !== null && ($root === $path || str_starts_with($root, $path . '/') === true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes a file or folder. Links are removed, never followed.
     *
     * @param string $path
     * @return void
     */
    public static function remove(string $path): void
    {
        if (is_link($path) === true) {
            // links to folders can only be removed with rmdir() on Windows
            is_dir($path) === true && DIRECTORY_SEPARATOR === '\\' ? rmdir($path) : unlink($path);
            return;
        }

        if (is_dir($path) === true) {
            Dir::remove($path);
            return;
        }

        F::unlink($path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    protected static function realpath(string $path): string|null
    {
        $realpath = realpath($path);

        return $realpath === false ? null : rtrim(str_replace('\\', '/', $realpath), '/');
    }

    /**
     * @param string $path
     * @return void
     */
    public static function clearLock(string $path): void
    {
        $files = static::lockFiles($path);

        foreach ($files as $file) {
            if (is_file($file) === true) {
                F::unlink($file);
            }
        }
    }

    /**
     * @param string $path
     * @param array $files
     * @return array
     */
    public static function lockFiles(string $path, array $files = []): array
    {
        foreach (Dir::read($path, null, true) as $dir) {
            if (is_dir($dir) === true) {
                $files = static::lockFiles($dir, $files);
            } else {
                if (basename($dir) === '.lock' && is_file($dir) === true) {
                    $files[] = $dir;
                }
            }
        }

        return $files;
    }
}
