<?php

namespace Oweb;

use Kirby\Exception\Exception;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;

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
     * @return array
     */
    public static function cacheDirs()
    {
        return static::folders('cache');
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
            case 'media':
                if (static::isRemovable($dir['path'], kirby()->root($type)) === false) {
                    throw new Exception('Invalid directory!');
                }

                static::remove($dir['path']);

                // cached pages would link to thumbs whose jobs are gone
                if ($type === 'media' && in_array($dir['name'], ['panel', 'plugins'], true) === false) {
                    kirby()->cache('pages')->flush();
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

        // links are removed, never followed
        $path = is_link($path) === true
            ? static::realpath(dirname($path)) . '/' . basename($path)
            : static::realpath($path);

        if ($root === null || $path === null || str_starts_with($path, $root . '/') === false) {
            return false;
        }

        foreach (kirby()->roots()->toArray() as $key => $protected) {
            if (in_array($key, ['cache', 'media'], true) === true) {
                continue;
            }

            $protected = static::realpath($protected);

            if ($protected !== null && ($protected === $path || str_starts_with($protected, $path . '/') === true)) {
                return false;
            }
        }

        return true;
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
