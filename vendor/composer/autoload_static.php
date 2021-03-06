<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit7a8ed280672dea8a39df18ff292e7e20
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'SeanKndy\\Y1731\\' => 15,
            'SeanKndy\\Daemon\\' => 16,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'SeanKndy\\Y1731\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'SeanKndy\\Daemon\\' => 
        array (
            0 => __DIR__ . '/..' . '/seankndy/daemon/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit7a8ed280672dea8a39df18ff292e7e20::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit7a8ed280672dea8a39df18ff292e7e20::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
