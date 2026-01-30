<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/middleware/Auth.php';

Auth::requireAuth();

$current_user = $_SESSION['user'] ?? null;
$event_id = $_GET['event_id'] ?? null;

// Get event details if event_id is provided
$event_details = null;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Mobile QR Scanner - <?php echo APP_NAME; ?></title>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="QR Scanner">
    <meta name="theme-color" content="#000000">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">
    
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        /* Mobile Scanner - Professional Glassmorphism Design */
        * { box-sizing: border-box; }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            overflow-x: hidden;
            position: relative;
            min-height: 100vh;
        }
        
        /* Premium Background Effect */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.2) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(139, 92, 246, 0.2) 0%, transparent 40%);
            animation: backgroundPulse 15s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }
        
        .mobile-scanner {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
        }
        
        /* Enhanced Header */
        .scanner-header {
            background: var(--glass-bg);
            backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid var(--glass-border);
            padding: 1.5rem 1rem;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .scanner-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 0.75rem 0;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .event-selector {
            margin: 1rem 0;
        }
        
        .event-selector select {
            width: 100%;
            padding: 0.875rem;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 0.95rem;
            backdrop-filter: blur(10px);
        }
        
        .event-info {
            font-size: 0.95rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            padding: 0.75rem;
            background: rgba(99, 102, 241, 0.1);
            border-radius: var(--radius-sm);
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        /* Main Scanner Area */
        .scanner-main {
            flex: 1;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .camera-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 1rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        #qr-reader {
            width: 100%;
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: var(--bg-secondary);
        }
        
        /* Enhanced Scanner Overlay */
        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 220px;
            height: 220px;
            border: 2px solid var(--primary);
            border-radius: var(--radius);
            pointer-events: none;
            z-index: 10;
            animation: scannerPulse 2s ease-in-out infinite;
        }
        
        .scanner-overlay::before,
        .scanner-overlay::after {
            content: '';
            position: absolute;
            width: 30px;
            height: 30px;
            border: 3px solid var(--accent);
            border-radius: 4px;
        }
        
        .scanner-overlay::before {
            top: -3px;
            left: -3px;
            border-right: none;
            border-bottom: none;
        }
        
        .scanner-overlay::after {
            bottom: -3px;
            right: -3px;
            border-left: none;
            border-top: none;
        }
        
        @keyframes scannerPulse {
            0%, 100% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
            50% { opacity: 0.7; transform: translate(-50%, -50%) scale(1.05); }
        }
        
        /* Professional Control Buttons */
        .control-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        
        .mobile-btn {
            padding: 1rem;
            background: var(--glass-bg);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
            border-radius: var(--radius);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }
        
        .mobile-btn::before {
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
        
        .mobile-btn:active::before {
            width: 200px;
            height: 200px;
        }
        
        .mobile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        }
        
        .mobile-btn.primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-color: var(--primary);
            color: white;
        }
        
        .mobile-btn.secondary {
            background: rgba(139, 92, 246, 0.1);
            border-color: rgba(139, 92, 246, 0.3);
            color: var(--accent-light);
        }
        
        .mobile-btn.danger {
            background: linear-gradient(135deg, var(--error), var(--error-dark));
            border-color: var(--error);
            color: white;
        }
        
        .mobile-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Manual Input Section */
        .manual-input {
            background: var(--glass-bg);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 1rem;
            margin-top: 0.5rem;
            display: none;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }
        
        .manual-input.show { display: block; }
        
        .manual-input input {
            width: 100%;
            padding: 1rem;
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 1rem;
            text-align: center;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        
        .manual-input input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .manual-input input::placeholder {
            color: var(--text-muted);
        }
        
        /* Enhanced Result Popup */
        .result-popup {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border-radius: 24px 24px 0 0;
            padding: 1.5rem;
            transform: translateY(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid var(--glass-border);
            box-shadow: 0 -8px 40px rgba(0, 0, 0, 0.6);
        }
        
        .result-popup.show {
            transform: translateY(0);
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border);
        }
        
        .result-status {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .close-btn {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
            padding: 0.5rem;
            border-radius: 50%;
            font-size: 1.25rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-btn:hover {
            background: var(--glass-hover);
        }
        
        /* Statistics Bar */
        .stats-bar {
            background: var(--glass-bg);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 1rem;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            text-align: center;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Professional Status Indicators */
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
            box-shadow: 0 0 8px rgba(255, 255, 255, 0.3);
        }
        
        .status-success { background: var(--success); }
        .status-warning { background: var(--warning); }
        .status-error { background: var(--error); }
        
        /* Torch Button */
        .torch-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
            padding: 0.75rem;
            border-radius: 50%;
            font-size: 1.25rem;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 20;
        }
        
        .torch-btn:hover {
            background: var(--glass-hover);
            transform: scale(1.1);
        }
        
        /* Loading State */
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
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid var(--primary);
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
        
        .vibration-feedback {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @media (max-height: 600px) {
            .scanner-header {
                padding: 0.5rem;
            }
            
            .scanner-main {
                padding: 0.5rem;
            }
            
            .result-popup {
                max-height: 60vh;
            }
        }
    </style>
</head>
<body>
    <div class="mobile-scanner">
        <div class="scanner-header">
            <div class="scanner-title">📱 Mobile QR Scanner</div>
            
            <?php if (count($all_events) > 0): ?>
            <div class="event-selector">
                <select id="eventSelect" onchange="changeEvent(this.value)">
                    <option value="">Select Event to Scan For</option>
                    <?php foreach ($all_events as $event): ?>
                        <option value="<?php echo $event['id']; ?>" <?php echo ($event_id == $event['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($event['event_name']); ?> - <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if ($event_details): ?>
                <div class="event-info">
                    🎯 <strong><?php echo htmlspecialchars($event_details['event_name']); ?></strong><br>
                    📅 <?php echo date('F j, Y g:i A', strtotime($event_details['event_date'])); ?><br>
                    📍 <?php echo htmlspecialchars($event_details['venue']); ?>
                </div>
            <?php else: ?>
                <div class="event-info">
                    ⚡ Ready to scan QR codes<br>
                    Select an event above to begin attendance marking
                </div>
            <?php endif; ?>
        </div>
        
        <div class="scanner-main">
            <!-- Enhanced Stats Bar -->
            <div class="stats-bar">
                <div>
                    <div class="stat-number" id="scanCount">0</div>
                    <div class="stat-label">Total Scans</div>
                </div>
                <div>
                    <div class="stat-number success" id="successCount">0</div>
                    <div class="stat-label">Successful</div>
                </div>
                <div>
                    <div class="stat-number error" id="errorCount">0</div>
                    <div class="stat-label">Failed</div>
                </div>
            </div>
            
            <!-- Enhanced Camera Container -->
            <div class="camera-container" id="cameraContainer">
                <div id="qr-reader"></div>
                <div class="scanner-overlay"></div>
                <button class="torch-btn" id="torchBtn" onclick="toggleTorch()" style="display: none;" title="Toggle Flashlight">
                    🔦
                </button>
                <div class="loading-overlay" id="loadingOverlay" style="display: none;">
                    <div class="loading-spinner"></div>
                    <div>Initializing camera...</div>
                </div>
            </div>
            
            <!-- Enhanced Control Buttons -->
            <div class="control-buttons">
                <button class="mobile-btn primary" id="startBtn" onclick="startScanning()">
                    📷 Start Scanner
                </button>
                <button class="mobile-btn danger" id="stopBtn" onclick="stopScanning()" style="display: none;">
                    ⏹️ Stop Scanner
                </button>
            </div>
            
            <div class="control-buttons">
                <button class="mobile-btn secondary" onclick="toggleManualInput()" id="manualBtn">
                    ⌨️ Manual Entry
                </button>
                <button class="mobile-btn secondary" onclick="switchCamera()" id="switchBtn" style="display: none;">
                    🔄 Switch Camera
                </button>
            </div>
            
            <!-- Enhanced Manual Input -->
            <div class="manual-input" id="manualInput">
                <input type="text" id="manualCode" placeholder="Enter QR code manually" maxlength="50">
                <div style="margin-top: 0.75rem;">
                    <button class="mobile-btn primary" onclick="processManualCode()" style="width: 100%;">
                        ✓ Process Code
                    </button>
                </div>
            </div>
        </div>
    </div>
                </button>
            </div>
            
            <!-- Manual Input -->
            <div class="manual-input" id="manualInput">
                <input type="text" id="manualCode" placeholder="Enter QR code" 
                       onkeypress="handleManualKeypress(event)">
                <button class="mobile-btn primary" style="margin-top: 1rem;" onclick="processManualCode()">
                    ✅ Process Code
                </button>
            </div>
    <!-- Enhanced Result Popup -->
    <div class="result-popup" id="resultPopup">
        <div class="result-header">
            <div class="result-status" id="resultStatus"></div>
            <button class="close-btn" onclick="closeResult()">✕</button>
        </div>
        <div id="participantDetails"></div>
    </div>
    
    <script>
        class MobileQRScanner {
            constructor() {
                this.html5QrCode = new Html5Qrcode("qr-reader");
                this.isScanning = false;
                this.currentCamera = 0;
                this.availableCameras = [];
                this.torchSupported = false;
                this.torchOn = false;
                this.eventId = <?php echo json_encode($event_id); ?>;
                
                this.stats = {
                    scanned: parseInt(localStorage.getItem('scanCount') || '0'),
                    success: parseInt(localStorage.getItem('successCount') || '0'),
                    errors: parseInt(localStorage.getItem('errorCount') || '0')
                };
                
                this.init();
            }
            
            async init() {
                this.updateStatsDisplay();
                await this.loadCameras();
                this.setupEventListeners();
                this.checkAutoStart();
            }
            
            setupEventListeners() {
                // Prevent zoom on double tap
                let lastTouchEnd = 0;
                document.addEventListener('touchend', (event) => {
                    const now = Date.now();
                    if (now - lastTouchEnd <= 300) {
                        event.preventDefault();
                    }
                    lastTouchEnd = now;
                }, false);
                
                // Handle manual input
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
            }
            
            async loadCameras() {
                try {
                    const devices = await Html5Qrcode.getCameras();
                    this.availableCameras = devices;
                    
                    if (devices.length > 1) {
                        document.getElementById('switchBtn').style.display = 'block';
                    }
                    
                    return devices.length > 0;
                } catch (error) {
                    console.error('Error loading cameras:', error);
                    this.showToast('❌ Camera access denied or not available', 'error');
                    return false;
                }
            }
            
            checkAutoStart() {
                // Auto-start if event is selected and cameras available
                if (this.eventId && this.availableCameras.length > 0) {
                    setTimeout(() => {
                        if (!this.isScanning) {
                            this.startScanning();
                        }
                    }, 1000);
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
                        this.showToast('❌ No cameras available', 'error');
                        return;
                    }
                }
                
                this.showLoading(true);
                
                try {
                    const cameraId = this.availableCameras[this.currentCamera]?.id;
                    const config = {
                        fps: 10,
                        qrbox: { width: 250, height: 250 },
                        aspectRatio: 1.0,
                        experimentalFeatures: {
                            useBarCodeDetectorIfSupported: true
                        }
                    };
                    
                    await this.html5QrCode.start(
                        cameraId,
                        config,
                        this.onScanSuccess.bind(this),
                        this.onScanError.bind(this)
                    );
                    
                    this.isScanning = true;
                    this.updateUIState();
                    this.checkTorchSupport();
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
                        this.updateUIState();
                        this.showToast('⏹️ Scanner stopped', 'info');
                    } catch (error) {
                        console.error('Error stopping scanner:', error);
                    }
                }
            }
            
            async switchCamera() {
                if (this.availableCameras.length <= 1) return;
                
                const wasScanning = this.isScanning;
                
                if (wasScanning) {
                    await this.stopScanning();
                }
                
                this.currentCamera = (this.currentCamera + 1) % this.availableCameras.length;
                
                if (wasScanning) {
                    setTimeout(() => this.startScanning(), 500);
                }
                
                this.showToast(`🔄 Switched to camera ${this.currentCamera + 1}`, 'info');
            }
            
            async toggleTorch() {
                if (!this.torchSupported) return;
                
                try {
                    this.torchOn = !this.torchOn;
                    const torchBtn = document.getElementById('torchBtn');
                    torchBtn.textContent = this.torchOn ? '🔅' : '🔦';
                    
                    // Note: Torch control varies by browser and device
                    this.showToast(this.torchOn ? '🔦 Flashlight ON' : '🔦 Flashlight OFF', 'info');
                } catch (error) {
                    console.error('Error toggling torch:', error);
                }
            }
            
            checkTorchSupport() {
                // Check if torch is supported (simplified check)
                const torchBtn = document.getElementById('torchBtn');
                if (navigator.mediaDevices && navigator.mediaDevices.getSupportedConstraints && 
                    navigator.mediaDevices.getSupportedConstraints().torch) {
                    this.torchSupported = true;
                    torchBtn.style.display = 'block';
                }
            }
            
            onScanSuccess(decodedText, decodedResult) {
                // Vibrate if supported
                if (navigator.vibrate) {
                    navigator.vibrate([100, 50, 100]);
                }
                
                this.processQRCode(decodedText);
            }
            
            onScanError(error) {
                // Silently handle scan errors (too verbose otherwise)
                if (error.includes('NotFoundException')) {
                    // No QR code found - this is normal
                    return;
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
                    this.showToast('❌ Network error. Please try again.', 'error');
                }
            }
            
            handleScanResult(result, qrCode) {
                if (result.success) {
                    this.updateStats('success');
                    this.showResult(result.data, 'success');
                    this.playSuccessSound();
                } else {
                    this.updateStats('errors');
                    this.showResult({
                        message: result.message || 'Unknown error',
                        qr_code: qrCode
                    }, 'error');
                    this.playErrorSound();
                }
            }
            
            showResult(data, type) {
                const popup = document.getElementById('resultPopup');
                const status = document.getElementById('resultStatus');
                const details = document.getElementById('participantDetails');
                
                // Set status
                const statusIcon = type === 'success' ? '✅' : '❌';
                const statusClass = type === 'success' ? 'status-success' : 'status-error';
                
                status.innerHTML = `
                    <span class="status-indicator ${statusClass}"></span>
                    <span>${statusIcon} ${type === 'success' ? 'Attendance Marked' : 'Scan Failed'}</span>
                `;
                
                // Set details
                if (type === 'success' && data.user_name) {
                    details.innerHTML = `
                        <div class="detail-item">
                            <div class="detail-label">Participant</div>
                            <div class="detail-value">${data.user_name}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Email</div>
                            <div class="detail-value">${data.user_email}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">${data.message}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Time</div>
                            <div class="detail-value">${new Date().toLocaleString()}</div>
                        </div>
                    `;
                } else {
                    details.innerHTML = `
                        <div class="detail-item">
                            <div class="detail-label">Error</div>
                            <div class="detail-value">${data.message}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">QR Code</div>
                            <div class="detail-value" style="font-family: monospace;">${data.qr_code || 'N/A'}</div>
                        </div>
                    `;
                }
                
                popup.classList.add('show');
                
                // Auto-close after 3 seconds for success, 5 seconds for errors
                setTimeout(() => {
                    this.closeResult();
                }, type === 'success' ? 3000 : 5000);
            }
            
            closeResult() {
                document.getElementById('resultPopup').classList.remove('show');
            }
            
            toggleManualInput() {
                const manualInput = document.getElementById('manualInput');
                const manualBtn = document.getElementById('manualBtn');
                
                if (manualInput.classList.contains('show')) {
                    manualInput.classList.remove('show');
                    manualBtn.textContent = '⌨️ Manual Entry';
                } else {
                    manualInput.classList.add('show');
                    manualBtn.textContent = '📷 Camera Mode';
                    document.getElementById('manualCode').focus();
                }
            }
            
            async processManualCode() {
                const input = document.getElementById('manualCode');
                const code = input.value.trim();
                
                if (!code) {
                    this.showToast('⚠️ Please enter a QR code', 'warning');
                    return;
                }
                
                input.value = '';
                this.toggleManualInput();
                
                await this.processQRCode(code);
            }
            
            updateStats(type) {
                this.stats[type]++;
                localStorage.setItem(`${type}Count`, this.stats[type].toString());
                this.updateStatsDisplay();
            }
            
            updateStatsDisplay() {
                document.getElementById('scanCount').textContent = this.stats.scanned;
                document.getElementById('successCount').textContent = this.stats.success;
                document.getElementById('errorCount').textContent = this.stats.errors;
            }
            
            updateUIState() {
                const startBtn = document.getElementById('startBtn');
                const stopBtn = document.getElementById('stopBtn');
                const switchBtn = document.getElementById('switchBtn');
                
                if (this.isScanning) {
                    startBtn.style.display = 'none';
                    stopBtn.style.display = 'block';
                    switchBtn.style.display = this.availableCameras.length > 1 ? 'block' : 'none';
                } else {
                    startBtn.style.display = 'block';
                    stopBtn.style.display = 'none';
                    switchBtn.style.display = 'none';
                }
            }
            
            showLoading(show) {
                document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
            }
            
            showToast(message, type = 'info') {
                // Create toast notification
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                toast.style.cssText = `
                    position: fixed;
                    top: 2rem;
                    left: 1rem;
                    right: 1rem;
                    background: var(--glass-bg);
                    backdrop-filter: blur(20px);
                    border: 1px solid var(--glass-border);
                    border-radius: var(--radius);
                    padding: 1rem;
                    color: var(--text-primary);
                    z-index: 10000;
                    transform: translateY(-100px);
                    opacity: 0;
                    transition: all 0.3s ease;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
                `;
                toast.textContent = message;
                
                document.body.appendChild(toast);
                
                requestAnimationFrame(() => {
                    toast.style.transform = 'translateY(0)';
                    toast.style.opacity = '1';
                });
                
                setTimeout(() => {
                    toast.style.transform = 'translateY(-100px)';
                    toast.style.opacity = '0';
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }, 3000);
            }
            
            playSuccessSound() {
                // Create success sound
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
                // Create error sound
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
        }
        
        // Global functions for backward compatibility
        let scanner;
        
        function changeEvent(eventId) {
            if (eventId) {
                window.location.href = `<?php echo BASE_URL; ?>/mobile-scanner.php?event_id=${eventId}`;
            } else {
                window.location.href = `<?php echo BASE_URL; ?>/mobile-scanner.php`;
            }
        }
        
        function startScanning() { scanner.startScanning(); }
        function stopScanning() { scanner.stopScanning(); }
        function switchCamera() { scanner.switchCamera(); }
        function toggleTorch() { scanner.toggleTorch(); }
        function toggleManualInput() { scanner.toggleManualInput(); }
        function processManualCode() { scanner.processManualCode(); }
        function closeResult() { scanner.closeResult(); }
        
        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            scanner = new MobileQRScanner();
        });
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, false);
        });
        
        async function loadCameras() {
            try {
                const devices = await Html5Qrcode.getCameras();
                availableCameras = devices;
                
                if (devices.length === 0) {
                    showToast('No cameras found', 'error');
                    return;
                }
                
                // Prefer back camera for mobile
                const backCamera = devices.find(device => 
                    device.label.toLowerCase().includes('back') || 
                    device.label.toLowerCase().includes('rear')
                );
                
                if (backCamera) {
                    currentCamera = devices.indexOf(backCamera);
                }
                
                console.log(`Found ${devices.length} cameras`);
                
            } catch (error) {
                console.error('Error getting cameras:', error);
                showToast('Camera access denied', 'error');
            }
        }
        
        async function startScanning() {
            if (isScanning || availableCameras.length === 0) return;
            
            try {
                const cameraId = availableCameras[currentCamera].id;
                
                const config = {
                    fps: 10,
                    qrbox: { width: 200, height: 200 },
                    aspectRatio: 1.0,
                    // Mobile optimizations
                    experimentalFeatures: {
                        useBarCodeDetectorIfSupported: true
                    }
                };
                
                await html5QrCode.start(
                    cameraId,
                    config,
                    onScanSuccess,
                    onScanFailure
                );
                
                isScanning = true;
                document.getElementById('startBtn').style.display = 'none';
                document.getElementById('stopBtn').style.display = 'block';
                
                // Check for torch support
                checkTorchSupport();
                
                showToast('Camera started', 'success');
                
            } catch (error) {
                console.error('Error starting camera:', error);
                showToast('Failed to start camera', 'error');
            }
        }
        
        async function stopScanning() {
            if (!isScanning) return;
            
            try {
                await html5QrCode.stop();
                isScanning = false;
                torchOn = false;
                
                document.getElementById('startBtn').style.display = 'block';
                document.getElementById('stopBtn').style.display = 'none';
                document.getElementById('torchBtn').style.display = 'none';
                
                showToast('Camera stopped', 'info');
                
            } catch (error) {
                console.error('Error stopping camera:', error);
            }
        }
        
        async function switchCamera() {
            if (availableCameras.length <= 1) {
                showToast('Only one camera available', 'info');
                return;
            }
            
            const wasScanning = isScanning;
            
            if (wasScanning) {
                await stopScanning();
            }
            
            currentCamera = (currentCamera + 1) % availableCameras.length;
            
            if (wasScanning) {
                setTimeout(startScanning, 500);
            }
            
            showToast('Switched camera', 'info');
        }
        
        function checkTorchSupport() {
            // This is a simplified check - actual torch support detection is complex
            if (isScanning) {
                document.getElementById('torchBtn').style.display = 'block';
                torchSupported = true;
            }
        }
        
        async function toggleTorch() {
            if (!torchSupported || !isScanning) return;
            
            try {
                // Torch control would require more advanced camera API
                torchOn = !torchOn;
                document.getElementById('torchBtn').textContent = torchOn ? '🔦' : '💡';
                showToast(torchOn ? 'Torch on' : 'Torch off', 'info');
            } catch (error) {
                console.error('Torch error:', error);
            }
        }
        
        function onScanSuccess(decodedText, decodedResult) {
            // Vibrate on successful scan
            if ('vibrate' in navigator) {
                navigator.vibrate(200);
            }
            
            stats.scanned++;
            updateStatsDisplay();
            
            processQRCode(decodedText);
        }
        
        function onScanFailure(error) {
            // Silent - don't log every frame failure
        }
        
        async function processQRCode(qrCode) {
            try {
                showToast('Processing...', 'info');
                
                const response = await fetch('/api/attendance.php?action=scan_qr', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ qr_code: qrCode })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    stats.success++;
                    addToHistory(result.data, true);
                    showResult(result.data, result.message, 'success');
                    
                    // Success vibration
                    if ('vibrate' in navigator) {
                        navigator.vibrate([100, 50, 100]);
                    }
                } else {
                    stats.errors++;
                    addToHistory({ error: result.error, qr_code: qrCode }, false);
                    showResult({ error: result.error }, 'Scan failed', 'error');
                    
                    // Error vibration
                    if ('vibrate' in navigator) {
                        navigator.vibrate([200, 100, 200, 100, 200]);
                    }
                }
                
            } catch (error) {
                stats.errors++;
                console.error('Process error:', error);
                showToast('Network error', 'error');
                
                if ('vibrate' in navigator) {
                    navigator.vibrate([300, 100, 300]);
                }
            }
            
            updateStatsDisplay();
            saveStats();
        }
        
        function showResult(data, message, type) {
            const popup = document.getElementById('resultPopup');
            const statusEl = document.getElementById('resultStatus');
            const detailsEl = document.getElementById('participantDetails');
            
            // Set status
            const statusIcon = type === 'success' ? '✅' : '❌';
            const statusClass = type === 'success' ? 'status-success' : 'status-error';
            
            statusEl.innerHTML = `
                <span class="status-indicator ${statusClass}"></span>
                ${statusIcon} ${message}
            `;
            
            // Set details
            if (type === 'success' && data.participant_name) {
                detailsEl.innerHTML = `
                    <div class="detail-item">
                        <div class="detail-label">Participant</div>
                        <div class="detail-value">${escapeHtml(data.participant_name)}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Event</div>
                        <div class="detail-value">${escapeHtml(data.event_name)}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Check-in Time</div>
                        <div class="detail-value">${new Date(data.checked_in_at).toLocaleString()}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">${data.was_duplicate ? 'Already Checked In' : 'Successfully Checked In'}</div>
                    </div>
                `;
            } else {
                detailsEl.innerHTML = `
                    <div class="detail-item">
                        <div class="detail-label">Error Details</div>
                        <div class="detail-value">${escapeHtml(data.error || 'Unknown error')}</div>
                    </div>
                `;
            }
            
            popup.classList.add('show');
            
            // Auto-close after 4 seconds for success, 6 for error
            setTimeout(closeResult, type === 'success' ? 4000 : 6000);
        }
        
        function closeResult() {
            document.getElementById('resultPopup').classList.remove('show');
        }
        
        function toggleManualInput() {
            const manualInput = document.getElementById('manualInput');
            const isVisible = manualInput.style.display !== 'none';
            manualInput.style.display = isVisible ? 'none' : 'block';
            
            if (!isVisible) {
                document.getElementById('manualCode').focus();
            }
        }
        
        function handleManualKeypress(event) {
            if (event.key === 'Enter') {
                processManualCode();
            }
        }
        
        function processManualCode() {
            const code = document.getElementById('manualCode').value.trim();
            if (!code) {
                showToast('Please enter a code', 'warning');
                return;
            }
            
            processQRCode(code);
            document.getElementById('manualCode').value = '';
        }
        
        function addToHistory(data, success) {
            const historyItem = {
                time: new Date().toLocaleTimeString(),
                success: success,
                name: success ? data.participant_name : 'Failed Scan',
                details: success ? data.event_name : data.error
            };
            
            stats.history.unshift(historyItem);
            if (stats.history.length > 10) {
                stats.history.pop();
            }
            
            updateHistoryDisplay();
        }
        
        function updateHistoryDisplay() {
            const historyList = document.getElementById('historyList');
            
            if (stats.history.length === 0) {
                historyList.innerHTML = '<div style="text-align: center; opacity: 0.6; padding: 1rem;">No scans yet</div>';
                return;
            }
            
            historyList.innerHTML = stats.history.map(item => `
                <div class="history-item">
                    <div>
                        <div style="font-weight: 600;">${item.success ? '✅' : '❌'} ${escapeHtml(item.name)}</div>
                        <div style="font-size: 0.75rem; opacity: 0.8;">${escapeHtml(item.details)}</div>
                    </div>
                    <div style="font-size: 0.75rem; opacity: 0.6;">${item.time}</div>
                </div>
            `).join('');
        }
        
        function clearHistory() {
            if (confirm('Clear scan history?')) {
                stats.history = [];
                updateHistoryDisplay();
                saveStats();
            }
        }
        
        function updateStatsDisplay() {
            document.getElementById('scanCount').textContent = stats.scanned;
            document.getElementById('successCount').textContent = stats.success;
            document.getElementById('errorCount').textContent = stats.errors;
        }
        
        function loadStats() {
            const saved = localStorage.getItem('mobileQRStats');
            if (saved) {
                stats = { ...stats, ...JSON.parse(saved) };
            }
            updateHistoryDisplay();
        }
        
        function saveStats() {
            localStorage.setItem('mobileQRStats', JSON.stringify(stats));
        }
        
        function showToast(message, type) {
            // Simple toast implementation
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: ${type === 'success' ? '#00ff88' : type === 'error' ? '#ff4757' : '#ffa502'};
                color: ${type === 'success' ? '#000' : '#fff'};
                padding: 1rem 2rem;
                border-radius: 25px;
                font-weight: 600;
                z-index: 2000;
                animation: slideIn 0.3s ease;
            `;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 2000);
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translate(-50%, -100%); opacity: 0; }
                to { transform: translate(-50%, 0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translate(-50%, 0); opacity: 1; }
                to { transform: translate(-50%, -100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        // Handle torch button
        document.getElementById('torchBtn').addEventListener('click', toggleTorch);
        
        // Save stats before page unload
        window.addEventListener('beforeunload', saveStats);
        
        // Handle back button
        window.addEventListener('popstate', function() {
            if (isScanning) {
                stopScanning();
            }
        });
    </script>
</body>
</html>