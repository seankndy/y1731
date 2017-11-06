<?php
/*
 * Basic singleton for getting instance of PDO
 *
 */

namespace SeanKndy\Y1731;

class Database
{
    private static $db = null;

    private static function init() {
        try {
            self::$db = new \PDO('mysql:host=' . $GLOBALS['config']['dbHost'] . ';dbname=' .
                $GLOBALS['config']['dbName'], $GLOBALS['config']['dbUser'], $GLOBALS['config']['dbPassword']);
            self::$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return true;
        } catch (\PDOException $e) {
            self::$db = null;
            return false;
        }
    }

    public static function getInstance() {
        if (self::$db == null) {
            if (!self::init()) {
                return null;
            }
        }
        try {
            $oldErrlevel = error_reporting(0);
            self::$db->query('select 1');
        } catch (\PDOException $e) {
            self::init();
        }
        error_reporting($oldErrlevel);
        return self::$db;
    }
    
    public static function close() {
        self::$db = null;
    }
}
