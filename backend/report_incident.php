<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include "../db_connection.php";
// Include PHPMailer autoload
require __DIR__ . '/vendor/autoload.php';

// Validate required fields (Person Involved fields are now optional)
$required_fields = ['user_id', 'reporter_name', 'reporter_address', 'reporter_contact', 'reporter_email', 'incident_address', 'description', 'incident_class', 'reporting_class', 'track_code'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
        exit;
    }
}

if (!isset($_FILES['attachment']) || empty($_FILES['attachment']['name'])) {
    echo json_encode(['success' => false, 'message' => 'Evidence attachment is required']);
    exit;
}

// Sanitize input data
$user_id = intval($_POST['user_id']);
$reporter_name = mysqli_real_escape_string($conn, $_POST['reporter_name']);
$reporter_address = mysqli_real_escape_string($conn, $_POST['reporter_address']);
$reporter_contact = mysqli_real_escape_string($conn, $_POST['reporter_contact']);
$reporter_email = mysqli_real_escape_string($conn, $_POST['reporter_email']);
$contact_person_name = isset($_POST['contact_person_name']) ? mysqli_real_escape_string($conn, $_POST['contact_person_name']) : '';
$contact_person_number = isset($_POST['contact_person_number']) ? mysqli_real_escape_string($conn, $_POST['contact_person_number']) : '';
$incident_address = mysqli_real_escape_string($conn, $_POST['incident_address']);
$description = mysqli_real_escape_string($conn, $_POST['description']);
$incident_class = mysqli_real_escape_string($conn, $_POST['incident_class']);
$reporting_class = mysqli_real_escape_string($conn, $_POST['reporting_class']);
$track_code = mysqli_real_escape_string($conn, $_POST['track_code']);
$status = 'Pending';
$attachment_path = null;
$attachment_type = null;

// Handle required file upload
$allowed_image = ['jpg', 'jpeg', 'png'];
$allowed_video = ['mp4'];

$file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));

if (in_array($file_ext, $allowed_image)) {
    $attachment_type = 'image';
} elseif (in_array($file_ext, $allowed_video)) {
    $attachment_type = 'video';
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and MP4 are allowed.']);
    exit;
}

// Validate file size (10MB max)
if ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit.']);
    exit;
}

// --- NEW FIX: Fetch the control_num for folder creation ---
$getControlNum = $conn->prepare("SELECT control_num FROM residents WHERE user_id = ?");
$getControlNum->bind_param("i", $user_id);
$getControlNum->execute();
$cnResult = $getControlNum->get_result()->fetch_assoc();
$control_num = $cnResult ? $cnResult['control_num'] : 'UNKNOWN';
$getControlNum->close();
// ----------------------------------------------------------

// Create upload directory if it doesn't exist (using $track_code instead of $tracking_code)
$upload_dir = "uploads/Incident_Reports/" . $control_num . "/" . $track_code . "/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$file_name = uniqid("incident_") . "." . $file_ext;
$attachment_path = $upload_dir . $file_name;

// Move uploaded file
if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
    exit;
}

// Check if incident_reports table exists, if not create it
$table_check = "CREATE TABLE IF NOT EXISTS incident_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reporter_name VARCHAR(255) NOT NULL,
    reporter_address TEXT,
    reporter_contact VARCHAR(20),
    reporter_email VARCHAR(255),
    contact_person_name VARCHAR(255),
    contact_person_number VARCHAR(20),
    incident_address TEXT NOT NULL,
    description LONGTEXT NOT NULL,
    attachment_path VARCHAR(255),
    attachment_type VARCHAR(50),
    status VARCHAR(50) DEFAULT 'Pending',
    track_code VARCHAR(50) UNIQUE NOT NULL,
    incident_class VARCHAR(100),
    reporting_class VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX (track_code),
    INDEX (status),
    INDEX (created_at)
)";

if (!mysqli_query($conn, $table_check)) {
    error_log("Table creation error: " . mysqli_error($conn));
}

// Insert incident report into database
$query = "INSERT INTO incident_reports 
    (user_id, reporter_name, reporter_address, reporter_contact, reporter_email, contact_person_name, contact_person_number, incident_address, description, attachment_path, attachment_type, status, track_code, incident_class, reporting_class)
    VALUES 
    ($user_id, '$reporter_name', '$reporter_address', '$reporter_contact', '$reporter_email', '$contact_person_name', '$contact_person_number', '$incident_address', '$description', '$attachment_path', '$attachment_type', '$status', '$track_code', '$incident_class', '$reporting_class')";

if (mysqli_query($conn, $query)) {
    $incident_id = mysqli_insert_id($conn);

    // ==========================================
    // EMAIL SENDING LOGIC (Using Gmail SMTP)
    // ==========================================
    if (!empty($reporter_email)) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;

            $mail->Username = 'jzelcantor@gmail.com';
            $mail->Password = 'ujkxkwahegmirrun';

            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom('jzelcantor@gmail.com', 'Barangay Pasong Buaya II');
            $mail->addAddress($reporter_email);

            $mail->isHTML(true);
            $mail->Subject = "Incident Report Received [$track_code]";

            // HTML Email Template with Summary
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 10px;'>
                    <h2 style='color: #dc2626; border-bottom: 2px solid #dc2626; padding-bottom: 10px;'>Incident Report Received</h2>
                    <p>Hello $reporter_name,</p>
                    <p>We have successfully received your incident report. It is currently under review by the Barangay Administration.</p>
                    
                    <div style='background-color: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #cbd5e1;'>
                        <h3 style='margin-top: 0; color: #334155; font-size: 16px;'>Report Summary</h3>
                        <p style='margin: 8px 0;'><strong>Tracking Code:</strong> <span style='color: #dc2626; font-weight: bold;'>$track_code</span></p>
                        <p style='margin: 8px 0;'><strong>Status:</strong> <span style='background-color: #fef08a; color: #854d0e; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px;'>PENDING</span></p>
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 12px 0;'>
                        <p style='margin: 8px 0;'><strong>Incident Classification:</strong> $incident_class</p>
                        <p style='margin: 8px 0;'><strong>Report Type:</strong> $reporting_class</p>
                        <p style='margin: 8px 0;'><strong>Incident Location:</strong> $incident_address</p>
                        <p style='margin: 8px 0;'><strong>Person Involved:</strong> $contact_person_name</p>
                        <p style='margin: 8px 0;'><strong>Contact Number:</strong> $contact_person_number</p>
                    </div>

                    <p>An email notification will be sent to you once your report has been reviewed and processed by the Barangay Administration.</p>
                    <p style='color: #64748b; font-size: 14px; margin-top: 30px;'>Thank you,<br>Barangay Pasong Buaya II Administration</p>
                </div>
            ";

            // Plain Text Fallback
            $mail->AltBody = "Your incident report (Tracking: $track_code) has been received and is currently PENDING. We will notify you once it has been reviewed.";

            $mail->send();
        } catch (Exception $e) {
            // We log the error but DO NOT crash the script. 
            // The DB insertion was successful, so the user should still see a success message.
            error_log("Failed to send Incident Report Email to $reporter_email. Error: {$mail->ErrorInfo}");
        }
    }

    $result = mysqli_query($conn, "SELECT created_at FROM incident_reports WHERE id = $incident_id");
    $row = mysqli_fetch_assoc($result);
    $created_at = $row['created_at'];

    echo json_encode([
        'success' => true,
        'message' => 'Incident report submitted securely',
        'incident_id' => $incident_id,
        'track_code' => $track_code,
        'created_at' => $created_at
    ]);
} else {
    // If track_code already exists, generate a new one
    if (strpos(mysqli_error($conn), 'Duplicate entry') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Tracking code already exists. Please try again.'
        ]);
    } else {
        error_log("Database error: " . mysqli_error($conn));
        echo json_encode([
            'success' => false,
            'message' => 'Failed to submit incident report. Please try again.'
        ]);
    }
}

mysqli_close($conn);
?>