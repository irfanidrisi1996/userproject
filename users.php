<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug logging
error_log("Request received: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json");

// // Test if file is being called
// if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
//     echo json_encode(["message" => "API is working", "method" => $_SERVER['REQUEST_METHOD']]);
//     exit;
// }

include 'db.php';

// Helper: Get request data
function getRequestData() {
    $input = file_get_contents("php://input");
    error_log("Raw input: " . $input);
    return json_decode($input, true);
}

// Helper: Send JSON response
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Routing logic
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

// Debug the path
error_log("Path: " . $path);

// Extract ID from URL if present (e.g., /users.php/users/3 -> id = 3)
$id = null;
if (preg_match('/\/users\/(\d+)$/', $path, $matches)) {
    $id = $matches[1];
    error_log("Extracted ID: " . $id);
}

// Check if this is a users endpoint (either /users or /users.php)
if (strpos($path, 'users') !== false) {
    switch ($method) {
        case 'GET':
            if (is_numeric($id)) {
                // Get single user
                $stmt = $conn->prepare("SELECT id, name, email, dob, created_at FROM users WHERE id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                sendResponse($result ?: ["error" => "User not found"], $result ? 200 : 404);
            } else {
                // Get all users
                $result = $conn->query("SELECT id, name, email, dob, created_at FROM users");
                if (!$result) {
                    sendResponse(["error" => "Database error: " . $conn->error], 500);
                }
                $users = [];
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
                sendResponse($users);
            }
            break;

        case 'POST':
            $data = getRequestData();
            error_log("POST data: " . json_encode($data));
            
            if (!$data || !isset($data['name']) || !isset($data['email']) || !isset($data['password']) || !isset($data['dob'])) {
                sendResponse(["error" => "Missing required fields", "received" => $data], 400);
            }
            
            $hashed = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, dob) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                sendResponse(["error" => "Prepare failed: " . $conn->error], 500);
            }
            
            $stmt->bind_param("ssss", $data['name'], $data['email'], $hashed, $data['dob']);
            if ($stmt->execute()) {
                sendResponse(["message" => "User created", "id" => $conn->insert_id], 201);
            } else {
                sendResponse(["error" => "Execute failed: " . $stmt->error], 400);
            }
            break;

            case 'PUT':
                if (!is_numeric($id)) sendResponse(["error" => "User ID required"], 400);
                $data = getRequestData();
                $fields = [];
                $params = [];
                $types = '';
                if (isset($data['name'])) { $fields[] = "name=?"; $params[] = $data['name']; $types .= 's'; }
                if (isset($data['email'])) { $fields[] = "email=?"; $params[] = $data['email']; $types .= 's'; }
                if (isset($data['password'])) { $fields[] = "password=?"; $params[] = password_hash($data['password'], PASSWORD_DEFAULT); $types .= 's'; }
                if (isset($data['dob'])) { $fields[] = "dob=?"; $params[] = $data['dob']; $types .= 's'; }
                if (!$fields) sendResponse(["error" => "No fields to update"], 400);
                $params[] = $id;
                $types .= 'i';
                $stmt = $conn->prepare("UPDATE users SET " . implode(',', $fields) . " WHERE id=?");
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    sendResponse(["message" => "User updated"]);
                } else {
                    sendResponse(["error" => $conn->error], 400);
                }
                break;
    
            case 'DELETE':
                if (!is_numeric($id)) sendResponse(["error" => "User ID required"], 400);
                $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    sendResponse(["message" => "User deleted"]);
                } else {
                    sendResponse(["error" => $conn->error], 400);
                }
                break;

        default:
            sendResponse(["error" => "Method not allowed: " . $method], 405);
    }
} else {
    sendResponse(["error" => "Endpoint not found", "path" => $path], 404);
}
?> 