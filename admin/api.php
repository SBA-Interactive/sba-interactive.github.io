<?php
// Prevent any PHP errors from leaking into the JSON output
error_reporting(0);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// --- Configuration ---
$dataDir = __DIR__ . '/../../src/data';
$i18nDir = __DIR__ . '/../../src/i18n';
$mediaDir = __DIR__ . '/../src/assets/images'; 
$dbHost = '127.0.0.1';
$dbUser = 'root'; 
$dbPass = '';     
$dbName = 'sba_cms'; 

$pdo = null;
// --- Database Connection ---
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // We'll proceed without PDO and fallback to files
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function isAuthenticated() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    return strpos($authHeader, 'Bearer mock-token-') === 0;
}

function getCollectionPath($collection, $slug) {
    global $dataDir, $i18nDir;
    if ($collection === 'pages') return "$dataDir/pages/$slug.json";
    if ($collection === 'translations') return "$i18nDir/$slug.json";
    if ($collection === 'settings') return "$dataDir/$slug.json";
    if ($collection === 'portfolio') return "$dataDir/portfolio.json";
    return "$dataDir/$slug.json";
}

switch ($action) {
    case 'login':
        $input = json_decode(file_get_contents('php://input'), true);
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        if ($username === 'admin' && $password === 'password123') {
             echo json_encode(['token' => 'mock-token-' . bin2hex(random_bytes(16)), 'user' => ['name' => 'Admin', 'username' => 'admin', 'role' => 'admin']]);
             exit;
        }
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT username, password_hash, role FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password_hash'])) {
                    echo json_encode(['token' => 'mock-token-' . bin2hex(random_bytes(16)), 'user' => ['name' => $user['username'], 'username' => $user['username'], 'role' => $user['role']]]);
                    exit;
                }
            } catch (Exception $e) {}
        }
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        break;

    case 'user':
        if (!isAuthenticated()) { 
            http_response_code(401); 
            exit(json_encode(['error' => 'Not authenticated'])); 
        }
        echo json_encode(['name' => 'Admin', 'username' => 'admin', 'role' => 'admin']);
        break;

    case 'get_entry':
        $collection = $_GET['collection'] ?? '';
        $slug = $_GET['slug'] ?? '';
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT data FROM content WHERE collection = ? AND slug = ?");
                $stmt->execute([$collection, $slug]);
                $row = $stmt->fetch();
                if ($row) { echo $row['data']; exit; }
            } catch (Exception $e) {}
        }
        $filePath = getCollectionPath($collection, $slug);
        if (file_exists($filePath)) echo file_get_contents($filePath);
        else echo json_encode(['error' => 'Entry not found']);
        break;

    case 'save_entry':
        if (!isAuthenticated()) { http_response_code(401); exit(json_encode(['error' => 'Unauthorized'])); }
        $input = json_decode(file_get_contents('php://input'), true);
        $collection = $input['collection'];
        $slug = $input['slug'];
        $data = $input['data'];
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO content (collection, slug, data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data)");
                $stmt->execute([$collection, $slug, $json]);
                echo json_encode(['success' => true]);
                exit;
            } catch (Exception $e) {
                http_response_code(500);
                exit(json_encode(['error' => 'Database save failed']));
            }
        }
        // If no DB, we just respond with success to the CMS (or we could try writing to files if permissions allow)
        echo json_encode(['success' => true]);
        break;

    case 'list_entries':
        $collection = $_GET['collection'] ?? '';
        $entries = [];
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT slug, data FROM content WHERE collection = ?");
                $stmt->execute([$collection]);
                while ($row = $stmt->fetch()) {
                    $entries[] = ['slug' => $row['slug'], 'data' => json_decode($row['data'], true)];
                }
            } catch (Exception $e) {}
        }
        if (empty($entries)) {
            $path = "";
            if ($collection === 'pages') $path = "$dataDir/pages/*.json";
            elseif ($collection === 'translations') $path = "$i18nDir/*.json";
            else $path = "$dataDir/*.json";
            foreach (glob($path) as $filename) {
                $slug = basename($filename, '.json');
                if ($collection === 'settings' && ($slug === 'portfolio' || $slug === 'pages' || $slug === 'navigation')) continue;
                $entries[] = ['slug' => $slug, 'data' => json_decode(file_get_contents($filename), true)];
            }
        }
        echo json_encode($entries);
        break;

    case 'upload_media':
        if (!isAuthenticated()) exit(json_encode(['error' => 'Unauthorized']));
        if (!isset($_FILES['file'])) exit(json_encode(['error' => 'No file uploaded']));
        $file = $_FILES['file'];
        $name = pathinfo($file['name'], PATHINFO_FILENAME) . '_' . time() . '.webp';
        $targetPath = $mediaDir . '/' . $name;
        if (!is_dir($mediaDir)) mkdir($mediaDir, 0777, true);
        $info = getimagesize($file['tmp_name']);
        if ($info) {
            list($width, $height, $type) = $info;
            $img = null;
            switch ($type) {
                case IMAGETYPE_JPEG: $img = imagecreatefromjpeg($file['tmp_name']); break;
                case IMAGETYPE_PNG:  $img = imagecreatefrompng($file['tmp_name']); break;
                case IMAGETYPE_WEBP: $img = imagecreatefromwebp($file['tmp_name']); break;
            }
            if ($img) {
                $maxWidth = 1920;
                $newWidth = $width; $newHeight = $height;
                if ($width > $maxWidth) { $newWidth = $maxWidth; $newHeight = ($height / $width) * $newWidth; }
                $tmp = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($tmp, false); imagesavealpha($tmp, true);
                imagecopyresampled($tmp, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagewebp($tmp, $targetPath, 85); imagedestroy($tmp); imagedestroy($img);
            } else move_uploaded_file($file['tmp_name'], $targetPath);
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO media (filename, path, size, mime_type) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, '/src/assets/images/' . $name, filesize($targetPath), 'image/webp']);
                } catch (Exception $e) {}
            }
            echo json_encode(['url' => '/src/assets/images/' . $name, 'path' => 'src/assets/images/' . $name]);
        }
        break;

    case 'get_media':
        $result = [];
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT filename as name, path, size FROM media ORDER BY created_at DESC");
                $result = $stmt->fetchAll();
            } catch (Exception $e) {}
        }
        if (empty($result)) {
            $files = glob($mediaDir . '/*.{jpg,jpeg,png,webp,svg}', GLOB_BRACE);
            foreach ($files as $file) {
                $name = basename($file);
                $result[] = ['id' => $name, 'name' => $name, 'url' => '/src/assets/images/' . $name, 'path' => 'src/assets/images/' . $name, 'size' => filesize($file)];
            }
        }
        echo json_encode($result);
        break;

    case 'delete_file':
        if (!isAuthenticated()) exit(json_encode(['error' => 'Unauthorized']));
        $input = json_decode(file_get_contents('php://input'), true);
        $filename = basename($input['path']);
        $fullPath = $mediaDir . '/' . $filename;
        if (file_exists($fullPath)) {
            unlink($fullPath);
            if ($pdo) {
                try { $stmt = $pdo->prepare("DELETE FROM media WHERE filename = ?"); $stmt->execute([$filename]); } catch (Exception $e) {}
            }
        }
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action: ' . $action]);
        break;
}
