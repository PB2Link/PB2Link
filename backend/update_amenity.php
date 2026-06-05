<?php 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../db_connection.php'; 

// Get the POST data from React 
$data = json_decode(file_get_contents("php://input"), true); 

if(isset($data['request_id']) && isset($data['status'])) { 
    $id = $conn->real_escape_string($data['request_id']); 
    $status = $conn->real_escape_string($data['status']); 

    // Auto Reject other residents when one is approved
    if (isset($data['extend_hours']) && intval($data['extend_hours']) > 0) {
        $hours = intval($data['extend_hours']);
        $sql = "UPDATE req_amenity_reservation 
                SET time_slot = CONCAT(time_slot, ' (+', $hours, ' Hrs OT)') 
                WHERE request_id = '$id'";
    } else {
        // Standard operational pathway (Approve, Decline, Complete shifts)
        $sql = "UPDATE req_amenity_reservation SET status = '$status' WHERE request_id = '$id'"; 
    }
    
    if ($conn->query($sql) === TRUE) { 
        
        // ------------------------------------------------------------------------
        // RESTRICTION: CASCADE REJECTION ON APPROVAL
        // ------------------------------------------------------------------------
        // Only trigger cascade rejection on standard manual approval actions
        // ------------------------------------------------------------------------
        if ($status === 'Approved' && !isset($data['extend_hours'])) {
            $cascadeSql = "UPDATE req_amenity_reservation 
                           SET status = 'Declined' 
                           WHERE status = 'Pending' 
                             AND request_id != '$id'
                             AND venue = (
                                 SELECT venue FROM (
                                     SELECT venue FROM req_amenity_reservation WHERE request_id = '$id'
                                 ) AS temp_v
                             )
                             AND reservation_date = (
                                 SELECT reservation_date FROM (
                                     SELECT reservation_date FROM req_amenity_reservation WHERE request_id = '$id'
                                 ) AS temp_d
                             )
                             AND time_slot = (
                                 SELECT time_slot FROM (
                                     SELECT time_slot FROM req_amenity_reservation WHERE request_id = '$id'
                                 ) AS temp_t
                             )";
            $conn->query($cascadeSql);
        }
        // ------------------------------------------------------------------------

        echo json_encode(["success" => true, "message" => "Operation completed successfully"]); 
    } else { 
        echo json_encode(["success" => false, "error" => $conn->error]); 
    } 
} else { 
    echo json_encode(["success" => false, "error" => "Invalid input"]); 
} 

$conn->close(); 
?>