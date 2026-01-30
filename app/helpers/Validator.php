<?php
/**
 * Validation Helper Class
 * Input Validation & Business Logic Validation
 */

class Validator {
    
    private $errors = [];
    
    /**
     * Validate required field
     */
    public function required($field, $value, $label = null) {
        $label = $label ?? ucfirst($field);
        
        if (empty($value) && $value !== '0') {
            $this->errors[$field] = "$label is required";
            return false;
        }
        return true;
    }
    
    /**
     * Validate email
     */
    public function email($field, $value, $label = null) {
        $label = $label ?? ucfirst($field);
        
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "$label must be a valid email address";
            return false;
        }
        return true;
    }
    
    /**
     * Validate college email
     */
    public function collegeEmail($field, $value, $domain = 'college.edu') {
        if (!$this->email($field, $value)) {
            return false;
        }
        
        $email_domain = substr(strrchr($value, "@"), 1);
        if ($email_domain !== $domain) {
            $this->errors[$field] = "Email must be a valid college email (@$domain)";
            return false;
        }
        return true;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength($field, $value, $min, $label = null) {
        $label = $label ?? ucfirst($field);
        
        if (strlen($value) < $min) {
            $this->errors[$field] = "$label must be at least $min characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength($field, $value, $max, $label = null) {
        $label = $label ?? ucfirst($field);
        
        if (strlen($value) > $max) {
            $this->errors[$field] = "$label must not exceed $max characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate password strength
     */
    public function strongPassword($field, $value) {
        if (strlen($value) < 8) {
            $this->errors[$field] = "Password must be at least 8 characters";
            return false;
        }
        
        if (!preg_match('/[A-Z]/', $value)) {
            $this->errors[$field] = "Password must contain at least one uppercase letter";
            return false;
        }
        
        if (!preg_match('/[a-z]/', $value)) {
            $this->errors[$field] = "Password must contain at least one lowercase letter";
            return false;
        }
        
        if (!preg_match('/[0-9]/', $value)) {
            $this->errors[$field] = "Password must contain at least one number";
            return false;
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            $this->errors[$field] = "Password must contain at least one special character";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate phone number
     */
    public function phone($field, $value, $label = null) {
        $label = $label ?? ucfirst($field);
        
        // Indian phone number format
        if (!preg_match('/^[6-9]\d{9}$/', $value)) {
            $this->errors[$field] = "$label must be a valid 10-digit phone number";
            return false;
        }
        return true;
    }
    
    /**
     * Validate unique value in database
     */
    public function unique($field, $value, $table, $column, $exclude_id = null) {
        $db = Database::getInstance()->getConnection();
        
        $sql = "SELECT COUNT(*) FROM $table WHERE $column = :value";
        if ($exclude_id) {
            $sql .= " AND id != :exclude_id";
        }
        
        $stmt = $db->prepare($sql);
        $params = ['value' => $value];
        if ($exclude_id) {
            $params['exclude_id'] = $exclude_id;
        }
        
        $stmt->execute($params);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $this->errors[$field] = ucfirst($field) . " already exists";
            return false;
        }
        return true;
    }
    
    /**
     * Validate date format and future date
     */
    public function futureDate($field, $value, $label = null) {
        $label = $label ?? ucfirst($field);
        
        $date = strtotime($value);
        if (!$date) {
            $this->errors[$field] = "$label must be a valid date";
            return false;
        }
        
        if ($date < time()) {
            $this->errors[$field] = "$label must be a future date";
            return false;
        }
        return true;
    }
    
    /**
     * Add custom error
     */
    public function addError($field, $message) {
        $this->errors[$field] = $message;
    }
    
    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Check if validation passed
     */
    public function passes() {
        return empty($this->errors);
    }
    
    /**
     * Get first error message
     */
    public function getFirstError() {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
}
