<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit81f7247027640df9f1bc96b2dd23a25f
{
    public static $prefixLengthsPsr4 = array (
        'v' => 
        array (
            'voku\\helper\\' => 12,
            'voku\\' => 5,
        ),
        'S' => 
        array (
            'Symfony\\Component\\CssSelector\\' => 30,
        ),
        'M' => 
        array (
            'MatthiasMullie\\PathConverter\\' => 29,
            'MatthiasMullie\\Minify\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'voku\\helper\\' => 
        array (
            0 => __DIR__ . '/..' . '/voku/simple_html_dom/src/voku/helper',
        ),
        'voku\\' => 
        array (
            0 => __DIR__ . '/..' . '/voku/html-min/src/voku',
        ),
        'Symfony\\Component\\CssSelector\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/css-selector',
        ),
        'MatthiasMullie\\PathConverter\\' => 
        array (
            0 => __DIR__ . '/..' . '/matthiasmullie/path-converter/src',
        ),
        'MatthiasMullie\\Minify\\' => 
        array (
            0 => __DIR__ . '/..' . '/matthiasmullie/minify/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit81f7247027640df9f1bc96b2dd23a25f::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit81f7247027640df9f1bc96b2dd23a25f::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit81f7247027640df9f1bc96b2dd23a25f::$classMap;

        }, null, ClassLoader::class);
    }
}
