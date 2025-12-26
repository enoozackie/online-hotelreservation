<?php

namespace Lourdian\MonbelaHotel\Model;

use Lourdian\MonbelaHotel\Core\Database;
use Exception;

class Auth extends Database
{
    /**
     * Login method - handles both admin and guest login
     */
    public function login($username, $password)
    {
        try {
            // First check admin table
            $stmt = $this->conn->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
            $stmt->close();

            if ($admin && password_verify($password, $admin['password'])) {
                return [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'fullname' => $admin['fullname'] ?? '',
                    'role' => 'admin'
                ];
            }

            // If not admin, check guest table
            $stmt = $this->conn->prepare("SELECT * FROM tblguest WHERE G_UNAME = ? LIMIT 1");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $guest = $result->fetch_assoc();
            $stmt->close();

            if ($guest && password_verify($password, $guest['G_PASS'])) {
                return [
                    'id' => $guest['GUESTID'],
                    'username' => $guest['G_UNAME'],
                    'fullname' => $guest['G_FNAME'] . ' ' . $guest['G_LNAME'],
                    'role' => 'guest'
                ];
            }

            return false;
        } catch (Exception $e) {
            error_log("Auth login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Register guest
     */
    public function registerGuest($data)
    {
        try {
            // Check if username or phone already exists
            $stmt = $this->conn->prepare("SELECT GUESTID FROM tblguest WHERE G_UNAME = ? OR G_PHONE = ? LIMIT 1");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("ss", $data['G_UNAME'], $data['G_PHONE']);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                return false;
            }
            $stmt->close();

            // Hash password
            $hashedPassword = password_hash($data['G_PASS'], PASSWORD_DEFAULT);
            
            // Generate reference number
            $refNo = 'REF' . date('YmdHis') . rand(100, 999);

            // Insert guest
            $sql = "INSERT INTO tblguest 
                    (REFNO, G_FNAME, G_LNAME, G_CITY, G_ADDRESS, DBIRTH, G_PHONE, 
                     G_NATIONALITY, G_COMPANY, G_CADDRESS, G_TERMS, G_UNAME, G_PASS, ZIP, LOCATION)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $terms = $data['G_TERMS'] ?? 1;
            $city = $data['G_CITY'] ?? '';
            $nationality = $data['G_NATIONALITY'] ?? '';
            $company = $data['G_COMPANY'] ?? '';
            $caddress = $data['G_CADDRESS'] ?? '';
            $zip = $data['ZIP'] ?? '';
            $location = $data['LOCATION'] ?? '';

            $stmt->bind_param(
                "ssssssssssissis",
                $refNo,
                $data['G_FNAME'],
                $data['G_LNAME'],
                $city,
                $data['G_ADDRESS'],
                $data['DBIRTH'],
                $data['G_PHONE'],
                $nationality,
                $company,
                $caddress,
                $terms,
                $data['G_UNAME'],
                $hashedPassword,
                $zip,
                $location
            );

            $success = $stmt->execute();
            $stmt->close();
            return $success;
            
        } catch (Exception $e) {
            error_log("Auth register error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn()
    {
        return isset($_SESSION['id']) && isset($_SESSION['role']);
    }

    /**
     * Get current user
     */
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['id'],
            'username' => $_SESSION['username'] ?? '',
            'fullname' => $_SESSION['fullname'] ?? '',
            'role' => $_SESSION['role']
        ];
    }

    /**
     * Logout
     */
    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        session_unset();
        session_destroy();
        
        return true;
    }
}