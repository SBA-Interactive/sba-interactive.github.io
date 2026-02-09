<?php
// ==========================================
// CONTACT FORM HANDLER - SBA INTERACTIVE
// ==========================================
// This script handles form submissions from the website.
// It receives JSON data, validates it, saves it to the database,
// and sends an email notification to the admin.

// ------------------------------------------
// 1. SETUP & HEADERS
// ------------------------------------------
// Set the content type to JSON so the browser knows we are sending back structured data.
header('Content-Type: application/json');

// Allow requests from any origin (CORS). IMPORTANT for frontend-backend communication.
header('Access-Control-Allow-Origin: *'); 

// Allow specific HTTP methods. We mainly care about POST.
header('Access-Control-Allow-Methods: POST, OPTIONS');

// Allow specific headers. Content-Type is needed for JSON.
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request.
// Browsers send this first to check if they can send the actual data.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ------------------------------------------
// 2. DATABASE CONNECTION
// ------------------------------------------
// Include the database connection script.
// ensure 'db.php' is in the same directory.
require_once __DIR__ . '/db.php';

// ------------------------------------------
// 3. REQUEST METHOD CHECK
// ------------------------------------------
// We only accept POST requests. If it's not POST, return a 405 error.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// ------------------------------------------
// 4. DATA INPUT PROCESSING
// ------------------------------------------
// Get the 'type' of form submission (quickcontact vs longassbrief) from the URL query parameter.
$type = $_GET['type'] ?? '';

// Read the raw POST data from the input stream.
// This is necessary because we are sending JSON, not standard form data.
$input = json_decode(file_get_contents('php://input'), true);

// If JSON decoding failed or input is empty, return a 400 error.
if (!$input) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

try {
    // Get a connection to the database
    $pdo = getDbConnection();
    
    // ------------------------------------------
    // 5. HANDLING 'quickcontact' FORM
    // ------------------------------------------
    if ($type === 'quickcontact') {
        // Sanitize and retrieve variables
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $message = trim($input['message'] ?? '');

        // Validate required fields
        if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($message)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input data']);
            exit;
        }

        // Save to Database
        // We store the specific fields plus the raw JSON data for backup.
        $stmt = $pdo->prepare("INSERT INTO requests (type, name, email, data) VALUES (?, ?, ?, ?)");
        $stmt->execute(['quick', $name, $email, json_encode(['message' => $message])]);

        // Send Email Notification
        $subject = EMAIL_SUBJECT_PREFIX . "Quick Contact: " . $name;
        $body = "New Quick Contact Request:\n\nName: $name\nEmail: $email\nMessage: $message";
        $headers = ['From' => EMAIL_FROM, 'Reply-To' => $email];
        mail(ADMIN_EMAIL, $subject, $body, $headers);

    // ------------------------------------------
    // 6. HANDLING 'longassbrief' FORM (THE BIG ONE)
    // ------------------------------------------
    } elseif ($type === 'longassbrief') {
        $startupName = trim($input['startupName'] ?? '');
        $contactInfo = trim($input['contactInfo'] ?? '');

        if (empty($startupName) || empty($contactInfo)) {
            http_response_code(400);
            echo json_encode(['error' => 'Startup name and contact info are required']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO requests (type, name, email, data) VALUES (?, ?, ?, ?)");
        $stmt->execute(['brief', $startupName, $contactInfo, json_encode($input)]);

        $subject = EMAIL_SUBJECT_PREFIX . "Project Brief: " . $startupName;
        
        if (!function_exists('cur_val')) {
            function cur_val($arr, $key) {
                $val = $arr[$key] ?? '—';
                if ($val === '') return '—';
                return is_array($val) ? implode(', ', $val) : $val;
            }
        }

        $body = "🚀 NEW PROJECT BRIEF RECEIVED\n";
        $body .= "================================\n\n";

        $body .= "📍 1. STARTUP OVERVIEW\n";
        $body .= "--------------------------------\n";
        $body .= "Startup Name:      " . cur_val($input, 'startupName') . "\n";
        $body .= "One Sentence:      " . cur_val($input, 'oneSentenceDesc') . "\n";
        $body .= "Problem Solved:    " . cur_val($input, 'problemSolved') . "\n";
        $body .= "Main Offer:        " . cur_val($input, 'mainOffer') . "\n";
        $body .= "Comp. Difference:  " . cur_val($input, 'competitorDifference') . "\n\n";

        $body .= "👥 2. TARGET AUDIENCE\n";
        $body .= "--------------------------------\n";
        $body .= "Primary Audience:  " . cur_val($input, 'primaryAudience') . "\n";
        $body .= "Target Groups:     " . cur_val($input, 'targetGroups') . "\n"; 
        $body .= "Age Range:         " . cur_val($input, 'audienceAge') . "\n";
        $body .= "Geo Market:        " . cur_val($input, 'geoMarket') . "\n";
        $body .= "Pain Point:        " . cur_val($input, 'biggestPainPoint') . "\n\n";

        $body .= "🎨 3. BRAND & STYLE\n";
        $body .= "--------------------------------\n";
        $body .= "Brand Describe:    " . cur_val($input, 'brandDescribe') . "\n";
        $body .= "Personality:       " . cur_val($input, 'brandPersonality') . "\n"; 
        $body .= "Visual Style:      " . cur_val($input, 'visualStyle') . "\n";
        $body .= "Branding Assets:   " . cur_val($input, 'brandingAssets') . "\n";
        $body .= "Liked Brands:      " . cur_val($input, 'likedBrands') . "\n\n";

        $body .= "🎯 4. GOALS & STRATEGY\n";
        $body .= "--------------------------------\n";
        $body .= "Primary Goal:      " . cur_val($input, 'primaryGoal') . "\n";
        $body .= "Main Action (CTA): " . cur_val($input, 'mainAction') . "\n";
        $scale = cur_val($input, 'conversionBrandingScale');
        $body .= "Brand vs Convert:  " . $scale . " (1:Brand <-> 5:Sales)\n\n";

        $body .= "⚙️ 5. PAGES & FEATURES\n";
        $body .= "--------------------------------\n";
        $body .= "Pages Included:    " . cur_val($input, 'pagesIncluded') . "\n"; 
        $body .= "Special Features:  " . cur_val($input, 'specialFeatures') . "\n"; 
        $body .= "Languages:         " . cur_val($input, 'languageCount') . "\n\n";

        $body .= "📝 6. CONTENT & ASSETS\n";
        $body .= "--------------------------------\n";
        $body .= "Content Status:    " . cur_val($input, 'contentAvailability') . "\n";
        $body .= "Help Needed:       " . cur_val($input, 'helpNeeded') . "\n"; 
        $body .= "Social Proof:      " . cur_val($input, 'socialProof') . "\n"; 
        $body .= "Important Info:    " . cur_val($input, 'highlightImportant') . "\n\n";

        $body .= "💻 7. TECHNICAL DETAILS\n";
        $body .= "--------------------------------\n";
        $body .= "Has Domain?:       " . cur_val($input, 'ownDomain') . "\n";
        $body .= "New vs Redesign:   " . cur_val($input, 'newOrRedesign') . "\n";
        $body .= "SEO Required?:     " . cur_val($input, 'seoRequired') . "\n";
        $body .= "Compliance:        " . cur_val($input, 'compliance') . "\n\n"; 

        $body .= "⚠️ 8. RISKS & HISTORY\n";
        $body .= "--------------------------------\n";
        $body .= "Previous Exp:      " . cur_val($input, 'agencyExperience') . "\n";
        $body .= "Main Concerns:     " . cur_val($input, 'mainConcern') . "\n"; 
        $body .= "Other Concerns:    " . cur_val($input, 'concernOther') . "\n";
        $body .= "Avoid/Dislikes:    " . cur_val($input, 'avoidNotes') . "\n\n";

        $body .= "🔮 9. FUTURE PLANS\n";
        $body .= "--------------------------------\n";
        $body .= "Scale Expectation: " . cur_val($input, 'scaleExpectation') . "\n";
        $body .= "Future Needs:      " . cur_val($input, 'futureNeeds') . "\n\n"; 

        $body .= "📞 10. CONTACT\n";
        $body .= "--------------------------------\n";
        $body .= "Contact Info:      " . cur_val($input, 'contactInfo') . "\n";
        $body .= "Additional Notes:  " . cur_val($input, 'additionalNotes') . "\n\n";

        $body .= "================================\n";
        $body .= "END OF BRIEF\n";

        $headers = ['From' => EMAIL_FROM];
        if (filter_var($contactInfo, FILTER_VALIDATE_EMAIL)) { 
            $headers['Reply-To'] = $contactInfo; 
        }
        
        mail(ADMIN_EMAIL, $subject, $body, $headers);

    } else {
        // Handle invalid 'type'
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request type']);
        exit;
    }

    // Return success to the frontend
    echo json_encode(['success' => true]);

} catch (\Exception $e) {
    // Handle server errors gracefully
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
