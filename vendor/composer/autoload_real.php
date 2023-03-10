<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInita7bf0d245e4dde81f1cebd94de74dbcf
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        require __DIR__ . '/platform_check.php';

        spl_autoload_register(array('ComposerAutoloaderInita7bf0d245e4dde81f1cebd94de74dbcf', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInita7bf0d245e4dde81f1cebd94de74dbcf', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInita7bf0d245e4dde81f1cebd94de74dbcf::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
