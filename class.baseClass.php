<?php

class BaseClass
{

    public $db;
    public $total=0;

    public function setDatabaseCredentials( $p )
    {
        $this->db_credentials = $p;
    }

    public function connectDatabase()
    {
        $this->db = new mysqli(
            $this->db_credentials["host"],
            $this->db_credentials["user"],
            $this->db_credentials["pass"]
        );

        $this->db->select_db($this->db_credentials["database"]);
        $this->db->set_charset("utf8");
    }

    public function log($message, $level = 3, $source = 'NBA')
    {
        $levels = [
            1 => 'Error',
            2 => 'Warning',
            3 => 'Info',
            4 => 'Debug',
        ];
        echo date('d-M-Y H:i:s') . ' - ' . $source . ' - ' .
            $levels[$level] . ' - ' . $message . "\n";
    }

}
