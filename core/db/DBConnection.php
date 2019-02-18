<?php
/**
 * Created by PhpStorm.
 * User: Ivan
 * Date: 14.02.2019
 * Time: 15:07
 */

namespace core\db;
use core\Configurator;


class DBConnection{
    private $configName;

    public function __construct($configName)
    {
        $this->configName = $configName;
    }

    public function createDBConnection(){
        $config = new Configurator("db");
        $name = $this->configName;
        $cfg = $config->$name;

        return new \PDO("mysql:host={$cfg["host"]};port={$cfg["port"]};dbname={$cfg["name"]};charset={$cfg["charset"]}",
            $cfg["user"],$cfg["pass"]);
    }
}