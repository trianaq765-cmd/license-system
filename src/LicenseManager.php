<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

class LicenseManager {
    private $db;
    private $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ========== GENERATE LICENSE ==========
    public function generate($data) {
        $productName = $data['product_name'] ?? 'MyTool';
        $licenseType = strtoupper($data['license_type'] ?? 'BASIC');
        $maxActivations = (int)($data['max_activations'] ?? 1);
        $quantity = min((int)($data['quantity'] ?? 1), 100);
        $customerEmail = $data['customer_email'] ?? null;
        $notes = $data['notes'] ?? null;
        
        // Hitung expiry
        $expiryDays = $data['expiry_days'] ?? DEFAULT_EXPIRY_DAYS[$licenseType] ?? null;
        $expiresAt = $expiryDays ? date('Y-m-d H:i:s', strtotime("+{$expiryDays} days")) : null;
        
        $generatedKeys = [];
        
        for ($i = 0; $i < $quantity; $i++) {
            $key = $this->createKeyString($licenseType);
            
            $this->db->insert(
                "INSERT INTO licenses (license_key, product_name, license_type, max_activations, expires_at, customer_email, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$key, $productName, $licenseType, $maxActivations, $expiresAt, $customerEmail, $notes]
            );
            
            $generatedKeys[] = [
                'key' => $key,
                'type' => $licenseType,
                'expires_at' => $expiresAt
            ];
        }
        
        return [
            'success' => true,
            'count' => count($generatedKeys),
            'licenses' => $generatedKeys
        ];
    }
    
    // ========== VALIDATE LICENSE ==========
    public function validate($licenseKey) {
        $license = $this->db->fetch(
            "SELECT * FROM licenses WHERE license_key = ?",
            [$licenseKey]
        );
        
        if (!$license) {
            return ['valid' => false, 'error' => 'LICENSE_NOT_FOUND', 'message' => 'License key tidak ditemukan'];
        }
        
        if ($license['status'] === 'revoked') {
            return ['valid' => false, 'error' => 'LICENSE_REVOKED', 'message' => 'License telah dicabut'];
        }
        
        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            $this->updateStatus($license['id'], 'expired');
            return ['valid' => false, 'error' => 'LICENSE_EXPIRED', 'message' => 'License sudah expired'];
        }
        
        $activationsLeft = $license['max_activations'] - $license['current_activations'];
        
        return [
            'valid' => true,
            'license_type' => $license['license_type'],
            'product_name' => $license['product_name'],
            'status' => $license['status'],
            'expires_at' => $license['expires_at'],
            'max_activations' => $license['max_activations'],
            'current_activations' => $license['current_activations'],
            'activations_left' => $activationsLeft
        ];
    }
    
    // ========== ACTIVATE LICENSE ==========
    public function activate($licenseKey, $hardwareId, $userAgent = null) {
        $validation = $this->validate($licenseKey);
        
        if (!$validation['valid']) {
            return $validation;
        }
        
        $license = $this->db->fetch(
            "SELECT * FROM licenses WHERE license_key = ?",
            [$licenseKey]
        );
        
        // Cek apakah hardware sudah teraktivasi
        $existing = $this->db->fetch(
            "SELECT id FROM activations WHERE license_id = ? AND hardware_id = ?",
            [$license['id'], $hardwareId]
        );
        
        if ($existing) {
            return [
                'success' => true,
                'message' => 'Perangkat sudah teraktivasi sebelumnya',
                'already_activated' => true
            ];
        }
        
        // Cek batas aktivasi
        if ($license['current_activations'] >= $license['max_activations']) {
            return [
                'success' => false,
                'error' => 'ACTIVATION_LIMIT',
                'message' => 'Batas maksimal aktivasi tercapai'
            ];
        }
        
        // Simpan aktivasi
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->db->insert(
            "INSERT INTO activations (license_id, hardware_id, ip_address, user_agent) VALUES (?, ?, ?, ?)",
            [$license['id'], $hardwareId, $ip, $userAgent]
        );
        
        // Update counter
        $this->db->query(
            "UPDATE licenses SET current_activations = current_activations + 1 WHERE id = ?",
            [$license['id']]
        );
        
        return [
            'success' => true,
            'message' => 'Aktivasi berhasil',
            'license_type' => $license['license_type'],
            'expires_at' => $license['expires_at']
        ];
    }
    
    // ========== DEACTIVATE ==========
    public function deactivate($licenseKey, $hardwareId) {
        $license = $this->db->fetch(
            "SELECT id FROM licenses WHERE license_key = ?",
            [$licenseKey]
        );
        
        if (!$license) {
            return ['success' => false, 'message' => 'License tidak ditemukan'];
        }
        
        $this->db->query(
            "DELETE FROM activations WHERE license_id = ? AND hardware_id = ?",
            [$license['id'], $hardwareId]
        );
        
        $this->db->query(
            "UPDATE licenses SET current_activations = GREATEST(current_activations - 1, 0) WHERE id = ?",
            [$license['id']]
        );
        
        return ['success' => true, 'message' => 'Deaktivasi berhasil'];
    }
    
    // ========== HELPER METHODS ==========
    private function createKeyString($licenseType) {
        $prefix = LICENSE_PREFIX[$licenseType] ?? 'XX';
        $key = $prefix . '-';
        
        // Format: XX-XXXX-XXXX-XXXX-XXXX
        for ($seg = 0; $seg < 4; $seg++) {
            for ($i = 0; $i < 4; $i++) {
                $key .= $this->chars[random_int(0, strlen($this->chars) - 1)];
            }
            if ($seg < 3) $key .= '-';
        }
        
        // Tambah checksum
        $key .= '-' . $this->generateChecksum($key);
        
        return $key;
    }
    
    private function generateChecksum($key) {
        $hash = hash_hmac('sha256', $key, SECRET_KEY);
        return strtoupper(substr($hash, 0, 4));
    }
    
    private function updateStatus($id, $status) {
        $this->db->query("UPDATE licenses SET status = ? WHERE id = ?", [$status, $id]);
    }
    
    // ========== ADMIN METHODS ==========
    public function getAllLicenses($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        return $this->db->fetchAll(
            "SELECT * FROM licenses ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }
    
    public function revokeLicense($licenseKey) {
        $this->db->query(
            "UPDATE licenses SET status = 'revoked' WHERE license_key = ?",
            [$licenseKey]
        );
        return ['success' => true, 'message' => 'License berhasil direvoke'];
    }
    
    public function getStats() {
        return [
            'total' => $this->db->fetch("SELECT COUNT(*) as count FROM licenses")['count'],
            'active' => $this->db->fetch("SELECT COUNT(*) as count FROM licenses WHERE status = 'active'")['count'],
            'used' => $this->db->fetch("SELECT COUNT(*) as count FROM licenses WHERE current_activations > 0")['count'],
            'expired' => $this->db->fetch("SELECT COUNT(*) as count FROM licenses WHERE status = 'expired'")['count'],
            'revoked' => $this->db->fetch("SELECT COUNT(*) as count FROM licenses WHERE status = 'revoked'")['count']
        ];
    }
}
