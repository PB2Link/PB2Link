<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include_once "../db_connection.php"; 

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['email']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input - missing data']);
    exit;
}

$email = mysqli_real_escape_string($conn, $data['email']);
$password = $data['password'];

$query = "SELECT admin_id, username, fullname, email, password_hash, role FROM admins WHERE email = '$email'";
$result = mysqli_query($conn, $query);

if ($row = mysqli_fetch_assoc($result)) {
    if (password_verify($password, $row['password_hash'])) {
        mysqli_query($conn, "UPDATE admins SET last_login = NOW() WHERE admin_id = '" . $row['admin_id'] . "'");

        echo json_encode([
            'success' => true,
            'message' => 'Admin login successful',
            'adminData' => [
                'admin_id' => $row['admin_id'],
                'username' => $row['username'], 
                'fullname' => $row['fullname'], 
                'email'    => $row['email'],
                'role'     => $row['role']     
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Email not found']);
}
?>