<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/middleware/Auth.php';

Auth::requireAuth();

$current_user = $_SESSION['user'] ?? null;
$event_id = $_GET['event_id'] ?? null;

// Get event details if event_id is provided
$event_details = null;
$all_events = [];
if ($event_id) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM events WHERE id = :event_id");
        $stmt->bindParam(':event_id', $event_id, PDO::PARAM_INT);
        $stmt->execute();
        $event_details = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching event details: " . $e->getMessage());
    }
}

// Get all available events for admin/faculty
try {
    $db = Database::getInstance()->getConnection();
    if ($current_user['role'] === 'admin') {
        $stmt = $db->prepare("SELECT id, event_name, event_date FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 20");
    } else if ($current_user['role'] === 'faculty') {
        $stmt = $db->prepare("SELECT id, event_name, event_date FROM events WHERE created_by = :user_id AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 20");
        $stmt->bindParam(':user_id', $current_user['id']);
    } else {
        $stmt = $db->prepare("SELECT id, event_name, event_date FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 10");
    }
    $stmt->execute();
    $all_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching events: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎫 QR Code Scanner - Professional Attendance System</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    
    <style>
        /* Professional QR Scanner - Enhanced Glassmorphism Design */
        body {
            padding-left: 0 !important;
            background: var(--bg-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        .main-content {
            min-height: 100vh;
            padding: 2rem;
            background: var(--bg-primary);
            position: relative;
        }
        
        /* Premium Background Effect */
        .main-content::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 15% 25%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 85% 75%, rgba(139, 92, 246, 0.15) 0%, transparent 50%);
            animation: backgroundPulse 20s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }
        
        .scanner-container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .scanner-header {
            text-align: center;
            margin-bottom: 2rem;
            background: var(--glass-bg);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        .scanner-header h1 {
            margin: 0 0 1rem 0;
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .event-selector {
            margin: 1.5rem 0;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .event-selector select {
            width: 100%;
            padding: 1rem;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 1rem;
            backdrop-filter: blur(10px);
            cursor: pointer;
        }
        
        .scanner-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .scanner-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 1rem;
            }
        }
        
        .scanner-section {
            background: var(--glass-bg);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        #qr-reader {
            width: 100%;
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: var(--bg-secondary);
            min-height: 300px;
            position: relative;
        }
        
        .scanner-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .btn-scanner {
            flex: 1;
            min-width: 120px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.25rem;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .btn-scanner::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.4s, height 0.4s;
        }
        
        .btn-scanner:active::before {
            width: 200px;
            height: 200px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        }
        
        .btn-secondary {
            background: var(--glass-bg);
            color: var(--text-primary);
            border-color: var(--glass-border);
        }
        
        .btn-secondary:hover {
            background: var(--glass-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--error), var(--error-dark));
            color: white;
            border-color: var(--error);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            color: white;
            border-color: var(--success);
        }
        
        .manual-entry {
            margin-top: 1rem;
        }
        
        .manual-entry input {
            width: 100%;
            padding: 1rem;
            background: var(--bg-card);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 1rem;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 1px;
            text-align: center;
            margin-bottom: 0.75rem;
        }
        
        .manual-entry input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .stat-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            padding: 1rem;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .scan-log {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 1rem;
        }
        
        .log-item {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            padding: 1rem;
            margin-bottom: 0.75rem;
            backdrop-filter: blur(10px);
        }
        
        .log-item.success {
            border-left: 4px solid var(--success);
        }
        
        .log-item.error {
            border-left: 4px solid var(--error);
        }
        
        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .log-status {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .log-time {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .log-details {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .sound-toggle {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
            padding: 0.75rem;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }
        
        .sound-toggle:hover {
            transform: scale(1.1);
            background: var(--glass-hover);
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: white;
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes backgroundPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
    </style>
</head>
<body>
    <div class="sound-toggle" id="soundToggle" onclick="toggleSound()" title="Toggle Sound">
        🔊
    </div>

    <div class="main-content">
        <div class="scanner-container">
            <div class="scanner-header">
                <h1>🎫 Professional QR Scanner</h1>
                
                <?php if (count($all_events) > 0): ?>
                <div class="event-selector">
                    <select id="eventSelect" onchange="changeEvent(this.value)">
                        <option value="">Select Event for Attendance Tracking</option>
                        <?php foreach ($all_events as $event): ?>
                            <option value="<?php echo $event['id']; ?>" <?php echo ($event_id == $event['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($event['event_name']); ?> - <?php echo date('M j, Y g:i A', strtotime($event['event_date'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if ($event_details): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: rgba(99, 102, 241, 0.1); border-radius: 12px; border: 1px solid rgba(99, 102, 241, 0.2);">
                        <h2 style="margin: 0 0 0.5rem 0; color: var(--primary); font-size: 1.5rem;"><?php echo htmlspecialchars($event_details['event_name']); ?></h2>
                        <p style="margin: 0; color: var(--text-secondary);">
                            📅 <?php echo date('F j, Y g:i A', strtotime($event_details['event_date'])); ?> | 
                            📍 <?php echo htmlspecialchars($event_details['venue']); ?>
                        </p>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); margin-top: 1rem;">Select an event above to begin attendance tracking</p>
                <?php endif; ?>
            </div>
            
            <div class="scanner-grid">
                <!-- Camera Scanner Section -->
                <div class="scanner-section">
                    <div class="section-title">
                        📷 Camera Scanner
                    </div>
                    
                    <div style="position: relative;">
                        <div id="qr-reader"></div>
                        <div class="loading-overlay" id="loadingOverlay" style="display: none;">
                            <div class="loading-spinner"></div>
                            <div>Initializing camera...</div>
                        </div>
                    </div>
                    
                    <div class="scanner-controls">
                        <button class="btn-scanner btn-primary" id="startBtn" onclick="startScanning()">
                            📷 Start Camera
                        </button>
                        <button class="btn-scanner btn-danger" id="stopBtn" onclick="stopScanning()" style="display: none;">
                            ⏹️ Stop Scanner
                        </button>
                        <button class="btn-scanner btn-secondary" id="switchBtn" onclick="switchCamera()" style="display: none;">
                            🔄 Switch Camera
                        </button>
                    </div>
                    
                    <div class="manual-entry">
                        <input type="text" id="manualCode" placeholder="Or enter QR code manually..." maxlength="50">
                        <button class="btn-scanner btn-success" onclick="processManualCode()" style="width: 100%;">
                            ⌨️ Process Manual Code
                        </button>
                    </div>
                </div>
                
                <!-- Statistics & Logs Section -->
                <div class="scanner-section">
                    <div class="section-title">
                        📊 Live Statistics
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number" id="scanCount">0</div>
                            <div class="stat-label">Total Scans</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="successCount" style="color: var(--success);">0</div>
                            <div class="stat-label">Successful</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="errorCount" style="color: var(--error);">0</div>
                            <div class="stat-label">Failed</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="duplicateCount" style="color: var(--warning);">0</div>
                            <div class="stat-label">Duplicates</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <div class="section-title" style="margin: 0;">📝 Scan Log</div>
                            <button class="btn-scanner btn-secondary" onclick="clearLog()" style="min-width: auto; padding: 0.5rem 1rem;">
                                🗑️ Clear
                            </button>
                        </div>
                        
                        <div class="scan-log" id="scanLog">
                            <div style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                No scans yet. Start scanning to see results here.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 2rem;">
                <button class="btn-scanner btn-secondary" onclick="exportLog()">
                    📊 Export Results
                </button>
                <button class="btn-scanner btn-secondary" onclick="refreshStats()">
                    🔄 Refresh Stats
                </button>
                <button class="btn-scanner btn-secondary" onclick="showFullscreen()">
                    🖥️ Fullscreen
                </button>
                <button class="btn-scanner btn-secondary" onclick="location.href='<?php echo BASE_URL; ?>/attendance-dashboard.php'">
                    📋 Dashboard
                </button>
            </div>
        </div>
    </div>
        
        <!-- Statistics Panel -->
        <div class="stats-panel" id="statsPanel" style="display: none;">
            <h3>Real-time Statistics</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number" id="totalScanned">0</div>
                    <div class="stat-label">Scanned Today</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="totalAttended">0</div>
                    <div class="stat-label">Attended</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="attendanceRate">0%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="duplicateScans">0</div>
                    <div class="stat-label">Duplicate Scans</div>
                </div>
            </div>
        </div>
        
        <!-- Scanner Mode Selection -->
        <div class="scanner-modes">
            <button class="mode-btn active" onclick="setMode('camera')">📷 Camera Scanner</button>
            <button class="mode-btn" onclick="setMode('manual')">⌨️ Manual Entry</button>
            <button class="mode-btn" onclick="setMode('bulk')">📋 Bulk Operations</button>
        </div>
        
        <!-- Camera Scanner Section -->
        <div class="scanner-section" id="cameraSection">
            <div id="qr-scanner"></div>
            <div class="camera-controls">
                <button class="btn btn-primary" id="startButton" onclick="startScanner()">Start Camera</button>
                <button class="btn btn-secondary" id="stopButton" onclick="stopScanner()" style="display: none;">Stop Camera</button>
                <button class="btn btn-outline" onclick="switchCamera()">Switch Camera</button>
            </div>
        </div>
        
        <!-- Manual Entry Section -->
        <div class="scanner-section" id="manualSection" style="display: none;">
            <div class="manual-input">
                <div class="input-group">
                    <input type="text" id="manualQRInput" placeholder="Enter QR code or registration ID" 
                           autocomplete="off" maxlength="50">
                    <button class="btn btn-primary" onclick="processManualQR()">Verify</button>
                </div>
            </div>
        </div>
        
        <!-- Bulk Operations Section -->
        <div class="scanner-section" id="bulkSection" style="display: none;">
            <div class="bulk-operations">
                <p>Bulk attendance operations</p>
                <div class="action-buttons">
                    <button class="btn btn-success" onclick="bulkMarkAttended()">Mark All as Attended</button>
                    <button class="btn btn-warning" onclick="bulkMarkAbsent()">Mark Absent as Absent</button>
                    <button class="btn btn-outline" onclick="exportAttendance()">Export Attendance</button>
                </div>
            </div>
        </div>
        
        <!-- Results Section -->
        <div class="result-section" id="resultSection">
            <div class="result-card" id="resultCard">
                <!-- Scan results will be populated here -->
            </div>
        </div>
        
        <!-- Recent Scans History -->
        <div class="scan-history" id="scanHistory">
            <h3>Recent Scans</h3>
            <div id="historyList">
                <!-- History items will be populated here -->
            </div>
        </div>
    </div>
    
    <!-- Audio elements for feedback -->
    <audio id="successSound" preload="auto">
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LFeSMFl2+z9N2bPQYUXrLl6KNODQlTouDxtmQdBjaz1/LGeSMFl2+z9N2bPQYUXrLl6KNODQlTouDxtmQdBjaz1/LGeSMFl2+z9N2bPQYUXrLl6KNODQlTouDxtmQdBjaz1/LGeSMFl2+z9N2bPQYUXrLl6KNODQlTouDxtmQdBjaz1/LGeSMFl2+z9N2bPQYUXrLl6KNODQlTouDxtmQdBjaz1/LGeSMFl2+z9N2bPQYUXrLl6KNODQlTouDxtmQdBjaz1/LGeSMFl2+z9N2bPQYUXrLl6KNODQlTouDxtmQdBjaz1/LGeSMFl2+z9N2bPQYUXrLl6KNODQlTouDxtmQdBjaz1/LGeSMFl2+z9N2bPQYUXrLl6KNODQlTouDxtmQdBjaz1/LGeSMFl2+z9N2bPQYUXrLl6KNODQlTouDxtmQdBjaz1/LGeSMF" type="audio/wav">
    </audio>
    
    <audio id="errorSound" preload="auto">
        <source src="data:audio/wav;base64,UklGRu4BAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQoBAAC9jBc03Ps63/vF4t+vkcHDu9+wm8PD29/g4u4l5NrTndXKzsm0u7+6tb63sbu2tLi1t7i2t7i2trm2s7q3s7y0s7+zsb61sL+3r8K1rsMvr8UzrbLZ" type="audio/wav">
    </audio>
    
    <script>
        class ProfessionalQRScanner {
            constructor() {
                this.html5QrCode = new Html5Qrcode("qr-reader");
                this.isScanning = false;
                this.currentCameraId = null;
                this.availableCameras = [];
                this.soundEnabled = true;
                this.eventId = <?php echo json_encode($event_id); ?>;
                
                this.stats = {
                    scanned: parseInt(localStorage.getItem('desktopScanCount') || '0'),
                    success: parseInt(localStorage.getItem('desktopSuccessCount') || '0'),
                    errors: parseInt(localStorage.getItem('desktopErrorCount') || '0'),
                    duplicates: parseInt(localStorage.getItem('desktopDuplicateCount') || '0'),
                    scanLog: JSON.parse(localStorage.getItem('desktopScanLog') || '[]')
                };
                
                this.init();
            }
            
            async init() {
                this.updateStatsDisplay();
                this.renderScanLog();
                await this.loadCameras();
                this.setupEventListeners();
            }
            
            setupEventListeners() {
                // Manual input handling
                const manualInput = document.getElementById('manualCode');
                if (manualInput) {
                    manualInput.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            this.processManualCode();
                        }
                    });
                    
                    manualInput.addEventListener('input', (e) => {
                        e.target.value = e.target.value.toUpperCase();
                    });
                }
                
                // Keyboard shortcuts
                document.addEventListener('keydown', (e) => {
                    if (e.ctrlKey || e.metaKey) return;
                    
                    switch(e.key.toLowerCase()) {
                        case 's':
                            if (!this.isScanning) this.startScanning();
                            break;
                        case 'q':
                            if (this.isScanning) this.stopScanning();
                            break;
                        case 'c':
                            this.switchCamera();
                            break;
                        case 'm':
                            document.getElementById('manualCode').focus();
                            break;
                        case 'f':
                            this.showFullscreen();
                            break;
                    }
                });
            }
            
            async loadCameras() {
                try {
                    const devices = await Html5Qrcode.getCameras();
                    this.availableCameras = devices;
                    
                    if (devices.length > 0) {
                        this.currentCameraId = devices[0].id;
                        
                        if (devices.length > 1) {
                            document.getElementById('switchBtn').style.display = 'inline-flex';
                        }
                        
                        console.log(`Found ${devices.length} camera(s)`);
                        this.showToast(`📷 Found ${devices.length} camera(s)`, 'success');
                    } else {
                        this.showToast('❌ No cameras available', 'error');
                    }
                    
                    return devices.length > 0;
                } catch (error) {
                    console.error('Error loading cameras:', error);
                    this.showToast('❌ Camera access denied', 'error');
                    return false;
                }
            }
            
            async startScanning() {
                if (!this.eventId) {
                    this.showToast('⚠️ Please select an event first', 'warning');
                    return;
                }
                
                if (this.availableCameras.length === 0) {
                    await this.loadCameras();
                    if (this.availableCameras.length === 0) {
                        return;
                    }
                }
                
                this.showLoading(true);
                
                try {
                    const config = {
                        fps: 10,
                        qrbox: { width: 350, height: 350 },
                        aspectRatio: 1.0,
                        experimentalFeatures: {
                            useBarCodeDetectorIfSupported: true
                        }
                    };
                    
                    await this.html5QrCode.start(
                        this.currentCameraId,
                        config,
                        this.onScanSuccess.bind(this),
                        this.onScanError.bind(this)
                    );
                    
                    this.isScanning = true;
                    this.updateScannerUI();
                    this.showToast('📷 Scanner started successfully', 'success');
                    
                } catch (error) {
                    console.error('Error starting scanner:', error);
                    this.showToast('❌ Failed to start camera', 'error');
                } finally {
                    this.showLoading(false);
                }
            }
            
            async stopScanning() {
                if (this.isScanning) {
                    try {
                        await this.html5QrCode.stop();
                        this.isScanning = false;
                        this.updateScannerUI();
                        this.showToast('⏹️ Scanner stopped', 'info');
                    } catch (error) {
                        console.error('Error stopping scanner:', error);
                    }
                }
            }
            
            async switchCamera() {
                if (this.availableCameras.length <= 1) {
                    this.showToast('⚠️ No other cameras available', 'warning');
                    return;
                }
                
                const wasScanning = this.isScanning;
                
                if (wasScanning) {
                    await this.stopScanning();
                }
                
                // Find next camera
                const currentIndex = this.availableCameras.findIndex(cam => cam.id === this.currentCameraId);
                const nextIndex = (currentIndex + 1) % this.availableCameras.length;
                this.currentCameraId = this.availableCameras[nextIndex].id;
                
                if (wasScanning) {
                    setTimeout(() => this.startScanning(), 500);
                }
                
                this.showToast(`🔄 Switched to ${this.availableCameras[nextIndex].label || 'Camera ' + (nextIndex + 1)}`, 'info');
            }
            
            onScanSuccess(decodedText, decodedResult) {
                this.processQRCode(decodedText);
            }
            
            onScanError(error) {
                // Silently handle scan errors (too verbose otherwise)
                if (error.includes('NotFoundException')) {
                    return; // No QR code found - this is normal
                }
                console.debug('Scan error:', error);
            }
            
            async processQRCode(qrCode) {
                if (!this.eventId) {
                    this.showToast('⚠️ No event selected', 'warning');
                    return;
                }
                
                this.updateStats('scanned');
                this.showToast('🔍 Processing QR code...', 'info');
                
                try {
                    const response = await fetch(`<?php echo BASE_URL; ?>/api/attendance.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'mark_attendance',
                            event_id: this.eventId,
                            qr_code: qrCode,
                            scan_method: 'camera'
                        })
                    });
                    
                    const result = await response.json();
                    this.handleScanResult(result, qrCode);
                    
                } catch (error) {
                    console.error('Error processing QR code:', error);
                    this.updateStats('errors');
                    this.addToLog('error', qrCode, 'Network error');
                    this.showToast('❌ Network error. Please try again.', 'error');
                }
            }
            
            handleScanResult(result, qrCode) {
                if (result.success) {
                    this.updateStats('success');
                    this.addToLog('success', qrCode, result.data.user_name || 'Unknown', result.data);
                    this.showToast(`✅ ${result.data.user_name} - ${result.message}`, 'success');
                    this.playSuccessSound();
                } else {
                    if (result.message && result.message.toLowerCase().includes('duplicate')) {
                        this.updateStats('duplicates');
                        this.addToLog('warning', qrCode, result.message);
                        this.showToast(`⚠️ ${result.message}`, 'warning');
                        this.playWarningSound();
                    } else {
                        this.updateStats('errors');
                        this.addToLog('error', qrCode, result.message || 'Unknown error');
                        this.showToast(`❌ ${result.message}`, 'error');
                        this.playErrorSound();
                    }
                }
            }
            
            async processManualCode() {
                const input = document.getElementById('manualCode');
                const code = input.value.trim();
                
                if (!code) {
                    this.showToast('⚠️ Please enter a QR code', 'warning');
                    input.focus();
                    return;
                }
                
                input.value = '';
                await this.processQRCode(code);
            }
            
            addToLog(type, qrCode, message, data = null) {
                const logEntry = {
                    timestamp: new Date().toISOString(),
                    type: type,
                    qr_code: qrCode,
                    message: message,
                    data: data
                };
                
                this.stats.scanLog.unshift(logEntry);
                
                // Keep only last 50 entries
                if (this.stats.scanLog.length > 50) {
                    this.stats.scanLog = this.stats.scanLog.slice(0, 50);
                }
                
                localStorage.setItem('desktopScanLog', JSON.stringify(this.stats.scanLog));
                this.renderScanLog();
            }
            
            renderScanLog() {
                const logContainer = document.getElementById('scanLog');
                
                if (this.stats.scanLog.length === 0) {
                    logContainer.innerHTML = `
                        <div style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            No scans yet. Start scanning to see results here.
                        </div>
                    `;
                    return;
                }
                
                logContainer.innerHTML = this.stats.scanLog.map(entry => {
                    const time = new Date(entry.timestamp).toLocaleTimeString();
                    const icon = {
                        'success': '✅',
                        'error': '❌',
                        'warning': '⚠️'
                    }[entry.type] || '📋';
                    
                    return `
                        <div class="log-item ${entry.type}">
                            <div class="log-header">
                                <div class="log-status">
                                    ${icon} ${entry.message}
                                </div>
                                <div class="log-time">${time}</div>
                            </div>
                            <div class="log-details">
                                Code: <code style="background: var(--glass-bg); padding: 0.2rem 0.4rem; border-radius: 4px; font-family: monospace;">${entry.qr_code}</code>
                                ${entry.data && entry.data.user_email ? `<br>Email: ${entry.data.user_email}` : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            }
            
            updateStats(type) {
                this.stats[type]++;
                localStorage.setItem(`desktop${type.charAt(0).toUpperCase() + type.slice(1)}Count`, this.stats[type].toString());
                this.updateStatsDisplay();
            }
            
            updateStatsDisplay() {
                document.getElementById('scanCount').textContent = this.stats.scanned;
                document.getElementById('successCount').textContent = this.stats.success;
                document.getElementById('errorCount').textContent = this.stats.errors;
                document.getElementById('duplicateCount').textContent = this.stats.duplicates;
            }
            
            updateScannerUI() {
                const startBtn = document.getElementById('startBtn');
                const stopBtn = document.getElementById('stopBtn');
                const switchBtn = document.getElementById('switchBtn');
                
                if (this.isScanning) {
                    startBtn.style.display = 'none';
                    stopBtn.style.display = 'inline-flex';
                    switchBtn.style.display = this.availableCameras.length > 1 ? 'inline-flex' : 'none';
                } else {
                    startBtn.style.display = 'inline-flex';
                    stopBtn.style.display = 'none';
                    switchBtn.style.display = 'none';
                }
            }
            
            showLoading(show) {
                document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
            }
            
            clearLog() {
                if (confirm('Are you sure you want to clear the scan log?')) {
                    this.stats.scanLog = [];
                    localStorage.removeItem('desktopScanLog');
                    this.renderScanLog();
                    this.showToast('🗑️ Scan log cleared', 'info');
                }
            }
            
            refreshStats() {
                // Reset session stats
                this.stats = {
                    scanned: 0,
                    success: 0,
                    errors: 0,
                    duplicates: 0,
                    scanLog: []
                };
                
                // Clear localStorage
                ['desktopScanCount', 'desktopSuccessCount', 'desktopErrorCount', 'desktopDuplicateCount', 'desktopScanLog'].forEach(key => {
                    localStorage.removeItem(key);
                });
                
                this.updateStatsDisplay();
                this.renderScanLog();
                this.showToast('🔄 Statistics refreshed', 'info');
            }
            
            exportLog() {
                if (this.stats.scanLog.length === 0) {
                    this.showToast('⚠️ No data to export', 'warning');
                    return;
                }
                
                const csvContent = 'Timestamp,Type,QR Code,Message,User Email\\n' +
                    this.stats.scanLog.map(entry => {
                        return [
                            new Date(entry.timestamp).toLocaleString(),
                            entry.type,
                            entry.qr_code,
                            entry.message.replace(/,/g, ';'),
                            (entry.data && entry.data.user_email) || ''
                        ].map(field => `"${field}"`).join(',');
                    }).join('\\n');
                
                const blob = new Blob([csvContent], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `qr-scan-log-${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                this.showToast('📊 Export completed', 'success');
            }
            
            showFullscreen() {
                const element = document.documentElement;
                if (element.requestFullscreen) {
                    element.requestFullscreen();
                } else if (element.webkitRequestFullscreen) {
                    element.webkitRequestFullscreen();
                } else if (element.msRequestFullscreen) {
                    element.msRequestFullscreen();
                }
            }
            
            showToast(message, type = 'info') {
                // Create and show toast notification
                const toast = document.createElement('div');
                toast.style.cssText = `
                    position: fixed;
                    top: 2rem;
                    left: 50%;
                    transform: translateX(-50%) translateY(-100px);
                    background: var(--glass-bg);
                    backdrop-filter: blur(20px);
                    border: 1px solid var(--glass-border);
                    border-radius: var(--radius);
                    padding: 1rem 1.5rem;
                    color: var(--text-primary);
                    z-index: 10000;
                    opacity: 0;
                    transition: all 0.3s ease;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
                    max-width: 400px;
                    text-align: center;
                `;
                
                const colors = {
                    success: 'rgba(16, 185, 129, 0.2)',
                    error: 'rgba(239, 68, 68, 0.2)',
                    warning: 'rgba(245, 158, 11, 0.2)',
                    info: 'rgba(99, 102, 241, 0.2)'
                };
                
                if (colors[type]) {
                    toast.style.background = colors[type];
                }
                
                toast.textContent = message;
                document.body.appendChild(toast);
                
                requestAnimationFrame(() => {
                    toast.style.transform = 'translateX(-50%) translateY(0)';
                    toast.style.opacity = '1';
                });
                
                setTimeout(() => {
                    toast.style.transform = 'translateX(-50%) translateY(-100px)';
                    toast.style.opacity = '0';
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }, 4000);
            }
            
            playSuccessSound() {
                if (!this.soundEnabled) return;
                const context = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = context.createOscillator();
                const gainNode = context.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(context.destination);
                
                oscillator.frequency.value = 800;
                oscillator.type = 'sine';
                
                gainNode.gain.setValueAtTime(0.3, context.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.5);
                
                oscillator.start(context.currentTime);
                oscillator.stop(context.currentTime + 0.5);
            }
            
            playErrorSound() {
                if (!this.soundEnabled) return;
                const context = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = context.createOscillator();
                const gainNode = context.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(context.destination);
                
                oscillator.frequency.value = 300;
                oscillator.type = 'sawtooth';
                
                gainNode.gain.setValueAtTime(0.2, context.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.8);
                
                oscillator.start(context.currentTime);
                oscillator.stop(context.currentTime + 0.8);
            }
            
            playWarningSound() {
                if (!this.soundEnabled) return;
                const context = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = context.createOscillator();
                const gainNode = context.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(context.destination);
                
                oscillator.frequency.value = 500;
                oscillator.type = 'triangle';
                
                gainNode.gain.setValueAtTime(0.25, context.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.6);
                
                oscillator.start(context.currentTime);
                oscillator.stop(context.currentTime + 0.6);
            }
        }
        
        // Global functions for backward compatibility
        let scanner;
        
        function changeEvent(eventId) {
            if (eventId) {
                window.location.href = `<?php echo BASE_URL; ?>/qr-scanner.php?event_id=${eventId}`;
            } else {
                window.location.href = `<?php echo BASE_URL; ?>/qr-scanner.php`;
            }
        }
        
        function toggleSound() {
            scanner.soundEnabled = !scanner.soundEnabled;
            const toggle = document.getElementById('soundToggle');
            toggle.textContent = scanner.soundEnabled ? '🔊' : '🔇';
            toggle.title = scanner.soundEnabled ? 'Disable Sound' : 'Enable Sound';
        }
        
        function startScanning() { scanner.startScanning(); }
        function stopScanning() { scanner.stopScanning(); }
        function switchCamera() { scanner.switchCamera(); }
        function processManualCode() { scanner.processManualCode(); }
        function clearLog() { scanner.clearLog(); }
        function refreshStats() { scanner.refreshStats(); }
        function exportLog() { scanner.exportLog(); }
        function showFullscreen() { scanner.showFullscreen(); }
        
        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            scanner = new ProfessionalQRScanner();
        });
    </script>
</body>
</html>
