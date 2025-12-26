<?php
// =============================================================================
// src/Model/Guest.php
// =============================================================================
namespace Lourdian\MonbelaHotel\Model;

use Lourdian\MonbelaHotel\Core\Database;
use Exception;

class Guest extends Database
{
    /**
     * Check if username exists
     */
    public function usernameExists($username)
    {
        try {
            $stmt = $this->conn->prepare("SELECT GUESTID FROM tblguest WHERE G_UNAME = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->num_rows > 0;
            $stmt->close();
            return $exists;
        } catch (Exception $e) {
            error_log("Check username error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if phone exists
     */
    public function phoneExists($phone)
    {
        try {
            $stmt = $this->conn->prepare("SELECT GUESTID FROM tblguest WHERE G_PHONE = ? LIMIT 1");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->num_rows > 0;
            $stmt->close();
            return $exists;
        } catch (Exception $e) {
            error_log("Check phone error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get last database error
     */
    public function getLastError()
    {
        return $this->conn->error;
    }

    /**
     * Create a new guest
     */
    public function create($data)
    {
        try {
            $required = ['G_FNAME','G_LNAME','G_ADDRESS','DBIRTH','G_PHONE','G_UNAME','G_PASS'];
            foreach($required as $field){
                if(empty($data[$field])){
                    throw new Exception("$field is required");
                }
            }

            // Check for existing username or phone
            $check = $this->conn->prepare("SELECT GUESTID FROM tblguest WHERE G_UNAME=? OR G_PHONE=? LIMIT 1");
            $check->bind_param("ss",$data['G_UNAME'],$data['G_PHONE']);
            $check->execute();
            $res = $check->get_result();
            if($res->num_rows > 0){
                throw new Exception("Username or phone already exists");
            }
            $check->close();

            // Hash password
            $hashedPassword = password_hash($data['G_PASS'], PASSWORD_DEFAULT);

            $stmt = $this->conn->prepare("INSERT INTO tblguest
            (REFNO,G_FNAME,G_LNAME,G_CITY,G_ADDRESS,DBIRTH,G_PHONE,G_NATIONALITY,G_COMPANY,G_CADDRESS,G_TERMS,G_UNAME,G_PASS,ZIP,LOCATION)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param(
                "ssssssssssissss",
                $data['REFNO'],
                $data['G_FNAME'],
                $data['G_LNAME'],
                $data['G_CITY'],
                $data['G_ADDRESS'],
                $data['DBIRTH'],
                $data['G_PHONE'],
                $data['G_NATIONALITY'],
                $data['G_COMPANY'],
                $data['G_CADDRESS'],
                $data['G_TERMS'],
                $data['G_UNAME'],
                $hashedPassword,
                $data['ZIP'],
                $data['LOCATION']
            );

            $result = $stmt->execute();
            
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
            return $result;
        } catch(Exception $e){
            error_log("Guest create error: ".$e->getMessage());
            return false;
        }
    }

    /**
     * Register a new guest (alias for create)
     */
    public function register($data){
        return $this->create($data);
    }
    
    /**
     * Get guest by ID
     */
    public function getById($id)
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tblguest WHERE GUESTID = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Get guest by ID error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update guest information
     */
    public function update(int $id, array $data): bool {
        try {
            // Build the query dynamically based on provided data
            $fields = [];
            $types = "";
            $values = [];
            
            $allowedFields = [
                'G_FNAME' => 's',
                'G_LNAME' => 's',
                'G_CITY' => 's',
                'G_ADDRESS' => 's',
                'DBIRTH' => 's',
                'G_PHONE' => 's',
                'G_NATIONALITY' => 's',
                'G_COMPANY' => 's',
                'G_CADDRESS' => 's',
                'G_UNAME' => 's',
                'ZIP' => 's',
                'LOCATION' => 's'
            ];
            
            foreach ($allowedFields as $field => $type) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $types .= $type;
                    $values[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                throw new Exception("No valid fields provided for update");
            }
            
            $sql = "UPDATE tblguest SET " . implode(', ', $fields) . " WHERE GUESTID = ?";
            $types .= "i";
            $values[] = $id;
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param($types, ...$values);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Update guest error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update guest password
     */
    public function updatePassword(int $guestId, string $newPassword): bool {
        try {
            // Validate password strength
            if (strlen($newPassword) < 6) {
                throw new Exception("Password must be at least 6 characters long");
            }
            
            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $sql = "UPDATE tblguest SET G_PASS = ? WHERE GUESTID = ?";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("si", $hashedPassword, $guestId);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch (Exception $e) {
            error_log("Update password error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify guest password
     */
    public function verifyPassword(int $guestId, string $password): bool {
        try {
            $stmt = $this->conn->prepare("SELECT G_PASS FROM tblguest WHERE GUESTID = ? LIMIT 1");
            $stmt->bind_param("i", $guestId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return false;
            }
            
            $guest = $result->fetch_assoc();
            $stmt->close();
            
            return password_verify($password, $guest['G_PASS']);
        } catch (Exception $e) {
            error_log("Verify password error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add new guest
     */
    public function addGuest(array $data): bool {
        try {
            // Generate reference number
            $refNo = 'REF' . date('YmdHis') . rand(100, 999);
            
            $sql = "INSERT INTO tblguest (G_FNAME, G_LNAME, G_CITY, G_ADDRESS, DBIRTH, 
                                         G_PHONE, G_NATIONALITY, G_COMPANY, G_CADDRESS, 
                                         G_UNAME, ZIP, LOCATION, REFNO) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("sssssssssssss", 
                $data['G_FNAME'], 
                $data['G_LNAME'], 
                $data['G_CITY'], 
                $data['G_ADDRESS'], 
                $data['DBIRTH'], 
                $data['G_PHONE'], 
                $data['G_NATIONALITY'], 
                $data['G_COMPANY'], 
                $data['G_CADDRESS'], 
                $data['G_UNAME'], 
                $data['ZIP'], 
                $data['LOCATION'], 
                $refNo
            );
            
            $success = $stmt->execute();
            $stmt->close();
            
            return $success;
        } catch (Exception $e) {
            error_log("Add guest error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update a guest's profile image
     */
    public function updateProfileImage($guestId, $imageFilename)
    {
        try {
            // First, get the old image to delete it later
            $stmt = $this->conn->prepare("SELECT G_PROFILEIMAGE FROM tblguest WHERE GUESTID = ?");
            $stmt->bind_param("i", $guestId);
            $stmt->execute();
            $result = $stmt->get_result();
            $oldImage = $result->fetch_assoc()['G_PROFILEIMAGE'];
            $stmt->close();

            // Update database with new image filename
            $stmt = $this->conn->prepare("UPDATE tblguest SET G_PROFILEIMAGE = ? WHERE GUESTID = ?");
            $stmt->bind_param("si", $imageFilename, $guestId);
            $result = $stmt->execute();
            $stmt->close();

            if ($result && $oldImage && $oldImage !== $imageFilename) {
                // Delete old image file from server
                $imageDir = $_SERVER['DOCUMENT_ROOT'] . '/MonbelaHotel/images/profiles/';
                $oldImagePath = $imageDir . $oldImage;
                
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("Update profile image error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all guests
     */
    public function getAll($limit = 50, $offset = 0)
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tblguest ORDER BY GUESTID DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Get all guests error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get guest bookings
     */
    public function getGuestBookings($guestId)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT r.*, rm.ROOMNUM as room_number, rm.ROOMDESC as room_desc, 
                       rm.PRICE as price, rm.ROOMIMAGE as roomimg, rm.NUMPERSON as numperson
                FROM tblreservation r
                LEFT JOIN tblroom rm ON r.ROOMID = rm.ROOMID
                WHERE r.GUESTID = ?
                ORDER BY r.ARRIVAL DESC
            ");
            $stmt->bind_param("i", $guestId);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Get guest bookings error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get guest payments
     */
    public function getGuestPayments($guestId)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT p.*, r.ARRIVAL as booking_date
                FROM tblpayment p
                LEFT JOIN tblreservation r ON p.CONFIRMATIONCODE = r.CONFIRMATIONCODE
                WHERE p.GUESTID = ?
                ORDER BY p.TRANSDATE DESC
            ");
            $stmt->bind_param("i", $guestId);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Get guest payments error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete a guest
     */
    public function delete($guestId)
    {
        try {
            // First, check if the guest has any active reservations
            $check = $this->conn->prepare("SELECT COUNT(*) as count FROM tblreservation WHERE GUESTID = ? AND STATUS = 'Confirmed'");
            $check->bind_param("i", $guestId);
            $check->execute();
            $result = $check->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                throw new Exception("Cannot delete guest with active reservations");
            }
            
            // Delete the guest
            $stmt = $this->conn->prepare("DELETE FROM tblguest WHERE GUESTID = ?");
            $stmt->bind_param("i", $guestId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Delete guest error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Search guests
     */
    public function search($keyword, $limit = 50)
    {
        try {
            $searchTerm = "%$keyword%";
            $stmt = $this->conn->prepare("
                SELECT * FROM tblguest 
                WHERE G_FNAME LIKE ? OR G_LNAME LIKE ? OR G_UNAME LIKE ? OR G_PHONE LIKE ?
                ORDER BY GUESTID DESC
                LIMIT ?
            ");
            $stmt->bind_param("ssssi", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Search guests error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Count total guests
     */
    public function count()
    {
        try {
            $result = $this->conn->query("SELECT COUNT(*) as count FROM tblguest");
            return $result ? $result->fetch_assoc()['count'] : 0;
        } catch (Exception $e) {
            error_log("Count guests error: " . $e->getMessage());
            return 0;
        }
    }
}