<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../src/LicenseManager.php';

class API {
    private $manager;
    private $apiKey;
    
    public function __construct() {
        $this->manager = new LicenseManager();
        $this->apiKey = getenv('API_KEY') ?: 'your-api-key-here';
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';
        $data = $this->getRequestData();
        
        // Public endpoints (tanpa auth)
        $publicActions = ['validate', 'activate', 'deactivate', 'check'];
        
        // Protected endpoints (perlu API key)
        $protectedActions = ['generate', 'list', 'revoke', 'stats', 'delete'];
        
        if (in_array($action, $protectedActions)) {
            if (!$this->authenticate()) {
                return $this->response(['error' => 'Unauthorized', 'message' => 'Invalid API Key'], 401);
            }
        }
        
        switch ($action) {
            // ===== PUBLIC ENDPOINTS =====
            case 'validate':
                return $this->response($this->manager->validate($data['license_key'] ?? ''));
                
            case 'activate':
                return $this->response($this->manager->activate(
                    $data['license_key'] ?? '',
                    $data['hardware_id'] ?? '',
                    $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null
                ));
                
            case 'deactivate':
                return $this->response($this->manager->deactivate(
                    $data['license_key'] ?? '',
                    $data['hardware_id'] ?? ''
                ));
                
            case 'check':
                // Simple check - hanya return valid/invalid
                $result = $this->manager->validate($data['license_key'] ?? '');
                return $this->response([
                    'valid' => $result['valid'],
                    'type' => $result['license_type'] ?? null,
                    'expires' => $result['expires_at'] ?? null
                ]);
            
            // ===== PROTECTED ENDPOINTS =====
            case 'generate':
                return $this->response($this->manager->generate($data));
                
            case 'list':
                $page = (int)($data['page'] ?? 1);
                $limit = (int)($data['limit'] ?? 20);
                return $this->response([
                    'success' => true,
                    'licenses' => $this->manager->getAllLicenses($page, $limit),
                    'page' => $page
                ]);
                
            case 'revoke':
                return $this->response($this->manager->revokeLicense($data['license_key'] ?? ''));
                
            case 'stats':
                return $this->response([
                    'success' => true,
                    'stats' => $this->manager->getStats()
                ]);
                
            default:
                return $this->response([
                    'error' => 'Invalid action',
                    'available_actions' => [
                        'public' => $publicActions,
                        'protected' => $protectedActions
                    ]
                ], 400);
        }
    }
    
    private function authenticate() {
        $providedKey = $_SERVER['HTTP_X_API_KEY'] 
            ?? $_GET['api_key'] 
            ?? $this->getRequestData()['api_key'] 
            ?? '';
        return hash_equals($this->apiKey, $providedKey);
    }
    
    private function getRequestData() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }
        
        return array_merge($_GET, $_POST);
    }
    
    private function response($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}

$api = new API();
$api->handleRequest();
