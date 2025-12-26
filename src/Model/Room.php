<?php
// =============================================================================
// src/Model/Room.php - Room Model (FIXED: Foreign key constraint issue)
// =============================================================================

namespace Lourdian\MonbelaHotel\Model;

use Lourdian\MonbelaHotel\Core\Database;
use Exception;

class Room extends Database
{
    // Amenity mapping for display
    private $amenityDisplayNames = [
        'wifi' => 'WiFi',
        'ac' => 'Air Conditioning',
        'tv' => 'Smart TV',
        'balcony' => 'Balcony',
        'bathtub' => 'Bathtub',
        'coffee' => 'Coffee Maker',
    ];

    /**
     * Get all available rooms
     */
    public function getAvailableRooms()
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tblroom ORDER BY ROOMNUM ASC");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $rooms = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            return $rooms;
        } catch(Exception $e) {
            error_log("Get available rooms error: ".$e->getMessage());
            return [];
        }
    }

    /**
     * Get room by ID (alias for compatibility)
     */
    public function getRoomById($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tblroom WHERE ROOMID = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $room = $result->fetch_assoc();
            
            // Process amenities
            if ($room && isset($room['amenities'])) {
                $room['amenities_list'] = $this->parseAmenities($room['amenities']);
            }
            
            return $room;
        } catch (Exception $e) {
            error_log("Get room by ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * A simpler version that only gets data from the tblroom table.
     */
    public function getById($roomId)
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tblroom WHERE ROOMID = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $roomId);
            $stmt->execute();
            $result = $stmt->get_result();
            $room = $result->fetch_assoc();
            $stmt->close();
            
            // Process amenities
            if ($room && isset($room['amenities'])) {
                $room['amenities_list'] = $this->parseAmenities($room['amenities']);
            }
            
            return $room;
        } catch(Exception $e) {
            error_log("Get room error: ".$e->getMessage());
            return null;
        }
    }

    /**
     * Get all rooms
     */
    public function getAll()
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tblroom ORDER BY ROOMNUM ASC");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $rooms = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            return $rooms;
        } catch(Exception $e) {
            error_log("Get all rooms error: ".$e->getMessage());
            return [];
        }
    }

    /**
     * Get all rooms with proper column mapping for manage_rooms.php
     * UPDATED: Includes amenities column
     */
    public function getAllRooms()
    {
        try {
            // Ensure amenities column exists
            $this->ensureAmenitiesColumnExists();
            
            $sql = "SELECT 
                        ROOMID,
                        ROOMNUM,
                        ROOM,
                        ROOMDESC,
                        NUMPERSON,
                        PRICE,
                        ROOMIMAGE,
                        ACCOMID,
                        amenities,
                        IFNULL(ROOM_STATUS, 'Available') as ROOM_STATUS
                    FROM tblroom 
                    ORDER BY ROOMNUM ASC";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $rooms = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Process amenities for each room
            foreach ($rooms as &$room) {
                $room['amenities_list'] = $this->parseAmenities($room['amenities'] ?? '');
            }
            
            return $rooms;
        } catch(Exception $e) {
            error_log("Get all rooms error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse amenities JSON string into array
     */
    public function parseAmenities($amenitiesJson)
    {
        if (empty($amenitiesJson)) {
            return [];
        }
        
        // If it's already an array, return it
        if (is_array($amenitiesJson)) {
            return $amenitiesJson;
        }
        
        $amenities = json_decode($amenitiesJson, true);
        
        if (!is_array($amenities)) {
            // Try to handle string format (comma-separated)
            if (is_string($amenitiesJson) && strpos($amenitiesJson, '[') === false) {
                $amenities = array_map('trim', explode(',', $amenitiesJson));
            } else {
                return [];
            }
        }
        
        return $amenities;
    }

    /**
     * Get room amenities
     */
    public function getRoomAmenities($roomId)
    {
        try {
            // Ensure amenities column exists
            $this->ensureAmenitiesColumnExists();
            
            $stmt = $this->conn->prepare("SELECT amenities FROM tblroom WHERE ROOMID = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $roomId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            if ($row && !empty($row['amenities'])) {
                return $this->parseAmenities($row['amenities']);
            }
            
            return [];
        } catch(Exception $e) {
            error_log("Get room amenities error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get formatted amenities for display
     */
    public function getFormattedAmenities($amenitiesArray)
    {
        $formatted = [];
        if (!is_array($amenitiesArray)) {
            return $formatted;
        }
        
        foreach ($amenitiesArray as $amenityKey) {
            if (isset($this->amenityDisplayNames[$amenityKey])) {
                $formatted[] = $this->amenityDisplayNames[$amenityKey];
            } else {
                $formatted[] = ucfirst(str_replace('_', ' ', $amenityKey));
            }
        }
        return $formatted;
    }

    /**
     * Ensure amenities column exists in database
     */
    public function ensureAmenitiesColumnExists()
    {
        try {
            $checkColumn = $this->conn->query("SHOW COLUMNS FROM tblroom LIKE 'amenities'");
            
            if ($checkColumn->num_rows == 0) {
                // Add the amenities column
                $sql = "ALTER TABLE tblroom ADD COLUMN amenities TEXT DEFAULT NULL AFTER ROOMIMAGE";
                if ($this->conn->query($sql)) {
                    error_log("✅ Added amenities column to tblroom table");
                    return true;
                } else {
                    error_log("❌ Error adding amenities column: " . $this->conn->error);
                    return false;
                }
            }
            
            return true; // Column already exists
        } catch (Exception $e) {
            error_log("Error checking/creating amenities column: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAllAccommodations()
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tblaccomodation ORDER BY ACCOMODATION ASC");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $accommodations = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            return $accommodations;
        } catch(Exception $e) {
            error_log("Get accommodations error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add room - UPDATED: Handle amenities
     */
    public function addRoom($data)
    {
        try {
            // Ensure amenities column exists
            $this->ensureAmenitiesColumnExists();
            
            if (!isset($data['ROOMIMAGE']) || $data['ROOMIMAGE'] === null) {
                $data['ROOMIMAGE'] = '';
            }

            // Handle amenities - convert array to JSON if present
            $amenitiesJson = null;
            if (isset($data['amenities']) && !empty($data['amenities'])) {
                if (is_array($data['amenities'])) {
                    $amenitiesJson = json_encode($data['amenities']);
                } else {
                    $amenitiesJson = $data['amenities']; // Already JSON string
                }
            }
            
            // Use default status if not provided
            $status = isset($data['ROOM_STATUS']) ? $data['ROOM_STATUS'] : 'Available';
            
            // Handle OROOMNUM if present (for available units)
            $oroomnum = isset($data['OROOMNUM']) ? $data['OROOMNUM'] : 1;
            
            $sql = "INSERT INTO tblroom 
                    (ROOMNUM, ROOM, ACCOMID, ROOMDESC, NUMPERSON, PRICE, ROOMIMAGE, amenities, ROOM_STATUS) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param(
                "sisidssss",
                $data['ROOMNUM'],
                $data['ROOM'],
                $data['ACCOMID'],
                $data['ROOMDESC'],
                $data['NUMPERSON'],
                $data['PRICE'],
                $data['ROOMIMAGE'],
                $amenitiesJson,
                $status
            );

            $result = $stmt->execute();
            $roomId = $this->conn->insert_id;
            $stmt->close();
            
            return $roomId;
        } catch(Exception $e) {
            error_log("Add room error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update room - UPDATED: Handle amenities
     */
    public function updateRoom($roomId, $data)
    {
        try {
            // Ensure amenities column exists
            $this->ensureAmenitiesColumnExists();
            
            // CRITICAL FIX: Ensure ROOMIMAGE has a value
            if (!isset($data['ROOMIMAGE']) || $data['ROOMIMAGE'] === null) {
                $data['ROOMIMAGE'] = ''; // Use empty string instead of null
            }

            // Handle amenities - convert array to JSON if present
            $amenitiesJson = null;
            if (isset($data['amenities'])) {
                if (is_array($data['amenities']) && !empty($data['amenities'])) {
                    $amenitiesJson = json_encode($data['amenities']);
                } elseif (is_string($data['amenities']) && !empty($data['amenities'])) {
                    $amenitiesJson = $data['amenities']; // Already JSON string
                }
            }
            
            // Use default status if not provided
            $status = isset($data['ROOM_STATUS']) ? $data['ROOM_STATUS'] : 'Available';

            $sql = "UPDATE tblroom SET 
                    ROOMNUM = ?,
                    ROOM = ?,
                    ACCOMID = ?,
                    ROOMDESC = ?,
                    NUMPERSON = ?,
                    PRICE = ?,
                    ROOMIMAGE = ?,
                    amenities = ?,
                    ROOM_STATUS = ?
                    WHERE ROOMID = ?";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param(
                "siidsssssi",
                $data['ROOMNUM'],      // string
                $data['ROOM'],         // string
                $data['ACCOMID'],      // integer
                $data['ROOMDESC'],     // string
                $data['NUMPERSON'],    // integer
                $data['PRICE'],        // double
                $data['ROOMIMAGE'],    // string
                $amenitiesJson,        // string (JSON)
                $status,               // string
                $roomId                // integer
            );

            $result = $stmt->execute();
            
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
            
            return $result;
        } catch(Exception $e) {
            error_log("Update room error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete room - UPDATED: Handle foreign key constraint safely
     */
    public function deleteRoom($roomId)
    {
        try {
            // First check if there are any reservations for this room
            $checkStmt = $this->conn->prepare("SELECT COUNT(*) as count FROM tblreservation WHERE ROOMID = ?");
            if (!$checkStmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $checkStmt->bind_param("i", $roomId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            $checkStmt->close();
            
            if ($row['count'] > 0) {
                // There are reservations for this room, so we can't delete it
                throw new Exception("Cannot delete room: There are {$row['count']} reservation(s) associated with this room. Please delete or modify these reservations first.");
            }
            
            // No reservations exist, so we can safely delete the room
            $stmt = $this->conn->prepare("DELETE FROM tblroom WHERE ROOMID = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $roomId);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch(Exception $e) {
            error_log("Delete room error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * NEW METHOD: Delete room and all associated reservations
     * Use this method if you want to allow deletion of rooms with reservations
     */
    public function deleteRoomWithReservations($roomId)
    {
        try {
            // Begin transaction to ensure both operations succeed or fail together
            $this->conn->begin_transaction();
            
            // First delete all reservations for this room
            $reservStmt = $this->conn->prepare("DELETE FROM tblreservation WHERE ROOMID = ?");
            if (!$reservStmt) {
                throw new Exception("Prepare failed for reservations: " . $this->conn->error);
            }
            
            $reservStmt->bind_param("i", $roomId);
            $reservResult = $reservStmt->execute();
            $reservStmt->close();
            
            if (!$reservResult) {
                throw new Exception("Failed to delete reservations: " . $this->conn->error);
            }
            
            // Now delete the room
            $roomStmt = $this->conn->prepare("DELETE FROM tblroom WHERE ROOMID = ?");
            if (!$roomStmt) {
                throw new Exception("Prepare failed for room: " . $this->conn->error);
            }
            
            $roomStmt->bind_param("i", $roomId);
            $roomResult = $roomStmt->execute();
            $roomStmt->close();
            
            if (!$roomResult) {
                throw new Exception("Failed to delete room: " . $this->conn->error);
            }
            
            // Commit the transaction
            $this->conn->commit();
            
            return true;
        } catch(Exception $e) {
            // Rollback in case of error to maintain data integrity
            $this->conn->rollback();
            error_log("Delete room with reservations error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if room number exists (for validation)
     */
    public function roomNumberExists($roomNum, $excludeId = null)
    {
        try {
            $sql = "SELECT ROOMID FROM tblroom WHERE ROOMNUM = ?";
            $types = "s";  // Changed from "i" to "s" since ROOMNUM might be string
            $params = [$roomNum];
            
            if ($excludeId !== null) {
                $sql .= " AND ROOMID != ?";
                $types .= "i";
                $params[] = $excludeId;
            }
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->num_rows > 0;
            $stmt->close();
            
            return $exists;
        } catch(Exception $e) {
            error_log("Check room number exists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get rooms by accommodation type
     */
    public function getRoomsByAccommodation($accomId)
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tblroom WHERE ACCOMID = ? ORDER BY ROOMNUM ASC");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $accomId);
            $stmt->execute();
            $result = $stmt->get_result();
            $rooms = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Add image path to each room
            foreach ($rooms as &$room) {
                $room['IMAGE_PATH'] = $this->getRoomImagePath($room['ROOMIMAGE'] ?? '');
                // Process amenities
                $room['amenities_list'] = $this->parseAmenities($room['amenities'] ?? '');
            }
            
            return $rooms;
        } catch(Exception $e) {
            error_log("Get rooms by accommodation error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Search rooms
     */
    public function searchRooms($keyword)
    {
        try {
            $sql = "SELECT * FROM tblroom 
                    WHERE ROOMNUM LIKE ? OR ROOM LIKE ? OR ROOMDESC LIKE ?
                    ORDER BY ROOMNUM ASC";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $searchTerm = "%$keyword%";
            $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
            $stmt->execute();
            $result = $stmt->get_result();
            $rooms = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            return $rooms;
        } catch(Exception $e) {
            error_log("Search rooms error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get room statistics
     */
    public function getRoomStats()
    {
        try {
            $stats = [];
            
            // Total rooms
            $result = $this->conn->query("SELECT COUNT(*) as total FROM tblroom");
            $stats['total'] = $result ? $result->fetch_assoc()['total'] : 0;
            
            // Average price
            $result = $this->conn->query("SELECT AVG(PRICE) as avg_price FROM tblroom");
            $stats['avg_price'] = $result ? ($result->fetch_assoc()['avg_price'] ?? 0) : 0;
            
            return $stats;
        } catch(Exception $e) {
            error_log("Get room stats error: " . $e->getMessage());
            return [];
        }
    }

    public function getRoomImagePath($roomImage) {
        if (empty($roomImage)) {
            return 'https://via.placeholder.com/400x300?text=Room';
        }
        
        $possiblePaths = [
            '../uploads/rooms/' . $roomImage,
            '../images/images/' . $roomImage,
            '../images/' . $roomImage
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists(__DIR__ . '/../../public/' . $path)) {
                return $path;
            }
        }
        
        return 'https://via.placeholder.com/400x300?text=Room';
    }

    /**
     * Add room with history and amenities
     */
    public function addRoomWithHistory($data, $userId = null, $userName = null, $action = 'New room added') {
        $this->conn->begin_transaction();
        try {
            // First add the room
            $roomId = $this->addRoom($data);
            
            if (!$roomId) {
                throw new Exception("Failed to add room");
            }
            
            // Log the creation in history if RoomHistory class exists
            if (class_exists('Lourdian\MonbelaHotel\Model\RoomHistory')) {
                $history = new RoomHistory();
                $history->logRoomCreated($roomId, $data['ROOMNUM'], $userId, $userName, $action);
            }
            
            $this->conn->commit();
            return $roomId;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * Update room with history and amenities
     */
    public function updateRoomWithHistory($roomId, $data, $userId = null, $userName = null) {
        $this->conn->begin_transaction();
        try {
            // Get current room data for comparison
            $currentRoom = $this->getRoomById($roomId);
            
            // Update the room
            $result = $this->updateRoom($roomId, $data);
            
            // Track what changed
            $changes = [];
            if ($currentRoom['ROOMNUM'] != $data['ROOMNUM']) {
                $changes[] = "Room number changed from {$currentRoom['ROOMNUM']} to {$data['ROOMNUM']}";
            }
            if ($currentRoom['PRICE'] != $data['PRICE']) {
                $changes[] = "Price changed from ₱{$currentRoom['PRICE']} to ₱{$data['PRICE']}";
            }
            if (isset($data['ROOM_STATUS']) && $currentRoom['ROOM_STATUS'] != $data['ROOM_STATUS']) {
                $changes[] = "Status changed from {$currentRoom['ROOM_STATUS']} to {$data['ROOM_STATUS']}";
            }
            
            // Check amenities changes
            $currentAmenities = $this->parseAmenities($currentRoom['amenities'] ?? '');
            $newAmenities = isset($data['amenities']) ? $this->parseAmenities($data['amenities']) : [];
            
            if (count(array_diff($currentAmenities, $newAmenities)) > 0 || count(array_diff($newAmenities, $currentAmenities)) > 0) {
                $changes[] = "Amenities updated";
            }
            
            // Log the update in history if RoomHistory class exists
            if (!empty($changes) && class_exists('Lourdian\MonbelaHotel\Model\RoomHistory')) {
                $history = new RoomHistory();
                $history->logRoomUpdated($roomId, $data['ROOMNUM'], $changes, $userId, $userName);
            }
            
            $this->conn->commit();
            return $result;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * Update room status (Available or Maintenance)
     */
    public function updateRoomStatus($roomId, $status) {
        $this->conn->begin_transaction();
        try {
            // First check if ROOM_STATUS column exists, if not add it
            $checkColumn = "SHOW COLUMNS FROM tblroom LIKE 'ROOM_STATUS'";
            $result = $this->conn->query($checkColumn);
            
            if ($result->num_rows == 0) {
                // Add the ROOM_STATUS column if it doesn't exist
                $addColumn = "ALTER TABLE tblroom ADD COLUMN ROOM_STATUS VARCHAR(50) DEFAULT 'Available'";
                $this->conn->query($addColumn);
            }
            
            // If setting to maintenance, we need to check for current reservations
            if (strtolower($status) === 'maintenance') {
                // Check if there are any active reservations
                $checkReservations = "SELECT COUNT(*) as count FROM tblreservation 
                                     WHERE ROOMID = ? AND STATUS IN ('Pending', 'Confirmed') 
                                     AND DEPARTURE > CURDATE()";
                $stmt = $this->conn->prepare($checkReservations);
                $stmt->bind_param("i", $roomId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();
                
                if ($row['count'] > 0) {
                    // There are active reservations - we should cancel them or warn the user
                    throw new Exception("Cannot set room to maintenance: There are {$row['count']} active reservation(s).");
                }
            }
            
            // Update the room status
            $sql = "UPDATE tblroom SET ROOM_STATUS = ? WHERE ROOMID = ?";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("si", $status, $roomId);
            $result = $stmt->execute();
            
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Update room status error: " . $e->getMessage());
            throw $e; // Re-throw to handle in calling code
        }
    }

    /**
     * Check if a room number already exists (excluding a specific room ID for updates)
     */
    public function isRoomNumberExists($roomNumber, $excludeRoomId = null)
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM tblroom WHERE ROOMNUM = ?";
            $params = [$roomNumber];
            $types = "s";
            
            if ($excludeRoomId !== null) {
                $sql .= " AND ROOMID != ?";
                $types .= "i";
                $params[] = $excludeRoomId;
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
            
            return $count > 0;
            
        } catch (Exception $e) {
            error_log("Check room number exists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get available rooms for specific dates (excluding already booked rooms)
     */
    public function getAvailableRoomsByDate($checkin, $checkout)
    {
        try {
            // First, get all rooms
            $sql = "SELECT r.*, a.ACCOMODATION 
                    FROM tblroom r 
                    LEFT JOIN tblaccomodation a ON r.ACCOMID = a.ACCOMID 
                    ORDER BY r.ROOMNUM ASC";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $allRooms = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // If no rooms at all, return empty array
            if (empty($allRooms)) {
                return [];
            }
            
            // Now get rooms that are booked during the selected dates
            $sql = "SELECT DISTINCT rv.ROOMID 
                    FROM tblreservation rv 
                    WHERE rv.STATUS != 'Cancelled' 
                    AND (
                        (rv.ARRIVAL <= ? AND rv.DEPARTURE >= ?) OR
                        (rv.ARRIVAL >= ? AND rv.ARRIVAL < ?) OR
                        (rv.DEPARTURE > ? AND rv.DEPARTURE <= ?)
                    )";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param("ssssss", $checkout, $checkin, $checkin, $checkout, $checkin, $checkout);
            $stmt->execute();
            $result = $stmt->get_result();
            $bookedRoomIds = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Extract booked room IDs
            $bookedIds = array_column($bookedRoomIds, 'ROOMID');
            
            // Filter available rooms (rooms not in bookedIds)
            $availableRooms = array_filter($allRooms, function($room) use ($bookedIds) {
                return !in_array($room['ROOMID'], $bookedIds);
            });
            
            // Process amenities for each room
            foreach ($availableRooms as &$room) {
                $room['amenities_list'] = $this->parseAmenities($room['amenities'] ?? '');
            }
            
            // Reset array keys
            return array_values($availableRooms);
            
        } catch(Exception $e) {
            error_log("Get available rooms by date error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get available rooms with filters
     */
    public function getAvailableRoomsWithFilters($accomId = null, $checkin = null, $checkout = null) {
        try {
            $sql = "SELECT r.*, a.ACCOMODATION 
                    FROM tblroom r
                    LEFT JOIN tblaccomodation a ON r.ACCOMID = a.ACCOMID
                    WHERE 1=1";
            
            $params = [];
            $types = "";
            
            if ($accomId) {
                $sql .= " AND r.ACCOMID = ?";
                $params[] = $accomId;
                $types .= "i";
            }
            
            if ($checkin && $checkout) {
                // Add datetime formatting if needed
                if (strlen($checkin) == 10) {
                    $checkin .= ' 14:00:00';
                }
                if (strlen($checkout) == 10) {
                    $checkout .= ' 12:00:00';
                }
                
                $sql .= " AND r.ROOMID NOT IN (
                    SELECT res.ROOMID 
                    FROM tblreservation res
                    WHERE res.STATUS IN ('Pending', 'Confirmed')
                    AND (
                        (res.ARRIVAL < ? AND res.DEPARTURE > ?) OR
                        (res.ARRIVAL >= ? AND res.ARRIVAL < ?) OR
                        (res.DEPARTURE > ? AND res.DEPARTURE <= ?)
                    )
                )";
                
                $params = array_merge($params, [$checkout, $checkin, $checkin, $checkout, $checkin, $checkout]);
                $types .= str_repeat("s", 6);
            }
            
            $sql .= " ORDER BY r.PRICE ASC";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $rooms = [];
            while ($row = $result->fetch_assoc()) {
                // Process amenities
                $row['amenities_list'] = $this->parseAmenities($row['amenities'] ?? '');
                $rooms[] = $row;
            }
            
            $stmt->close();
            return $rooms;
            
        } catch (Exception $e) {
            error_log("Error fetching available rooms with filters: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all available amenities
     */
    public function getAllAvailableAmenities()
    {
        return array_keys($this->amenityDisplayNames);
    }

    /**
     * Get amenity display name
     */
    public function getAmenityDisplayName($amenityKey)
    {
        return $this->amenityDisplayNames[$amenityKey] ?? ucfirst(str_replace('_', ' ', $amenityKey));
    }
    
}

class RoomHistory extends Database {
    public function logRoomCreated($roomId, $roomNumber, $userId = null, $userName = null, $action = 'New room added') {
        try {
            $sql = "INSERT INTO room_history (room_id, room_number, action, user_id, user_name, timestamp) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("issis", $roomId, $roomNumber, $action, $userId, $userName);
            $stmt->execute();
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Log room created error: " . $e->getMessage());
            return false;
        }
    }
    
    public function logRoomUpdated($roomId, $roomNumber, $changes, $userId = null, $userName = null) {
        try {
            $action = "Room updated: " . implode(", ", $changes);
            $sql = "INSERT INTO room_history (room_id, room_number, action, user_id, user_name, timestamp) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("issis", $roomId, $roomNumber, $action, $userId, $userName);
            $stmt->execute();
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Log room updated error: " . $e->getMessage());
            return false;
        }
    }
}