<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('club_leader');

$user = Auth::user();
$db = Database::getInstance()->getConnection();

// Get user's club
$club_sql = "SELECT c.* FROM clubs c WHERE c.leader_id = :user_id AND c.is_active = 1 LIMIT 1";
$club_stmt = $db->prepare($club_sql);
$club_stmt->execute(['user_id' => Auth::id()]);
$my_club = $club_stmt->fetch();

if (!$my_club) {
    header('Location: dashboard.php');
    exit;
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $club_name = Security::sanitize($_POST['club_name'] ?? '');
        $club_description = Security::sanitize($_POST['club_description'] ?? '');
        
        // Validation
        $validator = new Validator();
        $validator->required('club_name', $club_name, 'Club Name');
        $validator->required('club_description', $club_description, 'Club Description');
        $validator->minLength('club_name', $club_name, 3, 'Club Name');
        $validator->minLength('club_description', $club_description, 10, 'Club Description');
        
        // Handle logo upload
        $logo_filename = $my_club['club_logo'];
        if (isset($_FILES['club_logo']) && $_FILES['club_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_result = Security::uploadFile(
                $_FILES['club_logo'],
                UPLOAD_PATH . 'clubs/',
                ALLOWED_IMAGE_TYPES
            );
            
            if ($upload_result['success']) {
                // Delete old logo if exists
                if ($my_club['club_logo'] && file_exists(UPLOAD_PATH . 'clubs/' . $my_club['club_logo'])) {
                    unlink(UPLOAD_PATH . 'clubs/' . $my_club['club_logo']);
                }
                $logo_filename = $upload_result['filename'];
            } else {
                $validator->addError('club_logo', $upload_result['message']);
            }
        }
        
        if ($validator->passes()) {
            try {
                $sql = "UPDATE clubs SET 
                        club_name = :club_name,
                        club_description = :club_description,
                        club_logo = :club_logo,
                        updated_at = NOW()
                        WHERE id = :club_id AND leader_id = :leader_id";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    'club_name' => $club_name,
                    'club_description' => $club_description,
                    'club_logo' => $logo_filename,
                    'club_id' => $my_club['id'],
                    'leader_id' => Auth::id()
                ]);
                
                if ($result) {
                    Security::logAudit(Auth::id(), 'club_update', 'clubs', $my_club['id']);
                    $success = 'Club updated successfully!';
                    
                    // Refresh club data
                    $club_stmt->execute(['user_id' => Auth::id()]);
                    $my_club = $club_stmt->fetch();
                    
                    header("refresh:2;url=dashboard.php");
                }
            } catch (Exception $e) {
                error_log("Club update error: " . $e->getMessage());
                $error = 'Failed to update club. Please try again.';
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
    <title>Edit Club - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px) saturate(180%);
            border-radius: var(--radius);
            padding: 40px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px var(--glass-shadow);
        }
        
        .form-header {
            margin-bottom: 30px;
        }
        
        .form-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-primary);
        }
        
        .form-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .form-label .required {
            color: var(--error);
        }
        
        .form-input,
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }
        
        .form-file-input {
            display: none;
        }
        
        .file-upload-area {
            border: 2px dashed var(--glass-border);
            border-radius: var(--radius-sm);
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.02);
        }
        
        .file-upload-area:hover {
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.05);
        }
        
        .file-upload-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 10px;
            color: var(--text-secondary);
        }
        
        .file-upload-text {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .file-upload-hint {
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 5px;
        }
        
        .current-logo {
            margin-bottom: 15px;
            text-align: center;
        }
        
        .current-logo img {
            max-width: 150px;
            height: auto;
            border-radius: var(--radius-sm);
            border: 2px solid var(--glass-border);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(99, 102, 241, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
            border: 1px solid var(--glass-border);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="public-navbar">
        <div class="navbar-brand">🎓 Evento</div>
        <div class="navbar-actions" style="display: flex; gap: clamp(8px, 1.5vw, 16px); align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.75rem; color: white;">
                <span style="color: rgba(255, 255, 255, 0.9); font-size: clamp(0.85rem, 1.5vw, 1rem); font-weight: 500;"><?php $currentUser = Auth::user(); echo htmlspecialchars($currentUser['full_name']); ?></span>
                <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #60a5fa, #a78bfa); display: flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 700; color: white;">
                    <?php 
                    $nameParts = explode(' ', $currentUser['full_name']);
                    $initials = strtoupper(substr($nameParts[0], 0, 1));
                    if (count($nameParts) > 1) {
                        $initials .= strtoupper(substr(end($nameParts), 0, 1));
                    }
                    echo $initials;
                    ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>Dashboard</span>
            </a>
            
            <a href="club-events.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>Club Events</span>
            </a>
            
            <a href="create-event.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Create Event</span>
            </a>
            
            <a href="members.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>Members</span>
            </a>
            
            <a href="profile.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span>Profile</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <a href="<?php echo BASE_URL; ?>/logout.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="form-container">
            <div class="form-card">
                <div class="form-header">
                    <h1 class="form-title">Edit Club Information</h1>
                    <p class="form-subtitle">Update your club's details and logo</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 20px;">
                        <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;">
                        <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-group">
                        <label class="form-label">Club Name <span class="required">*</span></label>
                        <input type="text" name="club_name" class="form-input" 
                               value="<?php echo htmlspecialchars($my_club['club_name']); ?>" 
                               required placeholder="Enter club name">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Club Description <span class="required">*</span></label>
                        <textarea name="club_description" class="form-textarea" 
                                  required placeholder="Describe your club's purpose and activities"><?php echo htmlspecialchars($my_club['club_description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Club Logo</label>
                        
                        <?php if (!empty($my_club['club_logo'])): ?>
                            <div class="current-logo">
                                <p style="color: var(--text-muted); font-size: 12px; margin-bottom: 10px;">Current Logo:</p>
                                <img src="<?php echo BASE_URL; ?>/public/uploads/clubs/<?php echo htmlspecialchars($my_club['club_logo']); ?>" alt="Current Logo">
                            </div>
                        <?php endif; ?>
                        
                        <input type="file" name="club_logo" id="club_logo" class="form-file-input" accept="image/*">
                        <label for="club_logo" class="file-upload-area">
                            <svg class="file-upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <div class="file-upload-text">Click to upload new logo</div>
                            <div class="file-upload-hint">PNG, JPG, WEBP up to 5MB</div>
                        </label>
                    </div>

                    <div class="form-actions">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js"></script>
    <script>
        // File upload preview
        document.getElementById('club_logo').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                const label = document.querySelector('.file-upload-text');
                label.textContent = 'Selected: ' + fileName;
            }
        });
    </script>
</body>
</html>
