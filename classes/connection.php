<?php

namespace Controller;

use Exception;
use PDO;

class Connection
{
    private  ?string $servername = 'mysql'; //container name
    private  ?string $username = 'root';
    private  ?string $password = 'ouichouani';
    private  ?string $databasename = 'youcode_brief_10';

    private static ?self $connection = null;
    private ?PDO $pdo = null ;

    private function __construct()
    {
        $option = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new \PDO("mysql:host=" . $this->servername . ";dbname=" . $this->databasename, $this->username, $this->password, $option);
    }

    public static function get_instance(): self
    {
        if (!self::$connection) {
            self::$connection = new self();
        }

        return self::$connection;
    }



    public function get_PDO(): PDO
    {
        return $this->pdo;
    }

    public function __wakeup(): void
    {
        throw new \Exception("Cannot unserialize singleton");
    }
    private function __clone(){}

}
