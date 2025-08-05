<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

interface DBconnectionrable {
    public function mysqlConnection();
    public function mysqlClose();
    public function getError();
}

class DBconnection implements DBconnectionrable {
    private $servername = "localhost";
    private $dbname     = "czentrix_campaign_manager";
    private $username   = "tvtroot";
    private $password   = "sqladmin";
    private $conn;

    public function __construct(array $config = []) {
        $this->servername = $config['host']     ?? $this->servername;
        $this->username   = $config['username'] ?? $this->username;
        $this->password   = $config['password'] ?? $this->password;
        $this->dbname     = $config['dbname']   ?? $this->dbname;
    }

    public function mysqlConnection() {
        $this->conn = mysqli_connect($this->servername, $this->username, $this->password, $this->dbname);
        if (!$this->conn) {
            return false;
        }
        return $this->conn;
    }

    public function mysqlClose() {
        if ($this->conn) {
            mysqli_close($this->conn);
            $this->conn = null;
        }
    }

    public function getError(){
        return mysqli_connect_error();
    }
}
?>
