<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$type = $_GET['type'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    if ($type === 'quickcontact') {
        // Validation
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $message = trim($input['message'] ?? '');

        if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($message)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input data']);
            exit;
        }

        // Insert into DB
        $stmt = $pdo->prepare("INSERT INTO requests (type, name, email, data) VALUES (?, ?, ?, ?)");
        $stmt->execute(['quick', $name, $email, json_encode(['message' => $message])]);

        // Email
        $subject = EMAIL_SUBJECT_PREFIX . "Quick Contact: " . $name;
        $body = "New Quick Contact Request:\n\nName: $name\nEmail: $email\nMessage: $message";
        $headers = ['From' => EMAIL_FROM, 'Reply-To' => $email];
        mail(ADMIN_EMAIL, $subject, $body, $headers);

    } elseif ($type === 'longassbrief') {
        $startupName = trim($input['startupName'] ?? '');
        $contactInfo = trim($input['contactInfo'] ?? '');

        if (empty($startupName) || empty($contactInfo)) {
            http_response_code(400);
            echo json_encode(['error' => 'Startup name and contact info are required']);
            exit;
        }

        // Insert into DB
        $stmt = $pdo->prepare("INSERT INTO requests (type, name, email, data) VALUES (?, ?, ?, ?)");
        $stmt->execute(['brief', $startupName, $contactInfo, json_encode($input)]);

        // Email summary
        $subject = EMAIL_SUBJECT_PREFIX . "New Project Brief: " . $startupName;
        $body = "New Project Brief Submission:\n\nStartup: $startupName\nContact: $contactInfo\n\n";
        $body .= "Check database for full details.";
        
        $headers = ['From' => EMAIL_FROM];
        if (filter_var($contactInfo, FILTER_VALIDATE_EMAIL)) { $headers['Reply-To'] = $contactInfo; }
        mail(ADMIN_EMAIL, $subject, $body, $headers);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request type']);
        exit;
    }

    echo json_encode(['success' => true]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
