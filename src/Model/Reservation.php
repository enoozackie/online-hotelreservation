<?php
// =============================================================================
// src/Model/Reservation.php - FIXED (Removed RoomHistory dependency)
// =============================================================================
namespace Lourdian\MonbelaHotel\Model;

use Lourdian\MonbelaHotel\Model\Guest;
use Lourdian\MonbelaHotel\Core\Database;
use Exception;

class Reservation extends Database
{
    /**
     * Get all reservations for a specific room
     */
    public function getReservationsByRoom($roomId) {
        try {
            $sql = "SELECT r.*, g.G_FNAME, g.G_LNAME, g.G_PHONE 
                    FROM tblreservation r
                    JOIN tblguest g ON r.GUESTID = g.GUESTID
                    WHERE r.ROOMID = ?
                    ORDER BY r.ARRIVAL DESC";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $roomId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $reservations = [];
            while ($row = $result->fetch_assoc()) {
                $reservations[] = $row;
            }
            
            $stmt->close();
            return $reservations;
        } catch (Exception $e) {
            error_log("Error fetching reservations by room: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get current reservation for a room (if any)
     */
    public function getCurrentReservationForRoom($roomId) {
        try {
            $sql = "SELECT r.*, g.G_FNAME, g.G_LNAME 
                    FROM tblreservation r
                    JOIN tblguest g ON r.GUESTID = g.GUESTID
                    WHERE r.ROOMID = ? 
                    AND r.STATUS = 'Confirmed'
                    AND CURDATE() BETWEEN r.ARRIVAL AND r.DEPARTURE
                    LIMIT 1";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $roomId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $reservation = $result->fetch_assoc();
            $stmt->close();
            
            return $reservation ?: false;
        } catch (Exception $e) {
            error_log("Error fetching current reservation: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateReservation($reserveId, $data)
    {
        $this->conn->begin_transaction();
        try {
            $currentReservation = $this->getById($reserveId);
            if (!$currentReservation) {
                throw new Exception("Reservation not found.");
            }
            
            $roomChanged = ($currentReservation['ROOMID'] != $data['ROOMID']);
            
            if (!$this->isRoomAvailable($data['ROOMID'], $data['ARRIVAL'], $data['DEPARTURE'], $reserveId)) {
                throw new Exception("Room is not available for the selected dates.");
            }
            
            $newPrice = $this->calculatePrice($data['ROOMID'], $data['ARRIVAL'], $data['DEPARTURE']);
            if ($newPrice === false) {
                throw new Exception("Could not calculate price. Room might not exist.");
            }
            
            if ($roomChanged) {
                $this->updateRoomAvailability($currentReservation['ROOMID'], 1);
                $this->updateRoomAvailability($data['ROOMID'], -1);
            }
            
            $sql = "UPDATE tblreservation 
                    SET ROOMID = ?, 
                        ARRIVAL = ?, 
                        DEPARTURE = ?, 
                        RPRICE = ?, 
                        PRORPOSE = ?, 
                        REMARKS = ?
                    WHERE RESERVEID = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $purpose = $data['PRORPOSE'] ?? 'Leisure';
            $remarks = $data['REMARKS'] ?? '';
            $stmt->bind_param(
                "issdssi",
                $data['ROOMID'],
                $data['ARRIVAL'],
                $data['DEPARTURE'],
                $newPrice,
                $purpose,
                $remarks,
                $reserveId
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Update reservation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function createReservation($data)
    {
        error_log("=== Creating Reservation ===");
        error_log("Data: " . print_r($data, true));
        
        $this->conn->begin_transaction();
        try {
            // Check room availability
            if (!$this->isRoomAvailable($data['ROOMID'], $data['ARRIVAL'], $data['DEPARTURE'])) {
                throw new Exception("Room is not available for the selected dates.");
            }
            
            // Calculate price
            $price = $this->calculatePrice($data['ROOMID'], $data['ARRIVAL'], $data['DEPARTURE']);
            if ($price === false) {
                throw new Exception("Could not calculate price. Room might not exist.");
            }
            error_log("Calculated price: $price");
            
            // Generate confirmation code
            $confirmationCode = $this->generateConfirmationCode();
            error_log("Confirmation code: $confirmationCode");
            
            // Insert reservation
            $sql = "INSERT INTO tblreservation 
                    (CONFIRMATIONCODE, TRANSDATE, ROOMID, ARRIVAL, DEPARTURE, RPRICE, GUESTID, PRORPOSE, STATUS, BOOKDATE, REMARKS, USERID) 
                    VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $purpose = $data['PRORPOSE'] ?? 'Leisure';
            $remarks = $data['REMARKS'] ?? '';
            $userId = $data['USERID'] ?? null;
            
            $stmt->bind_param(
                "sisdssssi",
                $confirmationCode,
                $data['ROOMID'],
                $data['ARRIVAL'],
                $data['DEPARTURE'],
                $price,
                $data['GUESTID'],
                $purpose,
                $remarks,
                $userId
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $reservationId = $this->conn->insert_id;
            error_log("Reservation created with ID: $reservationId");
            $stmt->close();
            
            // Update room availability
            $this->updateRoomAvailability($data['ROOMID'], -1);
            
            // REMOVED RoomHistory logging to fix the error
            error_log("Skipping room history logging (not implemented)");
            
            $this->conn->commit();
            error_log("=== Reservation Created Successfully ===");
            return $confirmationCode;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Create reservation error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Get a single reservation by its ID
     */
    public function getById($reserveId)
    {
        try {
            if (!$this->conn) {
                error_log("ERROR: Database connection is NULL!");
                return null;
            }
            
            $sql = "SELECT 
                        r.*,
                        g.G_FNAME, g.G_LNAME, g.G_PHONE, g.G_UNAME,
                        room.ROOM, room.ROOMNUM
                    FROM 
                        tblreservation r
                    LEFT JOIN 
                        tblguest g ON r.GUESTID = g.GUESTID
                    LEFT JOIN 
                        tblroom room ON r.ROOMID = room.ROOMID
                    WHERE 
                        r.RESERVEID = ?";
            
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                error_log("Prepare failed: " . $this->conn->error);
                return null;
            }
            
            $stmt->bind_param("i", $reserveId);
            
            if (!$stmt->execute()) {
                error_log("Execute failed: " . $stmt->error);
                $stmt->close();
                return null;
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                return null;
            }
            
            $reservation = $result->fetch_assoc();
            
            // Add G_EMAIL as alias for G_UNAME for backward compatibility
            if (isset($reservation['G_UNAME']) && !isset($reservation['G_EMAIL'])) {
                $reservation['G_EMAIL'] = $reservation['G_UNAME'];
            }
            
            $stmt->close();
            return $reservation;
        } catch (Exception $e) {
            error_log("Get reservation by ID error: " . $e->getMessage());
            return null;
        }
    }
    
    public function getByGuestId($guestId)
    {
        try {
            $sql = "SELECT
                        r.*,
                        room.ROOM,
                        room.ROOMNUM
                    FROM
                        tblreservation r
                    LEFT JOIN
                        tblroom room ON r.ROOMID = room.ROOMID
                    WHERE
                        r.GUESTID = ?
                    ORDER BY
                        r.BOOKDATE DESC";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $guestId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $reservations = [];
            while ($row = $result->fetch_assoc()) {
                $reservations[] = $row;
            }
            
            $stmt->close();
            return $reservations;
            
        } catch (Exception $e) {
            error_log("Get reservations by guest ID error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all reservations
     */
    public function getAll()
    {
        try {
            if (!isset($this->conn)) {
                error_log("ERROR: No database connection in getAll()!");
                return [];
            }
            
            $sql = "SELECT 
                        r.*,
                        g.G_FNAME, g.G_LNAME, g.G_PHONE,
                        room.ROOMNUM, room.ROOM
                    FROM 
                        tblreservation r
                    LEFT JOIN 
                        tblguest g ON r.GUESTID = g.GUESTID
                    LEFT JOIN 
                        tblroom room ON r.ROOMID = room.ROOMID
                    ORDER BY 
                        r.BOOKDATE DESC";
            
            $result = $this->conn->query($sql);
            if (!$result) {
                error_log("Query failed in getAll(): " . $this->conn->error);
                return [];
            }
            
            $reservations = $result->fetch_all(MYSQLI_ASSOC);
            return $reservations;
        } catch (Exception $e) {
            error_log("Get all reservations error: " . $e->getMessage());
            return [];
        }
    }
    
    public function confirmReservation($reserveId)
    {
        try {
            $stmt = $this->conn->prepare("UPDATE tblreservation SET STATUS = 'Confirmed' WHERE RESERVEID = ?");
            $stmt->bind_param("i", $reserveId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Confirm reservation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function cancelReservation($reserveId)
    {
        $this->conn->begin_transaction();
        try {
            $reservation = $this->getById($reserveId);
            if (!$reservation || $reservation['STATUS'] === 'Cancelled') {
                throw new Exception("Reservation not found or already cancelled.");
            }
            
            $stmt = $this->conn->prepare("UPDATE tblreservation SET STATUS = 'Cancelled' WHERE RESERVEID = ?");
            $stmt->bind_param("i", $reserveId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update reservation status.");
            }
            $stmt->close();
            
            $this->updateRoomAvailability($reservation['ROOMID'], 1);
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Cancel reservation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if room is available for booking
     */
    private function isRoomAvailable($roomId, $arrival, $departure, $excludeReserveId = null)
    {
        try {
            // Check for overlapping reservations
            $sql = "SELECT COUNT(*) as count FROM tblreservation 
                    WHERE ROOMID = ? AND STATUS IN ('Pending', 'Confirmed')
                    AND (
                        (ARRIVAL < ? AND DEPARTURE > ?) OR
                        (ARRIVAL >= ? AND ARRIVAL < ?) OR
                        (DEPARTURE > ? AND DEPARTURE <= ?)
                    )";
            
            $params = [$roomId, $departure, $arrival, $arrival, $departure, $arrival, $departure];
            $types = "issssss";
            
            if ($excludeReserveId) {
                $sql .= " AND RESERVEID != ?";
                $types .= "i";
                $params[] = $excludeReserveId;
            }
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['count'];
            $stmt->close();
            
            if ($count > 0) {
                error_log("Room $roomId has $count overlapping reservations");
                return false;
            }
            
            // Check if room exists
            $roomCheck = $this->conn->prepare("SELECT ROOMID FROM tblroom WHERE ROOMID = ? LIMIT 1");
            $roomCheck->bind_param("i", $roomId);
            $roomCheck->execute();
            $roomExists = $roomCheck->get_result()->num_rows > 0;
            $roomCheck->close();
            
            if (!$roomExists) {
                error_log("Room ID $roomId does not exist");
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Check availability error: " . $e->getMessage());
            return false;
        }
    }
    
    private function calculatePrice($roomId, $arrival, $departure)
    {
        try {
            $sql = "SELECT PRICE FROM tblroom WHERE ROOMID = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $roomId);
            $stmt->execute();
            $result = $stmt->get_result();
            $room = $result->fetch_assoc();
            $stmt->close();
            
            if (!$room) {
                error_log("Room not found for price calculation: $roomId");
                return false;
            }
            
            $pricePerNight = $room['PRICE'];
            $arrivalDate = new \DateTime($arrival);
            $departureDate = new \DateTime($departure);
            $interval = $arrivalDate->diff($departureDate);
            $nights = $interval->days;
            
            if ($nights <= 0) {
                error_log("Invalid nights calculation: $nights");
                return false;
            }
            
            return $pricePerNight * $nights;
        } catch (Exception $e) {
            error_log("Calculate price error: " . $e->getMessage());
            return false;
        }
    }
    
    private function generateConfirmationCode()
    {
        do {
            $code = 'MBH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $stmt = $this->conn->prepare("SELECT RESERVEID FROM tblreservation WHERE CONFIRMATIONCODE = ?");
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->num_rows > 0;
            $stmt->close();
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Update room availability
     */
    private function updateRoomAvailability($roomId, $change)
    {
        try {
            // Check if OROOMNUM column exists
            $checkColumn = $this->conn->query("SHOW COLUMNS FROM tblroom LIKE 'OROOMNUM'");
            
            if ($checkColumn->num_rows === 0) {
                error_log("OROOMNUM column does not exist - skipping availability update");
                return true;
            }
            
            $sql = "UPDATE tblroom SET OROOMNUM = OROOMNUM + ? WHERE ROOMID = ?";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("ii", $change, $roomId);
            $result = $stmt->execute();
            
            if ($result) {
                error_log("Updated OROOMNUM for room $roomId by $change");
            }
            
            $stmt->close();
            return $result;
            
        } catch (Exception $e) {
            error_log("Update room availability error: " . $e->getMessage());
            return true; // Don't fail the transaction
        }
    }
    
    private function getRoomDetails($roomId)
    {
        try {
            $sql = "SELECT * FROM tblroom WHERE ROOMID = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $roomId);
            $stmt->execute();
            $result = $stmt->get_result();
            $room = $result->fetch_assoc();
            $stmt->close();
            return $room;
        } catch (Exception $e) {
            error_log("Get room details error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get pending reservations
     */
    public function getPendingReservations()
    {
        try {
            $sql = "SELECT r.*, g.G_FNAME, g.G_LNAME, g.G_PHONE, rm.ROOM, rm.ROOMNUM 
                    FROM tblreservation r 
                    LEFT JOIN tblguest g ON r.GUESTID = g.GUESTID 
                    LEFT JOIN tblroom rm ON r.ROOMID = rm.ROOMID 
                    WHERE r.STATUS = 'Pending' 
                    ORDER BY r.ARRIVAL ASC";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $reservations = [];
            while ($row = $result->fetch_assoc()) {
                $reservations[] = $row;
            }
            
            $stmt->close();
            return $reservations;
            
        } catch (Exception $e) {
            error_log("Get pending reservations error: " . $e->getMessage());
            return [];
        }
    }
    
    public function updateStatus(int $reservationId, string $status): bool
    {
        try {
            $sql = "UPDATE tblreservation SET STATUS = ? WHERE RESERVEID = ?";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            $stmt->bind_param("si", $status, $reservationId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Update status error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteReservation($reserveId)
    {
        $this->conn->begin_transaction();
        try {
            $reservation = $this->getById($reserveId);
            if (!$reservation) {
                throw new Exception("Reservation not found.");
            }
            
            $stmt = $this->conn->prepare("DELETE FROM tblreservation WHERE RESERVEID = ?");
            $stmt->bind_param("i", $reserveId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete reservation.");
            }
            $stmt->close();
            
            $this->updateRoomAvailability($reservation['ROOMID'], 1);
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Delete reservation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function checkOut($reserveId)
    {
        $this->conn->begin_transaction();
        try {
            $reservation = $this->getById($reserveId);
            if (!$reservation) {
                throw new Exception("Reservation not found.");
            }
            
            if ($reservation['STATUS'] === 'Checked Out' || $reservation['STATUS'] === 'Cancelled') {
                throw new Exception("Cannot check out. Reservation is already " . $reservation['STATUS'] . ".");
            }
            
            if ($reservation['STATUS'] !== 'Confirmed') {
                throw new Exception("Cannot check out. Reservation must be confirmed first.");
            }
            
            $stmt = $this->conn->prepare("UPDATE tblreservation SET STATUS = 'Checked Out', CHECKOUT_DATE = NOW(), CHECKOUT_BY = ? WHERE RESERVEID = ?");
            $checkoutBy = $_SESSION['username'] ?? 'System';
            $stmt->bind_param("si", $checkoutBy, $reserveId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update reservation status.");
            }
            $stmt->close();
            
            $this->updateRoomAvailability($reservation['ROOMID'], 1);
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Check out error: " . $e->getMessage());
            return false;
        }
    }
}