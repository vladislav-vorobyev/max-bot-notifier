<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Engine;

/**
 * 
 * Single object to collect all global objects.
 * 
 */
class Storage {

    /**
     * @var array storage of dependency
     */
    static private $map = [];

    /**
     * 
     * Get an object from storage or from load.
     * 
     * @param string object name
     * 
     * @return mixed an object from storage
     */
    static public function get($name)
    {
        return self::$map[$name] ?? DI::load($name);
    }

    /**
     * 
     * Put an object to storage.
     * 
     * @param string object name
     * @param mixed an object to put
     * 
     * @return mixed set object
     */
    static public function set($name, $dependency)
    {
        self::$map[$name] = $dependency;
        return self::$map[$name];
    }
}
