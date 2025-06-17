<?php


class RscDtgs_Autoloader
{

    /**
     * Register RscDtgs_Autoloader in the SPL autoloader stack
     */
    public static function register()
    {
        spl_autoload_register(array(__CLASS__, 'load'));
    }

    /**
     * Load the specified class
     * @param string $classname Name of the class to be loaded
     */
    public static function load($classname)
    {
        if (mb_stripos($classname,'twig') !== false) {
             if (class_exists($classname)) {
             die(); exit();
             }
        }
        if (substr($classname, 0, 7) !== 'RscDtgs') {
          return;
        }
        $classname = str_replace('RscDtgs', '', $classname);
        $file = dirname(__FILE__) . '/' . str_replace(array('_', '\0'), array('/', ''), $classname) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }

}
