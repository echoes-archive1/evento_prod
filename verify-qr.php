<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/middleware/Auth.php';

// This page is for event organizers to verify QR codes
Auth::requireAuth();

$verification_result = null;
$qr_code = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qr_code = trim($_POST['qr_code'] ?? '');
    
    if (!empty($qr_code)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Find registration by QR code
            $sql = "SELECT er.*, e.event_name, e.event_date, e.venue, u.full_name, u.email, u.roll_number 
                    FROM event_registrations er 
                    JOIN events e ON er.event_id = e.id 
                    JOIN users u ON er.user_id = u.id 
                    WHERE er.qr_code = :qr_code";
            
            $stmt = $db->prepare($sql);
            $stmt->execute(['qr_code' => $qr_code]);
            $registration = $stmt->fetch();
            
            if ($registration) {
                // Update attendance if not already marked
                if ($registration['attendance_status'] !== 'attended') {
                    $update_sql = "UPDATE event_registrations SET attendance_status = 'attended', checked_in_at = NOW() WHERE qr_code = :qr_code";
                    $update_stmt = $db->prepare($update_sql);
                    $update_stmt->execute(['qr_code' => $qr_code]);
                }
                
                $verification_result = $registration;
            } else {
                $error = 'Invalid QR code or registration not found.';
            }
            
        } catch (Exception $e) {
            $error = 'Verification failed. Please try again.';
            error_log("QR verification error: " . $e->getMessage());
        }
    } else {
        $error = 'Please enter a QR code.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Verification - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
</head>
<body>
    <div class="container">
        <div class="verification-container">
            <div class="header">
                <h1>🎫 QR Code Verification</h1>
                <p>Scan or enter the QR code to verify event attendance</p>
            </div>
            
            <form method="POST" class="verification-form">
                <div class="form-group">
                    <label for="qr_code">QR Code / Registration Code:</label>
                    <input type="text" id="qr_code" name="qr_code" value="<?php echo htmlspecialchars($qr_code); ?>" 
                           placeholder="Enter QR code or registration ID" required autofocus>
                </div>
                
                <button type="submit" class="btn btn-primary">Verify Registration</button>
            </form>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>❌ Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($verification_result): ?>
                <div class="verification-result">
                    <div class="alert alert-success">
                        <strong>✅ Valid Registration</strong>
                        <?php if ($verification_result['attendance_status'] === 'attended'): ?>
                            <span class="status-badge attended">Already Checked In</span>
                        <?php else: ?>
                            <span class="status-badge new">Just Checked In</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="participant-info">
                        <h3>Participant Details</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Name:</label>
                                <span><?php echo htmlspecialchars($verification_result['full_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Email:</label>
                                <span><?php echo htmlspecialchars($verification_result['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Roll Number:</label>
                                <span><?php echo htmlspecialchars($verification_result['roll_number']); ?></span>
                            </div>
                        </div>
                        
                        <h3>Event Details</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Event:</label>
                                <span><?php echo htmlspecialchars($verification_result['event_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Date:</label>
                                <span><?php echo date('F j, Y g:i A', strtotime($verification_result['event_date'])); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Venue:</label>
                                <span><?php echo htmlspecialchars($verification_result['venue']); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($verification_result['checked_in_at']): ?>
                            <div class="check-in-time">
                                <label>Check-in Time:</label>
                                <span><?php echo date('F j, Y g:i A', strtotime($verification_result['checked_in_at'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="help-section">
                <h4>How to use:</h4>
                <ul>
                    <li>Use a QR code scanner app to scan participant QR codes</li>
                    <li>Or manually enter the registration code from their email</li>
                    <li>The system will automatically mark attendance</li>
                </ul>
            </div>
        </div>
    </div>

    <style>
        .verification-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .verification-form {
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            text-align: center;
            font-family: monospace;
            letter-spacing: 1px;
        }
        
        .verification-result {
            margin-top: 2rem;
        }
        
        .participant-info {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-item label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }
        
        .info-item span {
            font-weight: 500;
            color: #111827;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 1rem;
        }
        
        .status-badge.attended {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.new {
            background: #d1fae5;
            color: #065f46;
        }
        
        .check-in-time {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .help-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #e5e7eb;
        }
        
        .help-section h4 {
            margin-bottom: 0.5rem;
            color: #374151;
        }
        
        .help-section ul {
            margin: 0;
            padding-left: 1.5rem;
            color: #6b7280;
        }
        
        .help-section li {
            margin-bottom: 0.25rem;
        }
    </style>
</body>
</html>