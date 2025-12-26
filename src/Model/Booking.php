<?php
// =============================================================================
// src/Model/Booking.php - COMPLETE VERSION WITH ALL METHODS
// =============================================================================

namespace Lourdian\MonbelaHotel\Model;

use Lourdian\MonbelaHotel\Core\Database;
use Exception;

class Booking extends Database
{
    /**
     * Create a new booking.
     */
    public function create($data)
    {
        error_log("Booking create called with data: " . print_r($data, true));
        
        $this->conn->begin_transaction();
        try {
            // Validate input
            if (empty($data['ROOMID'])) {
                throw new Exception("ROOMID is missing or empty");
            }
            if (empty($data['ARRIVAL'])) {
                throw new Exception("ARRIVAL date is missing or empty");
            }
            if (empty($data['DEPARTURE'])) {
                throw new Exception("DEPARTURE date is missing or empty");
            }
            if (empty($data['GUESTID'])) {
                throw new Exception("GUESTID is missing or empty");
            }

            // Convert dates to datetime format
            $arrival = $data['ARRIVAL'];
            $departure = $data['DEPARTURE'];
            
            if (strlen($arrival) == 10) {
                $arrival .= ' 14:00:00';
            }
            if (strlen($departure) == 10) {
                $departure .= ' 12:00:00';
            }
            
            error_log("Formatted dates - Arrival: {$arrival}, Departure: {$departure}");

            // Check room availability
            $isAvailable = $this->isRoomAvailable($data['ROOMID'], $arrival, $departure);
            error_log("Room availability check: " . ($isAvailable ? "Available" : "Not Available"));
            
            if (!$isAvailable) {
                throw new Exception("Room is not available for the selected dates.");
            }

            // Get room details for price calculation
            $roomStmt = $this->conn->prepare("SELECT PRICE FROM tblroom WHERE ROOMID = ?");
            $roomStmt->bind_param("i", $data['ROOMID']);
            $roomStmt->execute();
            $roomResult = $roomStmt->get_result();
            $room = $roomResult->fetch_assoc();
            $roomStmt->close();
            
            if (!$room) {
                throw new Exception("Room not found with ID: " . $data['ROOMID']);
            }
            
            error_log("Room price: " . $room['PRICE']);

            // Calculate total price
            $arrivalDate = new \DateTime($arrival);
            $departureDate = new \DateTime($departure);
            $nights = $arrivalDate->diff($departureDate)->days;
            
            if ($nights <= 0) {
                throw new Exception("Invalid date range: departure must be after arrival");
            }
            
            $totalPrice = $room['PRICE'] * $nights;
            error_log("Price calculation: {$room['PRICE']} x {$nights} nights = {$totalPrice}");

            // Generate confirmation code
            $confirmationCode = 'MBH-' . date('YmdHis') . '-' . rand(100, 999);
            error_log("Generated confirmation code: {$confirmationCode}");

            // Insert into database
            $sql = "INSERT INTO tblreservation
                    (CONFIRMATIONCODE, TRANSDATE, ROOMID, ARRIVAL, DEPARTURE, RPRICE, GUESTID, PRORPOSE, STATUS, BOOKDATE, REMARKS, USERID)
                    VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $roomId = (int)$data['ROOMID'];
            $guestId = (int)$data['GUESTID'];
            $userId = isset($data['USERID']) ? (int)$data['USERID'] : $guestId;
            $purpose = $data['PRORPOSE'] ?? 'Online Booking';
            $remarks = $data['REMARKS'] ?? '';
            
            error_log("Binding parameters: ConfCode={$confirmationCode}, RoomID={$roomId}, Arrival={$arrival}, Departure={$departure}, Price={$totalPrice}, GuestID={$guestId}, Purpose={$purpose}, Remarks={$remarks}, UserID={$userId}");
            
            $stmt->bind_param(
                "sissdissi",
                $confirmationCode,
                $roomId,
                $arrival,
                $departure,
                $totalPrice,
                $guestId,
                $purpose,
                $remarks,
                $userId
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $insertedId = $this->conn->insert_id;
            error_log("Booking inserted successfully with ID: {$insertedId}");
            $stmt->close();

            $this->conn->commit();
            error_log("Transaction committed successfully. Confirmation code: {$confirmationCode}");
            return $confirmationCode;

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Booking create error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Simplified room availability check
     */
    private function isRoomAvailableSimple($roomId, $arrival, $departure, $excludeBookingId = null)
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM tblreservation
                    WHERE ROOMID = ?
                    AND STATUS IN ('Pending', 'Confirmed')
                    AND (
                        (ARRIVAL < ? AND DEPARTURE > ?) OR
                        (ARRIVAL >= ? AND ARRIVAL < ?) OR
                        (DEPARTURE > ? AND DEPARTURE <= ?)
                    )";

            $params = [$roomId, $departure, $arrival, $arrival, $departure, $arrival, $departure];
            $types = "issssss";

            if ($excludeBookingId) {
                $sql .= " AND RESERVEID != ?";
                $types .= "i";
                $params[] = $excludeBookingId;
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['count'];
            $stmt->close();

            error_log("Overlapping bookings found: {$count}");
            return $count == 0;
        } catch (Exception $e) {
            error_log("Check availability error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * PUBLIC METHOD: Check if a room is available for given dates
     */
    public function isRoomAvailable($roomId, $arrival, $departure, $excludeBookingId = null)
    {
        // Add datetime formatting if needed
        if (strlen($arrival) == 10) {
            $arrival .= ' 14:00:00';
        }
        if (strlen($departure) == 10) {
            $departure .= ' 12:00:00';
        }
        
        return $this->isRoomAvailableSimple($roomId, $arrival, $departure, $excludeBookingId);
    }

    /**
     * Get all bookings for a guest
     */
    public function getByGuestId($guestId)
    {
        try {
            $sql = "SELECT
                        r.*,
                        ro.ROOM,
                        ro.ROOMNUM,
                        ro.NUMPERSON
                    FROM
                        tblreservation r
                    LEFT JOIN
                        tblroom ro ON r.ROOMID = ro.ROOMID
                    WHERE
                        r.GUESTID = ?
                    ORDER BY
                        r.BOOKDATE DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $guestId);
            $stmt->execute();
            $result = $stmt->get_result();
            $bookings = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return $bookings;
        } catch (Exception $e) {
            error_log("Get bookings error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get booking by ID
     */
    public function getById($bookingId)
    {
        try {
            $sql = "SELECT
                        r.*,
                        g.G_FNAME, g.G_LNAME, g.G_EMAIL,
                        ro.ROOM, ro.ROOMNUM, ro.PRICE, ro.NUMPERSON
                    FROM
                        tblreservation r
                    LEFT JOIN
                        tblguest g ON r.GUESTID = g.GUESTID
                    LEFT JOIN
                        tblroom ro ON r.ROOMID = ro.ROOMID
                    WHERE
                        r.RESERVEID = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
            $result = $stmt->get_result();
            $booking = $result->fetch_assoc();
            $stmt->close();

            return $booking;
        } catch (Exception $e) {
            error_log("Get booking error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cancel booking (simple version)
     */
    public function cancelReservation($reserveId)
    {
        try {
            $stmt = $this->conn->prepare("UPDATE tblreservation SET STATUS = 'Cancelled' WHERE RESERVEID = ?");
            $stmt->bind_param("i", $reserveId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Cancel reservation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Schedule a cancellation for 3 hours later
     */
    public function scheduleCancellation($reserveId)
    {
        try {
            // First check if the reservation exists and is confirmed
            $stmt = $this->conn->prepare("SELECT STATUS FROM tblreservation WHERE RESERVEID = ?");
            $stmt->bind_param("i", $reserveId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                return false;
            }
            
            $row = $result->fetch_assoc();
            $stmt->close();
            
            if ($row['STATUS'] !== 'Confirmed') {
                return false;
            }
            
            // Calculate cancellation time (3 hours from now)
            $cancellationTime = date('Y-m-d H:i:s', strtotime('+3 hours'));
            
            // Update the reservation status to PENDING_CANCELLATION and set the cancellation time
            $stmt = $this->conn->prepare("UPDATE tblreservation SET STATUS = 'Pending Cancellation', CANCELLATION_TIME = ? WHERE RESERVEID = ?");
            $stmt->bind_param("si", $cancellationTime, $reserveId);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch (Exception $e) {
            error_log("Schedule cancellation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Undo a scheduled cancellation
     */
    public function undoCancellation($reserveId)
    {
        try {
            // Update the reservation status back to CONFIRMED and clear the cancellation time
            $stmt = $this->conn->prepare("UPDATE tblreservation SET STATUS = 'Confirmed', CANCELLATION_TIME = NULL WHERE RESERVEID = ? AND STATUS = 'Pending Cancellation'");
            $stmt->bind_param("i", $reserveId);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch (Exception $e) {
            error_log("Undo cancellation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process pending cancellations (this should be run by a cron job)
     */
    public function processPendingCancellations()
    {
        try {
            $currentTime = date('Y-m-d H:i:s');
            
            // Find all reservations that are pending cancellation and whose cancellation time has passed
            $stmt = $this->conn->prepare("SELECT RESERVEID FROM tblreservation WHERE STATUS = 'Pending Cancellation' AND CANCELLATION_TIME <= ?");
            $stmt->bind_param("s", $currentTime);
            $stmt->execute();
            $result = $stmt->get_result();
            $reservations = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            $cancelledCount = 0;
            foreach ($reservations as $reservation) {
                // Cancel the reservation
                if ($this->cancelReservation($reservation['RESERVEID'])) {
                    $cancelledCount++;
                }
            }
            
            return $cancelledCount;
        } catch (Exception $e) {
            error_log("Process pending cancellations error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get booking by ID and guest ID
     */
    public function getByIdAndGuestId($reservationId, $guestId)
    {
        try {
            $sql = "SELECT r.*, ro.ROOM, ro.ROOMNUM, ro.PRICE, ro.NUMPERSON
                    FROM tblreservation r
                    LEFT JOIN tblroom ro ON r.ROOMID = ro.ROOMID
                    WHERE r.RESERVEID = ? AND r.GUESTID = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $reservationId, $guestId);
            $stmt->execute();
            $result = $stmt->get_result();
            $booking = $result->fetch_assoc();
            $stmt->close();
            
            return $booking;
        } catch (Exception $e) {
            error_log("Get booking by ID and guest ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update booking status
     */
    public function updateStatus($bookingId, $status)
    {
        try {
            $stmt = $this->conn->prepare("UPDATE tblreservation SET STATUS = ? WHERE RESERVEID = ?");
            $stmt->bind_param("si", $status, $bookingId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Update booking status error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all bookings for admin dashboard
     */
    public function getAll()
    {
        try {
            $sql = "SELECT
                        r.*,
                        g.G_FNAME, g.G_LNAME,
                        ro.ROOM, ro.ROOMNUM
                    FROM
                        tblreservation r
                    LEFT JOIN
                        tblguest g ON r.GUESTID = g.GUESTID
                    LEFT JOIN
                        tblroom ro ON r.ROOMID = ro.ROOMID
                    ORDER BY
                        r.BOOKDATE DESC";

            $result = $this->conn->query($sql);
            if (!$result) {
                throw new Exception("Query failed: " . $this->conn->error);
            }
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Get all bookings error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cancel a booking
     */
    public function cancel($bookingId)
    {
        return $this->updateStatus($bookingId, 'Cancelled');
    }

    /**
     * Confirm a booking
     */
    public function confirm($bookingId)
    {
        return $this->updateStatus($bookingId, 'Confirmed');
    }

    /**
     * Update booking details
     */
    public function update($reservationId, $data)
    {
        try {
            $setParts = [];
            $types = '';
            $params = [];

            foreach ($data as $key => $value) {
                if (in_array($key, ['ARRIVAL', 'DEPARTURE', 'REMARKS'])) {
                    $setParts[] = "$key = ?";
                    $types .= 's';
                    $params[] = $value;
                }
            }

            if (empty($setParts)) {
                return true;
            }

            $sql = "UPDATE tblreservation SET " . implode(', ', $setParts) . " WHERE RESERVEID = ?";

            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $types .= 'i';
            $params[] = $reservationId;

            $stmt->bind_param($types, ...$params);
            $result = $stmt->execute();
            $stmt->close();

            return $result;
        } catch (Exception $e) {
            error_log("Update reservation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing reservation with room change support
     */
    public function updateReservation($reserveId, $data)
    {
        $this->conn->begin_transaction();
        try {
            // Get current reservation
            $currentReservation = $this->getById($reserveId);
            if (!$currentReservation) {
                throw new Exception("Reservation not found.");
            }

            // Add datetime formatting if needed
            $arrival = $data['ARRIVAL'];
            $departure = $data['DEPARTURE'];
            if (strlen($arrival) == 10) {
                $arrival .= ' 14:00:00';
            }
            if (strlen($departure) == 10) {
                $departure .= ' 12:00:00';
            }

            // Check availability
            if (!$this->isRoomAvailable($data['ROOMID'], $arrival, $departure, $reserveId)) {
                throw new Exception("Room is not available for the selected dates.");
            }

            // Get room price
            $roomStmt = $this->conn->prepare("SELECT PRICE FROM tblroom WHERE ROOMID = ?");
            $roomStmt->bind_param("i", $data['ROOMID']);
            $roomStmt->execute();
            $roomResult = $roomStmt->get_result();
            $room = $roomResult->fetch_assoc();
            $roomStmt->close();
            
            if (!$room) {
                throw new Exception("Room not found.");
            }

            // Calculate new price
            $arrivalDate = new \DateTime($arrival);
            $departureDate = new \DateTime($departure);
            $nights = $arrivalDate->diff($departureDate)->days;
            $newPrice = $room['PRICE'] * $nights;

            // Update reservation
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
            $roomId = (int)$data['ROOMID'];

            $stmt->bind_param(
                "issdssi",
                $roomId,
                $arrival,
                $departure,
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
}