<?php
session_start();
require_once __DIR__ . '/../src/LicenseManager.php';

$manager = new LicenseManager();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'generate':
            $result = $manager->generate($_POST);
            if ($result['success']) {
                $message = "Berhasil generate {$result['count']} license key!";
                $messageType = 'success';
                $_SESSION['generated_keys'] = $result['licenses'];
            }
            break;
            
        case 'revoke':
            $result = $manager->revokeLicense($_POST['license_key']);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            break;
            
        case 'validate':
            $result = $manager->validate($_POST['license_key']);
            $_SESSION['validation_result'] = $result;
            break;
    }
}

$stats = $manager->getStats();
$licenses = $manager->getAllLicenses($_GET['page'] ?? 1, 15);
$generatedKeys = $_SESSION['generated_keys'] ?? null;
$validationResult = $_SESSION['validation_result'] ?? null;
unset($_SESSION['generated_keys'], $_SESSION['validation_result']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Manager - Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #0f0f23;
            color: #e0e0e0;
            min-height: 100vh;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #1a1a3e 0%, #2d2d5a 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { color: #00ff88; font-size: 28px; }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #333;
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #00ff88;
        }
        .stat-card .label { color: #888; margin-top: 5px; }
        
        /* Grid Layout */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
        }
        
        /* Cards */
        .card {
            background: #1a1a2e;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #333;
        }
        .card h2 {
            color: #00ff88;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }
        
        /* Forms */
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #aaa;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #444;
            border-radius: 8px;
            background: #0f0f23;
            color: #fff;
            font-size: 14px;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #00ff88;
        }
        
        /* Buttons */
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%);
            color: #000;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,255,136,0.3); }
        .btn-danger { background: #dc3545; color: #fff; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-block { width: 100%; }
        
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: rgba(0,255,136,0.1); border: 1px solid #00ff88; color: #00ff88; }
        .alert-error { background: rgba(220,53,69,0.1); border: 1px solid #dc3545; color: #dc3545; }
        
        /* Table */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        th { background: #0f0f23; color: #00ff88; }
        tr:hover { background: rgba(0,255,136,0.05); }
        
        /* Key Display */
        .key-display {
            background: #0f0f23;
            border: 1px solid #00ff88;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            color: #00ff88;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Status Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-active { background: rgba(0,255,136,0.2); color: #00ff88; }
        .badge-expired { background: rgba(255,193,7,0.2); color: #ffc107; }
        .badge-revoked { background: rgba(220,53,69,0.2); color: #dc3545; }
        
        /* Validation Result */
        .validation-box {
            background: #0f0f23;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
        }
        .validation-box.valid { border: 2px solid #00ff88; }
        .validation-box.invalid { border: 2px solid #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>🔐 License Manager</h1>
                <p style="color: #888; margin-top: 5px;">Admin Dashboard</p>
            </div>
            <div>
                <span style="color: #00ff88;">●</span> System Online
            </div>
        </div>
        
        <!-- Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?= $stats['total'] ?></div>
                <div class="label">Total Licenses</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $stats['active'] ?></div>
                <div class="label">Active</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $stats['used'] ?></div>
                <div class="label">Activated</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $stats['expired'] ?></div>
                <div class="label">Expired</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $stats['revoked'] ?></div>
                <div class="label">Revoked</div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-grid">
            <!-- Generate Form -->
            <div class="card">
                <h2>🔑 Generate License</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    
                    <div class="form-group">
                        <label>Product Name</label>
                        <input type="text" name="product_name" value="MyTool" required>
                    </div>
                    
                    <div class="form-group">
                        <label>License Type</label>
                        <select name="license_type">
                            <option value="TRIAL">Trial (30 Days)</option>
                            <option value="BASIC">Basic (1 Year)</option>
                            <option value="PRO" selected>Professional (1 Year)</option>
                            <option value="ENTERPRISE">Enterprise (2 Years)</option>
                            <option value="LIFETIME">Lifetime</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Max Activations</label>
                        <input type="number" name="max_activations" value="1" min="1" max="100">
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" value="1" min="1" max="100">
                    </div>
                    
                    <div class="form-group">
                        <label>Customer Email (Optional)</label>
                        <input type="email" name="customer_email" placeholder="customer@email.com">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Generate Keys</button>
                </form>
                
                <!-- Generated Keys Display -->
                <?php if ($generatedKeys): ?>
                    <div style="margin-top: 20px;">
                        <h3 style="color: #00ff88; margin-bottom: 10px;">Generated Keys:</h3>
                        <?php foreach ($generatedKeys as $key): ?>
                            <div class="key-display">
                                <span><?= htmlspecialchars($key['key']) ?></span>
                                <button class="btn btn-sm" onclick="copyKey('<?= $key['key'] ?>')" style="background:#333;color:#fff;">Copy</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Validate Form -->
            <div class="card">
                <h2>🔍 Validate License</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="validate">
                    <div class="form-group">
                        <label>License Key</label>
                        <input type="text" name="license_key" placeholder="XX-XXXX-XXXX-XXXX-XXXX-XXXX" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Validate</button>
                </form>
                
                <?php if ($validationResult): ?>
                    <div class="validation-box <?= $validationResult['valid'] ? 'valid' : 'invalid' ?>">
                        <?php if ($validationResult['valid']): ?>
                            <p style="color: #00ff88; font-size: 18px; margin-bottom: 10px;">✓ License Valid</p>
                            <p><strong>Type:</strong> <?= $validationResult['license_type'] ?></p>
                            <p><strong>Product:</strong> <?= $validationResult['product_name'] ?></p>
                            <p><strong>Expires:</strong> <?= $validationResult['expires_at'] ?? 'Never' ?></p>
                            <p><strong>Activations:</strong> <?= $validationResult['current_activations'] ?>/<?= $validationResult['max_activations'] ?></p>
                        <?php else: ?>
                            <p style="color: #dc3545; font-size: 18px;">✗ <?= $validationResult['message'] ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- License Table -->
        <div class="card" style="margin-top: 30px;">
            <h2>📋 All Licenses</h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>License Key</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Activations</th>
                            <th>Expires</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($licenses as $license): ?>
                            <tr>
                                <td style="font-family: monospace; color: #00ff88;">
                                    <?= htmlspecialchars($license['license_key']) ?>
                                </td>
                                <td><?= $license['license_type'] ?></td>
                                <td>
                                    <span class="badge badge-<?= $license['status'] ?>">
                                        <?= ucfirst($license['status']) ?>
                                    </span>
                                </td>
                                <td><?= $license['current_activations'] ?>/<?= $license['max_activations'] ?></td>
                                <td><?= $license['expires_at'] ?? 'Never' ?></td>
                                <td><?= date('d M Y', strtotime($license['created_at'])) ?></td>
                                <td>
                                    <?php if ($license['status'] === 'active'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="license_key" value="<?= $license['license_key'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Revoke license ini?')">Revoke</button>
                                        </form>
                                    <?php endif; ?>
                                    <button class="btn btn-sm" onclick="copyKey('<?= $license['license_key'] ?>')" style="background:#333;color:#fff;">Copy</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function copyKey(key) {
            navigator.clipboard.writeText(key).then(() => {
                alert('License key copied!');
            });
        }
    </script>
</body>
</html>
