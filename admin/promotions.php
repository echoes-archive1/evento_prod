<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

// Check if user is admin
if (!Auth::check() || !Auth::hasRole('admin')) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$current_user = Auth::user();
$success = '';
$error = '';

$db = Database::getInstance()->getConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        
        if ($action === 'update_settings') {
            // Update promotion settings
            try {
                $promotion_type = $_POST['promotion_type'] ?? 'automatic';
                $january_enabled = isset($_POST['january_promotion_enabled']) ? 1 : 0;
                $april_enabled = isset($_POST['april_promotion_enabled']) ? 1 : 0;
                $january_date = $_POST['january_promotion_date'] ?? '';
                $april_date = $_POST['april_promotion_date'] ?? '';
                $auto_active = isset($_POST['auto_promotion_active']) ? 1 : 0;
                
                $sql = "UPDATE semester_promotion_settings SET 
                        promotion_type = :promotion_type,
                        january_promotion_enabled = :january_enabled,
                        april_promotion_enabled = :april_enabled,
                        january_promotion_date = :january_date,
                        april_promotion_date = :april_date,
                        auto_promotion_active = :auto_active,
                        updated_at = NOW()";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    'promotion_type' => $promotion_type,
                    'january_enabled' => $january_enabled,
                    'april_enabled' => $april_enabled,
                    'january_date' => $january_date,
                    'april_date' => $april_date,
                    'auto_active' => $auto_active
                ]);
                
                if ($result) {
                    $success = 'Promotion settings updated successfully!';
                    Security::logAudit($current_user['id'], 'promotion_settings_updated', 'semester_promotion_settings', null);
                } else {
                    $error = 'Failed to update settings.';
                }
                
            } catch (Exception $e) {
                error_log("Settings update error: " . $e->getMessage());
                $error = 'Error updating settings.';
            }
            
        } elseif ($action === 'bulk_promote') {
            // Bulk promote students
            $year_filter = $_POST['year_filter'] ?? 'all';
            $department_filter = $_POST['department_filter'] ?? 'all';
            
            try {
                $user_ids = null;
                
                // Build filter for specific students if needed
                if ($year_filter !== 'all' || $department_filter !== 'all') {
                    $filter_sql = "SELECT id FROM users WHERE year IN ('1','2','3','4') AND profile_completed = 1";
                    $params = [];
                    
                    if ($year_filter !== 'all') {
                        $filter_sql .= " AND year = :year";
                        $params['year'] = $year_filter;
                    }
                    
                    if ($department_filter !== 'all') {
                        $filter_sql .= " AND department = :department";
                        $params['department'] = $department_filter;
                    }
                    
                    $filter_stmt = $db->prepare($filter_sql);
                    $filter_stmt->execute($params);
                    $user_ids = array_column($filter_stmt->fetchAll(), 'id');
                }
                
                $result = StudentRegistrationHelper::promoteStudents($user_ids);
                
                if ($result['success']) {
                    $success = "Successfully promoted {$result['promoted_count']} students.";
                    if (!empty($result['errors'])) {
                        $success .= ' Errors: ' . implode(', ', array_slice($result['errors'], 0, 3));
                    }
                    Security::logAudit($current_user['id'], 'bulk_promotion', 'users', null, [
                        'promoted_count' => $result['promoted_count'],
                        'year_filter' => $year_filter,
                        'department_filter' => $department_filter
                    ]);
                } else {
                    $error = 'Bulk promotion failed: ' . $result['error'];
                }
                
            } catch (Exception $e) {
                error_log("Bulk promotion error: " . $e->getMessage());
                $error = 'Error during bulk promotion.';
            }
            
        } elseif ($action === 'individual_promote') {
            // Individual student promotion/demotion
            $user_id = (int)$_POST['user_id'];
            $new_year = (int)$_POST['new_year'];
            $new_semester = (int)$_POST['new_semester'];
            $notes = $_POST['notes'] ?? '';
            
            try {
                // Get current student info
                $student_sql = "SELECT year, current_semester, full_name FROM users WHERE id = :user_id";
                $student_stmt = $db->prepare($student_sql);
                $student_stmt->execute(['user_id' => $user_id]);
                $student = $student_stmt->fetch();
                
                if ($student) {
                    $current_year = (int)$student['year'];
                    $current_semester = (int)$student['current_semester'];
                    
                    // Update student
                    $update_sql = "UPDATE users SET year = :new_year, current_semester = :new_semester, 
                                   last_promotion_date = NOW(), updated_at = NOW() WHERE id = :user_id";
                    $update_stmt = $db->prepare($update_sql);
                    $update_result = $update_stmt->execute([
                        'new_year' => $new_year,
                        'new_semester' => $new_semester,
                        'user_id' => $user_id
                    ]);
                    
                    if ($update_result) {
                        // Log promotion history
                        $history_sql = "INSERT INTO promotion_history 
                                       (user_id, from_year, to_year, from_semester, to_semester, 
                                        promotion_type, promoted_by, notes)
                                       VALUES (:user_id, :from_year, :to_year, :from_semester, :to_semester, 
                                               'manual', :promoted_by, :notes)";
                        
                        $history_stmt = $db->prepare($history_sql);
                        $history_stmt->execute([
                            'user_id' => $user_id,
                            'from_year' => $current_year,
                            'to_year' => $new_year,
                            'from_semester' => $current_semester,
                            'to_semester' => $new_semester,
                            'promoted_by' => $current_user['id'],
                            'notes' => $notes
                        ]);
                        
                        $success = "Successfully updated {$student['full_name']} from Year {$current_year} Sem {$current_semester} to Year {$new_year} Sem {$new_semester}.";
                        
                        Security::logAudit($current_user['id'], 'individual_promotion', 'users', $user_id, [
                            'from' => "Y{$current_year}S{$current_semester}",
                            'to' => "Y{$new_year}S{$new_semester}",
                            'notes' => $notes
                        ]);
                    } else {
                        $error = 'Failed to update student.';
                    }
                } else {
                    $error = 'Student not found.';
                }
                
            } catch (Exception $e) {
                error_log("Individual promotion error: " . $e->getMessage());
                $error = 'Error updating student.';
            }
        }
    }
}

// Get current settings
$settings_sql = "SELECT * FROM semester_promotion_settings ORDER BY id DESC LIMIT 1";
$settings_stmt = $db->query($settings_sql);
$settings = $settings_stmt->fetch() ?: [];

// Get student statistics
$stats_sql = "SELECT 
    year,
    current_semester,
    department,
    COUNT(*) as count
FROM users 
WHERE year IN ('1','2','3','4') AND profile_completed = 1
GROUP BY year, current_semester, department
ORDER BY year, current_semester, department";

$stats_stmt = $db->query($stats_sql);
$student_stats = $stats_stmt->fetchAll(PDO::FETCH_GROUP);

// Get recent promotion history
$history_sql = "SELECT 
    ph.*,
    u.full_name,
    u.roll_number,
    admin.full_name as promoted_by_name
FROM promotion_history ph
JOIN users u ON ph.user_id = u.id
LEFT JOIN users admin ON ph.promoted_by = admin.id
ORDER BY ph.promotion_date DESC
LIMIT 20";

$history_stmt = $db->query($history_sql);
$recent_promotions = $history_stmt->fetchAll();

// Get all students for individual editing
$students_sql = "SELECT id, full_name, roll_number, year, current_semester, department, intake_year
FROM users 
WHERE year IN ('1','2','3','4') AND profile_completed = 1
ORDER BY year, roll_number";

$students_stmt = $db->query($students_sql);
$all_students = $students_stmt->fetchAll();

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Promotions - Admin</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/admin.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #11998e;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .promotion-form {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .history-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1>🎓 Student Promotions</h1>
                <p>Manage semester promotions and academic progression</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Student Statistics -->
            <div class="admin-card">
                <h2>📊 Student Distribution</h2>
                <div class="stats-grid">
                    <?php
                    $total_students = 0;
                    $year_totals = [];
                    
                    foreach ($all_students as $student) {
                        $year = $student['year'];
                        $year_totals[$year] = ($year_totals[$year] ?? 0) + 1;
                        $total_students++;
                    }
                    ?>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_students; ?></div>
                        <div>Total Students</div>
                    </div>
                    
                    <?php foreach ($year_totals as $year => $count): ?>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $count; ?></div>
                            <div>Year <?php echo $year; ?> Students</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Promotion Settings -->
            <div class="admin-card">
                <h2>⚙️ Promotion Settings</h2>
                
                <form method="POST" class="promotion-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="promotion_type">Promotion Type</label>
                            <select id="promotion_type" name="promotion_type" class="form-control">
                                <option value="automatic" <?php echo ($settings['promotion_type'] ?? '') == 'automatic' ? 'selected' : ''; ?>>Automatic</option>
                                <option value="manual" <?php echo ($settings['promotion_type'] ?? '') == 'manual' ? 'selected' : ''; ?>>Manual Only</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="auto_promotion_active" value="1" 
                                       <?php echo ($settings['auto_promotion_active'] ?? 0) ? 'checked' : ''; ?>>
                                Enable Auto Promotion System
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="january_promotion_enabled" value="1" 
                                       <?php echo ($settings['january_promotion_enabled'] ?? 0) ? 'checked' : ''; ?>>
                                Enable January Promotion
                            </label>
                            <input type="date" name="january_promotion_date" class="form-control" 
                                   value="<?php echo $settings['january_promotion_date'] ?? '2026-01-15'; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="april_promotion_enabled" value="1" 
                                       <?php echo ($settings['april_promotion_enabled'] ?? 0) ? 'checked' : ''; ?>>
                                Enable April Promotion
                            </label>
                            <input type="date" name="april_promotion_date" class="form-control" 
                                   value="<?php echo $settings['april_promotion_date'] ?? '2026-04-15'; ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Settings</button>
                </form>
            </div>
            
            <!-- Bulk Promotion -->
            <div class="admin-card">
                <h2>📈 Bulk Promotion</h2>
                
                <form method="POST" class="promotion-form" 
                      onsubmit="return confirm('Are you sure you want to promote these students? This action cannot be easily undone.')">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="bulk_promote">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="year_filter">Filter by Year</label>
                            <select id="year_filter" name="year_filter" class="form-control">
                                <option value="all">All Years</option>
                                <option value="1">First Year</option>
                                <option value="2">Second Year</option>
                                <option value="3">Third Year</option>
                                <option value="4">Fourth Year</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="department_filter">Filter by Department</label>
                            <select id="department_filter" name="department_filter" class="form-control">
                                <option value="all">All Departments</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Electronics and Communication">Electronics & Communication</option>
                                <option value="Mechanical Engineering">Mechanical Engineering</option>
                                <option value="Civil Engineering">Civil Engineering</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">Promote Selected Students</button>
                </form>
            </div>
            
            <!-- Individual Student Management -->
            <div class="admin-card">
                <h2>👤 Individual Student Management</h2>
                
                <div style="overflow-x: auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Roll Number</th>
                                <th>Department</th>
                                <th>Current</th>
                                <th>Intake Year</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                    <td><?php echo htmlspecialchars($student['department']); ?></td>
                                    <td>Y<?php echo $student['year']; ?> S<?php echo $student['current_semester']; ?></td>
                                    <td><?php echo $student['intake_year'] ?: 'N/A'; ?></td>
                                    <td>
                                        <button onclick="editStudent(<?php echo $student['id']; ?>, '<?php echo addslashes($student['full_name']); ?>', 
                                                                    <?php echo $student['year']; ?>, <?php echo $student['current_semester']; ?>)" 
                                                class="btn btn-small btn-primary">Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Promotion History -->
            <div class="admin-card">
                <h2>📜 Recent Promotion History</h2>
                
                <?php foreach ($recent_promotions as $promotion): ?>
                    <div class="history-item">
                        <strong><?php echo htmlspecialchars($promotion['full_name']); ?></strong>
                        (<?php echo htmlspecialchars($promotion['roll_number']); ?>) -
                        Y<?php echo $promotion['from_year']; ?>S<?php echo $promotion['from_semester']; ?> → 
                        Y<?php echo $promotion['to_year']; ?>S<?php echo $promotion['to_semester']; ?>
                        <br>
                        <small>
                            <?php echo ucfirst($promotion['promotion_type']); ?> promotion
                            <?php if ($promotion['promoted_by_name']): ?>
                                by <?php echo htmlspecialchars($promotion['promoted_by_name']); ?>
                            <?php endif; ?>
                            on <?php echo date('M j, Y g:i A', strtotime($promotion['promotion_date'])); ?>
                            <?php if ($promotion['notes']): ?>
                                <br>Notes: <?php echo htmlspecialchars($promotion['notes']); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
    
    <!-- Edit Student Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
         background: rgba(0,0,0,0.8); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
             background: #1a1a2e; padding: 30px; border-radius: 15px; width: 90%; max-width: 500px;">
            <h3 id="modalTitle">Edit Student</h3>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="individual_promote">
                <input type="hidden" id="editUserId" name="user_id">
                
                <div class="form-group">
                    <label for="editYear">New Year</label>
                    <select id="editYear" name="new_year" class="form-control" required>
                        <option value="1">First Year</option>
                        <option value="2">Second Year</option>
                        <option value="3">Third Year</option>
                        <option value="4">Fourth Year</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="editSemester">New Semester</label>
                    <select id="editSemester" name="new_semester" class="form-control" required>
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                        <option value="3">Semester 3</option>
                        <option value="4">Semester 4</option>
                        <option value="5">Semester 5</option>
                        <option value="6">Semester 6</option>
                        <option value="7">Semester 7</option>
                        <option value="8">Semester 8</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="editNotes">Notes (Optional)</label>
                    <textarea id="editNotes" name="notes" class="form-control" rows="3" 
                              placeholder="Reason for manual promotion/demotion..."></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary" style="margin-right: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Student</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function editStudent(userId, name, currentYear, currentSemester) {
            document.getElementById('modalTitle').textContent = 'Edit: ' + name;
            document.getElementById('editUserId').value = userId;
            document.getElementById('editYear').value = currentYear;
            document.getElementById('editSemester').value = currentSemester;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>