<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/middleware/Auth.php';

// Check if user came from email verification OR Google OAuth
$is_google_registration = false;
$user_id = null;
$email = '';
$pre_filled_name = '';
$google_id = null;
$pre_filled_roll_number = '';

if (isset($_SESSION['google_registration'])) {
    // Google OAuth registration
    $is_google_registration = true;
    $google_data = $_SESSION['google_registration'];
    
    // Verify data is not expired (10 minutes)
    if (!isset($google_data['timestamp']) || (time() - $google_data['timestamp']) > 600) {
        unset($_SESSION['google_registration']);
        $_SESSION['flash_error'] = 'Registration session expired. Please try again.';
        header('Location: ' . BASE_URL . '/register.php');
        exit;
    }
    
    $email = $google_data['email'];
    $pre_filled_name = $google_data['name'] ?? ''; // Auto-fill name from Google
    $google_id = $google_data['google_id'];
    $google_picture = $google_data['picture'] ?? null;
    
    // Extract student ID from email (part before @)
    $pre_filled_roll_number = strtoupper(explode('@', $email)[0]);
    
} elseif (isset($_SESSION['verify_user_id']) && isset($_SESSION['verify_email'])) {
    // Email verification registration
    $user_id = $_SESSION['verify_user_id'];
    $email = $_SESSION['verify_email'];
    
    // Extract student ID from email (part before @)
    $pre_filled_roll_number = strtoupper(explode('@', $email)[0]);
} else {
    // No valid registration session
    header('Location: ' . BASE_URL . '/register.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $full_name = Security::sanitize($_POST['full_name'] ?? '');
        $roll_number = $pre_filled_roll_number; // Use email-derived student ID
        $phone = Security::sanitize($_POST['phone'] ?? '');
        $has_backlog = isset($_POST['has_backlog']) && $_POST['has_backlog'] === 'yes';
        
        // Handle profile photo upload
        $profile_image = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['profile_photo']['type'];
            
            if (in_array($file_type, $allowed_types) && $_FILES['profile_photo']['size'] <= 5242880) { // 5MB limit
                $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $new_filename = 'profile_' . uniqid() . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    $profile_image = 'profiles/' . $new_filename;
                }
            }
        }
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Auto-detect department and year from roll number
        $department = '';
        $year = '';
        
        if (!empty($roll_number)) {
            // Extract department and year from roll number
            $roll_upper = strtoupper($roll_number);
            
            // Extract joining year (first 2 digits)
            $joining_year = '20' . substr($roll_upper, 0, 2);
            
            // Extract department code
            if (preg_match('/^\d{2}([A-Z]+)/', $roll_upper, $matches)) {
                $dept_code = $matches[1];
                
                // Map department codes to full names
                $dept_map = [
                    'CS' => 'CSPIT - Computer Science Engineering',
                    'DCS' => 'DEPSTAR - Computer Science Engineering',
                    'CE' => 'CSPIT - Computer Engineering',
                    'DCE' => 'DEPSTAR - Computer Engineering',
                    'IT' => 'CSPIT - Information Technology',
                    'DIT' => 'DEPSTAR - Information Technology',
                    'AIML' => 'CSPIT - AI & Machine Learning',
                    'DAIML' => 'DEPSTAR - AI & Machine Learning'
                ];
                
                $department = $dept_map[$dept_code] ?? 'Unknown Department';
                
                // Calculate current year if no backlog
                if (!$has_backlog) {
                    $current_year = date('Y');
                    $current_month = (int)date('n');
                    $years_since_joining = $current_year - intval($joining_year);
                    // Academic year starts in July
                    if ($current_month >= 7) {
                        $year = min($years_since_joining + 1, 4);
                    } else {
                        $year = min($years_since_joining, 4);
                    }
                } else {
                    // Use manually selected year when backlog exists
                    $year = Security::sanitize($_POST['year'] ?? '');
                }
            }
        }
        
        // Validation
        $validator = new Validator();
        
        $validator->required('full_name', $full_name, 'Full Name');
        $validator->required('phone', $phone, 'Phone');
        $validator->phone('phone', $phone);
        
        // Validate password for Google users (mandatory)
        if ($is_google_registration) {
            $validator->required('password', $password, 'Password');
            $validator->strongPassword('password', $password);
            if ($password !== $confirm_password) {
                $validator->addError('confirm_password', 'Passwords do not match');
            }
        }
        
        if ($has_backlog && empty($year)) {
            $validator->addError('year', 'Please select your current year');
        }
        
        if (empty($department)) {
            $validator->addError('roll_number', 'Invalid roll number format. Please use format like 24CS001');
        }
        
        // Check roll number uniqueness
        if (!empty($roll_number)) {
            $validator->unique('roll_number', $roll_number, 'users', 'roll_number');
        }
        
        if ($validator->passes()) {
            try {
                $db = Database::getInstance()->getConnection();
                
                // For Google OAuth users, create new account
                if ($is_google_registration) {
                    // Create new user account with password
                    $password_hash = Security::hashPassword($password);
                    
                    $sql = "INSERT INTO users (email, full_name, roll_number, department, year, phone, google_id, password_hash, profile_image, email_verified, email_verified_at, created_at) 
                            VALUES (:email, :full_name, :roll_number, :department, :year, :phone, :google_id, :password_hash, :profile_image, 1, NOW(), NOW())";
                    
                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute([
                        'email' => $email,
                        'full_name' => $full_name,
                        'roll_number' => strtoupper($roll_number),
                        'department' => $department,
                        'year' => $year,
                        'phone' => $phone,
                        'google_id' => $google_id,
                        'password_hash' => $password_hash,
                        'profile_image' => $profile_image
                    ]);
                    
                    if ($result) {
                        $user_id = $db->lastInsertId();
                        
                        // Assign default student role
                        $role_sql = "INSERT INTO user_roles (user_id, role_id) 
                                     SELECT :user_id, id FROM roles WHERE role_name = 'student'";
                        $role_stmt = $db->prepare($role_sql);
                        $role_stmt->execute(['user_id' => $user_id]);
                        
                        // Log registration completion
                        Security::logAudit($user_id, 'google_registration_completed', 'users', $user_id);
                        
                        // Clear Google registration session
                        unset($_SESSION['google_registration']);
                        
                        // Log the user in automatically
                        Auth::login($user_id);
                        
                        $success = 'Registration completed successfully! Redirecting to dashboard...';
                        
                        // Redirect to dashboard after 2 seconds
                        header("refresh:2;url=" . BASE_URL . "/student/dashboard.php");
                    }
                } else {
                    // For email verification users, update existing account
                    $sql = "UPDATE users 
                            SET full_name = :full_name, 
                                roll_number = :roll_number, 
                                department = :department, 
                                year = :year, 
                                phone = :phone,
                                profile_image = :profile_image,
                                email_verified = 1,
                                email_verified_at = NOW(),
                                verification_token = NULL,
                                verification_code = NULL,
                                token_expiry = NULL,
                                updated_at = NOW()
                            WHERE id = :user_id";
                    
                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute([
                        'full_name' => $full_name,
                        'roll_number' => strtoupper($roll_number),
                        'department' => $department,
                        'year' => $year,
                        'phone' => $phone,
                        'profile_image' => $profile_image,
                        'user_id' => $user_id
                    ]);
                    
                    if ($result) {
                        // Assign default student role
                        $role_sql = "INSERT INTO user_roles (user_id, role_id) 
                                     SELECT :user_id, id FROM roles WHERE role_name = 'student'
                                     ON DUPLICATE KEY UPDATE user_id = user_id";
                        $role_stmt = $db->prepare($role_sql);
                        $role_stmt->execute(['user_id' => $user_id]);
                        
                        // Log registration completion
                        Security::logAudit($user_id, 'registration_completed', 'users', $user_id);
                        
                        // Clear session variables
                        unset($_SESSION['verify_user_id']);
                        unset($_SESSION['verify_email']);
                        
                        $success = 'Registration completed successfully! Redirecting to login...';
                        
                        // Redirect to login after 2 seconds
                        header("refresh:2;url=" . BASE_URL . "/login.php");
                    }
                }
            } catch (PDOException $e) {
                error_log("Profile completion error: " . $e->getMessage());
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $error = 'Roll number already exists. Please use a different one.';
                } else {
                    $error = 'Failed to complete profile. Please try again.';
                }
            }
        } else {
            $error = $validator->getFirstError();
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/auth.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="auth-container">
        <div class="auth-glass-card">
            <div class="auth-header">
                <h1 class="auth-title"><?php echo $is_google_registration ? '🔐 Google Account Connected!' : '✅ Email Verified!'; ?></h1>
                <p class="auth-subtitle"><?php echo $is_google_registration ? 'Complete your profile to finish registration' : 'Step 2: Complete your profile to finish registration'; ?></p>
            </div>
            
            <div style="background: rgba(17, 153, 142, 0.1); border-left: 4px solid #11998e; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <p style="margin: 0; color: rgba(255, 255, 255, 0.9); font-size: 14px;">
                    📧 <strong>Email:</strong> <?php echo htmlspecialchars($email); ?>
                </p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" required 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? $pre_filled_name); ?>" 
                           placeholder="John Doe" autofocus>
                    <?php if ($is_google_registration && $pre_filled_name): ?>
                        <small class="form-hint" style="color: rgba(76, 175, 80, 0.9);">✓ Auto-filled from your Google account</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="profile_photo">Profile Photo (Optional)</label>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/jpg,image/png,image/gif"
                           style="padding: 8px; background: rgba(255, 255, 255, 0.05); border-radius: 8px;">
                    <small class="form-hint">Upload a profile photo (max 5MB, JPG/PNG/GIF)</small>
                </div>
                
                <div class="form-group">
                    <label for="roll_number">Student ID *</label>
                    <input type="text" id="roll_number" name="roll_number" 
                           value="<?php echo htmlspecialchars($pre_filled_roll_number); ?>"
                           placeholder="e.g., 24CS001"
                           disabled
                           style="background: rgba(255, 255, 255, 0.05); cursor: not-allowed;"
                           oninput="updateDepartmentAndYear()">
                    <small class="form-hint" style="color: rgba(255, 193, 7, 0.9);">Auto-filled from your email address</small>
                </div>
                
                <div class="form-group">
                    <label>Department (Auto-detected) *</label>
                    <input type="text" id="department_display" readonly 
                           value="" 
                           style="background: rgba(255, 255, 255, 0.05); cursor: not-allowed;"
                           placeholder="Enter Student ID to auto-detect">
                </div>
                
                <div class="form-group">
                    <label>Any backlogs? *</label>
                    <div style="display: flex; gap: 20px; margin-top: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="has_backlog" value="no" checked onchange="toggleYearSelection()" style="cursor: pointer;">
                            <span>No</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="has_backlog" value="yes" onchange="toggleYearSelection()" style="cursor: pointer;">
                            <span>Yes</span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="year" id="year_label">Year (Auto-detected) *</label>
                    <select id="year" name="year" disabled style="background: rgba(255, 255, 255, 0.05); cursor: not-allowed;">
                        <option value="">Auto-detected from Student ID</option>
                        <option value="1">First Year</option>
                        <option value="2">Second Year</option>
                        <option value="3">Third Year</option>
                        <option value="4">Fourth Year</option>
                    </select>
                    <small class="form-hint" id="year_hint" style="display: none; color: rgba(255, 193, 7, 0.9);">Select your current year (only available years shown)</small>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" required 
                           value="<?php echo $_POST['phone'] ?? ''; ?>" 
                           placeholder="9876543210" maxlength="10">
                </div>
                
                <?php if ($is_google_registration): ?>
                    <div style="background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; padding: 16px; border-radius: 8px; margin: 20px 0;">
                        <p style="margin: 0 0 12px 0; color: rgba(255, 255, 255, 0.9); font-size: 14px; font-weight: 600;">
                            🔑 Set Your Password
                        </p>
                        <p style="margin: 0 0 16px 0; color: rgba(255, 255, 255, 0.7); font-size: 13px;">
                            Create a password to login with email/password in addition to Google Sign-In on any device.
                        </p>
                        
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label for="password">Password *</label>
                            <div class="password-wrapper">
                                <input type="password" id="password" name="password" required
                                       placeholder="Create a strong password"
                                       class="password-input">
                                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                    <svg class="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                            <small class="form-hint">Min 8 characters with uppercase, lowercase, number & special character</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" required
                                       placeholder="Re-enter your password"
                                       class="password-input">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <svg class="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary btn-full">
                    <span>Complete Registration</span>
                    <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </button>
            </form>
        </div>
        
        <div class="auth-bg-blur"></div>
    </div>
    
    <script src="<?php echo BASE_URL; ?>/public/js/auth.js"></script>
    <script>
        let autoDetectedYear = 0;
        
        function updateDepartmentAndYear() {
            const rollNumber = '<?php echo $pre_filled_roll_number; ?>';
            const deptDisplay = document.getElementById('department_display');
            const yearSelect = document.getElementById('year');
            
            if (rollNumber.length < 4) {
                deptDisplay.value = '';
                autoDetectedYear = 0;
                return;
            }
            
            // Extract joining year and department code
            const joiningYear = '20' + rollNumber.substring(0, 2);
            const deptCode = rollNumber.match(/^\d{2}([A-Z]+)/)?.[1];
            
            // Department mapping
            const deptMap = {
                'CS': 'CSPIT - Computer Science Engineering',
                'DCS': 'DEPSTAR - Computer Science Engineering',
                'CE': 'CSPIT - Computer Engineering',
                'DCE': 'DEPSTAR - Computer Engineering',
                'IT': 'CSPIT - Information Technology',
                'DIT': 'DEPSTAR - Information Technology',
                'AIML': 'CSPIT - AI & Machine Learning',
            };
            
            if (deptCode && deptMap[deptCode]) {
                deptDisplay.value = deptMap[deptCode];
                
                // Calculate current year
                const currentDate = new Date();
                const currentYear = currentDate.getFullYear();
                const currentMonth = currentDate.getMonth() + 1; // JavaScript months are 0-indexed
                const yearsSinceJoining = currentYear - parseInt(joiningYear);
                // Academic year starts in July
                if (currentMonth >= 7) {
                    autoDetectedYear = Math.min(yearsSinceJoining + 1, 4);
                } else {
                    autoDetectedYear = Math.min(yearsSinceJoining, 4);
                }
                
                // Update year dropdown based on backlog status
                toggleYearSelection();
            } else {
                deptDisplay.value = 'Invalid department code';
                autoDetectedYear = 0;
            }
        }
        
        function toggleYearSelection() {
            const hasBacklog = document.querySelector('input[name="has_backlog"]:checked')?.value === 'yes';
            const yearSelect = document.getElementById('year');
            const yearHint = document.getElementById('year_hint');
            const yearLabel = document.getElementById('year_label');
            
            // Clear all options
            yearSelect.innerHTML = '';
            
            if (hasBacklog && autoDetectedYear > 0) {
                // Enable manual selection with years up to auto-detected year
                yearSelect.disabled = false;
                yearSelect.required = true;
                yearSelect.style.background = '';
                yearSelect.style.cursor = 'pointer';
                yearHint.style.display = 'block';
                yearLabel.textContent = 'Year *';
                
                const yearNames = ['', 'First Year', 'Second Year', 'Third Year', 'Fourth Year'];
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Select Year';
                yearSelect.appendChild(option);
                
                // Add only years from 1 to auto-detected year
                for (let i = 1; i <= autoDetectedYear; i++) {
                    const opt = document.createElement('option');
                    opt.value = i;
                    opt.textContent = yearNames[i];
                    yearSelect.appendChild(opt);
                }
            } else {
                // Auto-detected year (disabled)
                yearSelect.disabled = true;
                yearSelect.required = false;
                yearSelect.style.background = 'rgba(255, 255, 255, 0.05)';
                yearSelect.style.cursor = 'not-allowed';
                yearHint.style.display = 'none';
                yearLabel.textContent = 'Year (Auto-detected) *';
                
                if (autoDetectedYear > 0) {
                    const yearNames = ['', 'First Year', 'Second Year', 'Third Year', 'Fourth Year'];
                    const option = document.createElement('option');
                    option.value = autoDetectedYear;
                    option.textContent = yearNames[autoDetectedYear];
                    option.selected = true;
                    yearSelect.appendChild(option);
                } else {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'Auto-detected from Student ID';
                    yearSelect.appendChild(option);
                }
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateDepartmentAndYear();
        });
    </script>
</body>
</html>
