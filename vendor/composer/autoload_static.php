<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit432303e49bcaa4ea2973b7fee4ffe536
{
    public static $files = array (
        'b5489b2f62c9a59ab916381df5616a21' => __DIR__ . '/../..' . '/lib/db.php',
    );

    public static $prefixLengthsPsr4 = array (
        'N' => 
        array (
            'NickBeen\\ProgressBar\\' => 21,
        ),
        'A' => 
        array (
            'App\\' => 4,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'NickBeen\\ProgressBar\\' => 
        array (
            0 => __DIR__ . '/..' . '/nickbeen/php-cli-progress-bar/src',
        ),
        'App\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit432303e49bcaa4ea2973b7fee4ffe536::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit432303e49bcaa4ea2973b7fee4ffe536::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit432303e49bcaa4ea2973b7fee4ffe536::$classMap;

        }, null, ClassLoader::class);
    }
}
