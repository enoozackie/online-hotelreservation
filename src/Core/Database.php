<?php
// =============================================================================
// src/Core/Database.php - Database Connection Class
// =============================================================================

namespace Lourdian\MonbelaHotel\Core;

class Database
{
   protected \mysqli $conn; // protected so models can access it


public function __construct()
{
// Force mysqli to throw real exceptions (no silent failures)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


$this->conn = $this->connect();
}
    private function connect()
    {
        // Database credentials
        $host = 'localhost';
        $user = 'root';
        $pass = ''; // Add your MySQL password if you have one
        $dbname = 'monbela_hotel'; // FIXED: Changed from 'monbelahotel' to 'monbela_hotel'
        
        // Create connection
        $conn = new \mysqli($host, $user, $pass, $dbname);
        
        // Check connection
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            die("Connection failed: " . $conn->connect_error);
        }
        
        // Set charset to support international characters
        $conn->set_charset("utf8mb4");
        
        return $conn;
    }
    
    /**
     * Get the database connection
     * @return \mysqli
     */
    public function getConnection()
    {
        return $this->conn;
    }
 
}