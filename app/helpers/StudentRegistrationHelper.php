<?php
/**
 * Student Registration Helper
 * Handles email extraction, student progression, and automatic promotions
 */

class StudentRegistrationHelper {
    private static $db = null;
    
    public static function init() {
        if (self::$db === null) {
            self::$db = Database::getInstance()->getConnection();
        }
    }
    
    /**
     * Extract student data from email address
     * @param string $email
     * @return array Extracted data or empty array if extraction fails
     */
    public static function extractDataFromEmail($email) {
        self::init();
        
        $email = strtolower(trim($email));
        $domain = substr(strrchr($email, "@"), 1);
        
        if (empty($domain)) {
            return [];
        }
        
        // Hardcoded patterns for charusat.edu.in emails
        $supported_domains = ['charusat.edu.in', 'charusat.ac.in'];
        
        if (!in_array($domain, $supported_domains)) {
            return [];
        }
        
        try {
            $username = substr($email, 0, strpos($email, '@'));
            
            // Pattern: YYDEPTnnn (e.g., 24CS096, 23DCE014)
            // YY = year (24 = 2024), DEPT = department code, nnn = roll number
            if (preg_match('/^(\d{2})([A-Z]+)(\d+)$/i', $username, $matches)) {
                $extracted_data = [
                    'auto_extracted' => true,
                    'roll_number' => strtoupper($username)
                ];
                
                // Extract year from roll number (first 2 digits)
                if (preg_match('/^(\d{2})/', $username, $year_match)) {
                    $year_digits = (int)$year_match[1];
                    
                    // Convert to full year (e.g., 24 = 2024, 23 = 2023)
                    $intake_year = 2000 + $year_digits;
                    
                    // Calculate current academic position
                    $academic_info = self::calculateAcademicPosition($intake_year);
                    $extracted_data = array_merge($extracted_data, $academic_info);
                }
                
                // Extract department code (letters between numbers)
                if (preg_match('/^\d{2}([A-Z]+)\d+$/i', $username, $dept_match)) {
                    $dept_code = strtoupper($dept_match[1]);
                    $dept_info = self::getDepartmentAndInstitute($dept_code);
                    
                    $extracted_data['department_code'] = $dept_code;
                    $extracted_data['department'] = $dept_info['department'];
                    $extracted_data['institute'] = $dept_info['institute'];
                }
                
                return $extracted_data;
            }
            
        } catch (Exception $e) {
            error_log("Email extraction error: " . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * Calculate academic position based on intake year
     * @param int $intake_year
     * @return array Current year and semester
     */
    public static function calculateAcademicPosition($intake_year) {
        $current_date = new DateTime();
        $current_year = (int)$current_date->format('Y');
        $current_month = (int)$current_date->format('n');
        
        // Calculate years since intake
        $years_since_intake = $current_year - $intake_year;
        
        // Determine academic year and semester
        // Academic year typically starts in July/August
        if ($current_month >= 7) {
            // July onwards - start of new academic year
            $academic_year = $years_since_intake + 1;
            $semester = ($academic_year * 2) - 1; // Odd semester
        } else {
            // January to June - second half of academic year
            $academic_year = $years_since_intake;
            $semester = $academic_year * 2; // Even semester
        }
        
        // Cap at 4th year, 8th semester
        if ($academic_year > 4) {
            $academic_year = 4;
            $semester = 8;
        }
        
        if ($semester > 8) {
            $semester = 8;
        }
        
        return [
            'year' => (string)$academic_year,
            'current_semester' => $semester,
            'calculated_on' => $current_date->format('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get department and institute from department code
     * @param string $dept_code
     * @return array Department name and institute
     */
    public static function getDepartmentAndInstitute($dept_code) {
        // Determine institute based on code pattern
        $institute = 'CSPIT'; // Default
        $clean_code = $dept_code;
        
        // Check if it starts with D (Depstar indicator)
        if (substr($dept_code, 0, 1) === 'D') {
            $institute = 'Depstar';
            $clean_code = substr($dept_code, 1); // Remove 'D' prefix
        }
        
        // Department mapping with simplified names
        $department_mapping = [
            'CS' => 'CSE',           // Computer Science → CSE (Computer Science and Engineering)
            'CE' => 'CE',            // Computer Engineering → CE  
            'IT' => 'IT',            // Information Technology → IT
            'EC' => 'EC',            // Electronics and Communication → EC
            'ME' => 'ME',            // Mechanical Engineering → ME
            'EE' => 'EE',            // Electrical Engineering → EE
            'CL' => 'CE',            // Civil Engineering → CE 
            'CH' => 'CHE',           // Chemical Engineering → CHE
            'IC' => 'ICE',           // Instrumentation and Control → ICE
            'EI' => 'EIE',           // Electronics and Instrumentation → EIE
            'ICT' => 'ICT',          // Information and Communication Technology → ICT
            'AI' => 'CSE',           // Artificial Intelligence → CSE
            'ML' => 'CSE',           // Machine Learning → CSE
            'DS' => 'CSE',           // Data Science → CSE
            'MBA' => 'MBA'           // Master of Business Administration → MBA
        ];
        
        $department = $department_mapping[$clean_code] ?? 'CSE'; // Default fallback
        
        return [
            'department' => $department,
            'institute' => $institute
        ];
    }
    
    /**
     * Get department name from department code (deprecated - use getDepartmentAndInstitute instead)
     * @param string $dept_code
     * @return string Department name
     */
    public static function getDepartmentFromCode($dept_code) {
        $result = self::getDepartmentAndInstitute($dept_code);
        return $result['department'];
    }
    
    /**
     * Check if automatic promotion should occur
     * @return array Promotion info
     */
    public static function checkAutomaticPromotion() {
        self::init();
        
        try {
            // Get promotion settings
            $settings_sql = "SELECT * FROM semester_promotion_settings ORDER BY id DESC LIMIT 1";
            $settings_stmt = self::$db->query($settings_sql);
            $settings = $settings_stmt->fetch();
            
            if (!$settings || !$settings['auto_promotion_active']) {
                return ['should_promote' => false, 'reason' => 'Auto promotion disabled'];
            }
            
            $current_date = new DateTime();
            $january_date = new DateTime($settings['january_promotion_date']);
            $april_date = new DateTime($settings['april_promotion_date']);
            
            $should_promote = false;
            $promotion_type = null;
            
            // Check if we're within promotion windows
            $jan_diff = $current_date->diff($january_date)->days;
            $apr_diff = $current_date->diff($april_date)->days;
            
            if ($settings['january_promotion_enabled'] && 
                $current_date >= $january_date && 
                $jan_diff <= 7) { // Within 7 days of January promotion
                $should_promote = true;
                $promotion_type = 'january';
            } elseif ($settings['april_promotion_enabled'] && 
                      $current_date >= $april_date && 
                      $apr_diff <= 7) { // Within 7 days of April promotion
                $should_promote = true;
                $promotion_type = 'april';
            }
            
            return [
                'should_promote' => $should_promote,
                'promotion_type' => $promotion_type,
                'settings' => $settings
            ];
            
        } catch (Exception $e) {
            error_log("Promotion check error: " . $e->getMessage());
            return ['should_promote' => false, 'reason' => 'Error checking promotion'];
        }
    }
    
    /**
     * Promote students automatically
     * @param array $user_ids Optional specific user IDs to promote
     * @return array Results
     */
    public static function promoteStudents($user_ids = null) {
        self::init();
        
        $promoted_count = 0;
        $errors = [];
        
        try {
            self::$db->beginTransaction();
            
            // Base query for eligible students
            $base_query = "SELECT id, current_semester, year, intake_year, last_promotion_date, full_name 
                          FROM users 
                          WHERE year IN ('1','2','3','4') 
                          AND current_semester < 8
                          AND profile_completed = 1";
            
            $params = [];
            
            if ($user_ids && is_array($user_ids) && !empty($user_ids)) {
                $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
                $base_query .= " AND id IN ($placeholders)";
                $params = $user_ids;
            } else {
                // Only promote if not promoted in the last 3 months
                $base_query .= " AND (last_promotion_date IS NULL OR last_promotion_date < DATE_SUB(NOW(), INTERVAL 3 MONTH))";
            }
            
            $stmt = self::$db->prepare($base_query);
            $stmt->execute($params);
            $students = $stmt->fetchAll();
            
            foreach ($students as $student) {
                try {
                    $current_semester = (int)$student['current_semester'];
                    $current_year = (int)$student['year'];
                    
                    $new_semester = $current_semester + 1;
                    $new_year = $current_year;
                    
                    // If moving to odd semester, increase year
                    if ($new_semester % 2 == 1 && $new_semester > 1) {
                        $new_year = min($current_year + 1, 4);
                    }
                    
                    // Don't promote beyond 4th year, 8th semester
                    if ($new_year > 4 || $new_semester > 8) {
                        continue;
                    }
                    
                    // Update student
                    $update_sql = "UPDATE users 
                                  SET current_semester = :new_semester, 
                                      year = :new_year,
                                      last_promotion_date = NOW(),
                                      updated_at = NOW()
                                  WHERE id = :user_id";
                    
                    $update_stmt = self::$db->prepare($update_sql);
                    $update_result = $update_stmt->execute([
                        'new_semester' => $new_semester,
                        'new_year' => $new_year,
                        'user_id' => $student['id']
                    ]);
                    
                    if ($update_result) {
                        // Log promotion history
                        $history_sql = "INSERT INTO promotion_history 
                                       (user_id, from_year, to_year, from_semester, to_semester, promotion_type)
                                       VALUES (:user_id, :from_year, :to_year, :from_semester, :to_semester, 'automatic')";
                        
                        $history_stmt = self::$db->prepare($history_sql);
                        $history_stmt->execute([
                            'user_id' => $student['id'],
                            'from_year' => $current_year,
                            'to_year' => $new_year,
                            'from_semester' => $current_semester,
                            'to_semester' => $new_semester
                        ]);
                        
                        $promoted_count++;
                        
                        // Log audit
                        Security::logAudit($student['id'], 'automatic_promotion', 'users', $student['id'], 
                                         "Promoted from Year {$current_year} Sem {$current_semester} to Year {$new_year} Sem {$new_semester}");
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Failed to promote {$student['full_name']}: " . $e->getMessage();
                }
            }
            
            self::$db->commit();
            
            return [
                'success' => true,
                'promoted_count' => $promoted_count,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            self::$db->rollback();
            error_log("Bulk promotion error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'promoted_count' => 0
            ];
        }
    }
    
    /**
     * Validate roll number format
     * @param string $roll_number
     * @param string $email
     * @return array Validation result
     */
    public static function validateRollNumber($roll_number, $email = null) {
        $roll_number = strtoupper(trim($roll_number));
        
        // Basic format validation
        if (!preg_match('/^[0-9]{2}[A-Z]{2,4}[0-9]{3}$/', $roll_number)) {
            return [
                'valid' => false,
                'message' => 'Roll number format should be like: 23CS054, 24DCE014, etc.'
            ];
        }
        
        // If email provided, check consistency
        if ($email) {
            $extracted = self::extractDataFromEmail($email);
            if ($extracted && isset($extracted['roll_number'])) {
                if ($extracted['roll_number'] !== $roll_number) {
                    return [
                        'valid' => false,
                        'message' => "Roll number doesn't match your email. Expected: {$extracted['roll_number']}"
                    ];
                }
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * Get suggested profile data for user
     * @param string $email
     * @return array Suggested data
     */
    public static function getSuggestedProfileData($email) {
        $extracted = self::extractDataFromEmail($email);
        
        if (empty($extracted)) {
            return [
                'has_suggestions' => false,
                'message' => 'Please fill in your details manually.'
            ];
        }
        
        return [
            'has_suggestions' => true,
            'extracted_data' => $extracted,
            'message' => 'We\'ve pre-filled some information based on your email. Please verify and complete your profile.'
        ];
    }
}