<?php
/**
 * QR Code Generator Helper
 * Provides reliable QR code generation with multiple fallback options
 */

class QRCode {
    
    /**
     * Generate QR code image data
     * @param string $data The data to encode in QR code
     * @param int $size Size of QR code (default 300)
     * @return array [success, data, mime_type]
     */
    public static function generate($data, $size = 300) {
        $encoded_data = urlencode($data);
        $qr_size = "{$size}x{$size}";
        
        // Multiple QR code generation services for reliability
        $qr_services = [
            [
                'url' => "https://api.qrserver.com/v1/create-qr-code/?size={$qr_size}&format=png&data=" . $encoded_data,
                'mime' => 'image/png'
            ],
            [
                'url' => "https://quickchart.io/qr?text=" . $encoded_data . "&size={$size}",
                'mime' => 'image/png'
            ],
            [
                'url' => "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl=" . $encoded_data . "&choe=UTF-8",
                'mime' => 'image/png'
            ]
        ];
        
        // Try each service until one works
        foreach ($qr_services as $service) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'GET',
                    'header' => "User-Agent: Evento-QR-Generator/1.0\r\n"
                ]
            ]);
            
            $image_data = @file_get_contents($service['url'], false, $context);
            
            if ($image_data !== false && strlen($image_data) > 100) {
                // Validate that we got actual image data
                $image_info = @getimagesizefromstring($image_data);
                if ($image_info !== false) {
                    return [
                        'success' => true,
                        'data' => $image_data,
                        'base64' => base64_encode($image_data),
                        'mime_type' => $service['mime'],
                        'service' => $service['url']
                    ];
                }
            }
        }
        
        // If all services fail, generate SVG fallback
        return self::generateSVGFallback($data, $size);
    }
    
    /**
     * Generate a simple SVG QR code alternative when services fail
     * @param string $data The data to display
     * @param int $size Size of the SVG
     * @return array [success, data, mime_type]
     */
    private static function generateSVGFallback($data, $size = 300) {
        // Create a simple grid pattern SVG as QR alternative
        $svg = '<?xml version="1.0" encoding="UTF-8"?>
        <svg width="' . $size . '" height="' . $size . '" xmlns="http://www.w3.org/2000/svg">
            <!-- Background -->
            <rect width="' . $size . '" height="' . $size . '" fill="#ffffff" stroke="#000000" stroke-width="2"/>
            
            <!-- QR-like pattern -->
            <g fill="#000000">';
        
        // Add some QR-like corner patterns
        $corner_size = $size / 10;
        $positions = [
            [0, 0], [$size - $corner_size * 3, 0], [0, $size - $corner_size * 3]
        ];
        
        foreach ($positions as $pos) {
            $svg .= '<rect x="' . ($pos[0] + 10) . '" y="' . ($pos[1] + 10) . '" width="' . ($corner_size * 3) . '" height="' . ($corner_size * 3) . '" fill="none" stroke="#000000" stroke-width="3"/>
                    <rect x="' . ($pos[0] + 20) . '" y="' . ($pos[1] + 20) . '" width="' . $corner_size . '" height="' . $corner_size . '" fill="#000000"/>';
        }
        
        $svg .= '</g>
            
            <!-- Center text -->
            <text x="' . ($size/2) . '" y="' . ($size/2 - 20) . '" text-anchor="middle" font-family="Arial, sans-serif" font-size="16" font-weight="bold" fill="#333333">REGISTRATION CODE</text>
            <text x="' . ($size/2) . '" y="' . ($size/2 + 10) . '" text-anchor="middle" font-family="monospace" font-size="14" fill="#000000" font-weight="bold">' . htmlspecialchars($data) . '</text>
            <text x="' . ($size/2) . '" y="' . ($size/2 + 30) . '" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" fill="#666666">Present at event venue</text>
        </svg>';
        
        return [
            'success' => true,
            'data' => $svg,
            'base64' => base64_encode($svg),
            'mime_type' => 'image/svg+xml',
            'service' => 'svg_fallback'
        ];
    }
    
    /**
     * Get QR code as data URL for email embedding
     * @param string $data The data to encode
     * @param int $size Size of QR code
     * @return string Data URL for embedding in HTML
     */
    public static function getDataURL($data, $size = 300) {
        $qr_result = self::generate($data, $size);
        
        if ($qr_result['success']) {
            return 'data:' . $qr_result['mime_type'] . ';base64,' . $qr_result['base64'];
        }
        
        return '';
    }
    
    /**
     * Save QR code to file
     * @param string $data The data to encode
     * @param string $filepath Path to save the file
     * @param int $size Size of QR code
     * @return bool Success status
     */
    public static function saveToFile($data, $filepath, $size = 300) {
        $qr_result = self::generate($data, $size);
        
        if ($qr_result['success']) {
            $result = file_put_contents($filepath, $qr_result['data']);
            return $result !== false;
        }
        
        return false;
    }
    
    /**
     * Generate event ticket with QR code
     * @param array $event_details Event information
     * @param array $participant_details Participant information
     * @param string $qr_code QR code data
     * @param int $width Ticket width (default 800)
     * @param int $height Ticket height (default 400)
     * @return array [success, data, mime_type]
     */
    public static function generateEventTicket($event_details, $participant_details, $qr_code, $width = 800, $height = 400) {
        // Generate QR code
        $qr_result = self::generate($qr_code, 150);
        
        if (!$qr_result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to generate QR code for ticket'
            ];
        }
        
        // Create ticket SVG
        $svg = self::createTicketSVG($event_details, $participant_details, $qr_result['base64'], $width, $height);
        
        return [
            'success' => true,
            'data' => $svg,
            'base64' => base64_encode($svg),
            'mime_type' => 'image/svg+xml'
        ];
    }
    
    /**
     * Create ticket SVG design
     * @param array $event_details Event information
     * @param array $participant_details Participant information
     * @param string $qr_base64 Base64 encoded QR code
     * @param int $width Ticket width
     * @param int $height Ticket height
     * @return string SVG content
     */
    private static function createTicketSVG($event_details, $participant_details, $qr_base64, $width, $height) {
        $event_name = htmlspecialchars($event_details['event_name'] ?? 'Event');
        $event_date = date('F j, Y', strtotime($event_details['event_date'] ?? 'now'));
        $event_time = date('g:i A', strtotime($event_details['event_date'] ?? 'now'));
        $venue = htmlspecialchars($event_details['venue'] ?? 'Venue TBA');
        $club_name = htmlspecialchars($event_details['club_name'] ?? 'Club');
        
        $participant_name = htmlspecialchars($participant_details['full_name'] ?? 'Participant');
        $roll_number = htmlspecialchars($participant_details['roll_number'] ?? '');
        $department = htmlspecialchars($participant_details['department'] ?? '');
        
        $qr_data_url = 'data:image/png;base64,' . $qr_base64;
        
        return '<?xml version="1.0" encoding="UTF-8"?>
        <svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
            <!-- Background gradient -->
            <defs>
                <linearGradient id="ticketGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
                </linearGradient>
                <filter id="shadow" x="-50%" y="-50%" width="200%" height="200%">
                    <feDropShadow dx="2" dy="4" stdDeviation="3" flood-opacity="0.3"/>
                </filter>
                <pattern id="dots" patternUnits="userSpaceOnUse" width="20" height="20">
                    <circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/>
                </pattern>
            </defs>
            
            <!-- Main ticket background -->
            <rect width="' . $width . '" height="' . $height . '" rx="20" fill="url(#ticketGradient)" filter="url(#shadow)"/>
            
            <!-- Decorative pattern -->
            <rect width="' . $width . '" height="' . $height . '" rx="20" fill="url(#dots)"/>
            
            <!-- Ticket stub perforation -->
            <line x1="' . ($width - 200) . '" y1="20" x2="' . ($width - 200) . '" y2="' . ($height - 20) . '" 
                  stroke="rgba(255,255,255,0.3)" stroke-width="2" stroke-dasharray="5,5"/>
            
            <!-- Left side - Event details -->
            <g transform="translate(30, 30)">
                <!-- Event name -->
                <text x="0" y="30" font-family="Arial, sans-serif" font-size="28" font-weight="bold" fill="white">
                    ' . $event_name . '
                </text>
                
                <!-- Club name -->
                <text x="0" y="60" font-family="Arial, sans-serif" font-size="16" fill="rgba(255,255,255,0.9)">
                    Organized by ' . $club_name . '
                </text>
                
                <!-- Date and time -->
                <g transform="translate(0, 90)">
                    <circle cx="12" cy="12" r="12" fill="rgba(255,255,255,0.2)"/>
                    <text x="12" y="17" font-family="Arial, sans-serif" font-size="14" font-weight="bold" 
                          fill="white" text-anchor="middle">📅</text>
                    <text x="35" y="17" font-family="Arial, sans-serif" font-size="18" font-weight="600" fill="white">
                        ' . $event_date . '
                    </text>
                </g>
                
                <g transform="translate(0, 130)">
                    <circle cx="12" cy="12" r="12" fill="rgba(255,255,255,0.2)"/>
                    <text x="12" y="17" font-family="Arial, sans-serif" font-size="14" font-weight="bold" 
                          fill="white" text-anchor="middle">⏰</text>
                    <text x="35" y="17" font-family="Arial, sans-serif" font-size="18" font-weight="600" fill="white">
                        ' . $event_time . '
                    </text>
                </g>
                
                <g transform="translate(0, 170)">
                    <circle cx="12" cy="12" r="12" fill="rgba(255,255,255,0.2)"/>
                    <text x="12" y="17" font-family="Arial, sans-serif" font-size="14" font-weight="bold" 
                          fill="white" text-anchor="middle">📍</text>
                    <text x="35" y="17" font-family="Arial, sans-serif" font-size="18" font-weight="600" fill="white">
                        ' . $venue . '
                    </text>
                </g>
                
                <!-- Participant details -->
                <g transform="translate(0, 240)">
                    <text x="0" y="0" font-family="Arial, sans-serif" font-size="14" fill="rgba(255,255,255,0.8)">
                        PARTICIPANT
                    </text>
                    <text x="0" y="25" font-family="Arial, sans-serif" font-size="20" font-weight="600" fill="white">
                        ' . $participant_name . '
                    </text>
                    <text x="0" y="50" font-family="Arial, sans-serif" font-size="14" fill="rgba(255,255,255,0.9)">
                        ' . $roll_number . ($department ? ' • ' . $department : '') . '
                    </text>
                </g>
            </g>
            
            <!-- Right side - QR code stub -->
            <g transform="translate(' . ($width - 180) . ', ' . ($height/2 - 75) . ')">
                <!-- QR code background -->
                <rect x="0" y="0" width="150" height="150" rx="10" fill="white"/>
                
                <!-- QR code image -->
                <image x="10" y="10" width="130" height="130" href="' . $qr_data_url . '"/>
            </g>
            
            <!-- Instructions -->
            <text x="' . ($width - 90) . '" y="' . ($height - 30) . '" font-family="Arial, sans-serif" 
                  font-size="12" fill="rgba(255,255,255,0.8)" text-anchor="middle">
                Present at venue
            </text>
            
            <!-- Decorative elements -->
            <circle cx="50" cy="50" r="3" fill="rgba(255,255,255,0.3)"/>
            <circle cx="' . ($width - 50) . '" cy="50" r="3" fill="rgba(255,255,255,0.3)"/>
            <circle cx="50" cy="' . ($height - 50) . '" r="3" fill="rgba(255,255,255,0.3)"/>
            
            <!-- Border -->
            <rect width="' . $width . '" height="' . $height . '" rx="20" fill="none" 
                  stroke="rgba(255,255,255,0.2)" stroke-width="2"/>
        </svg>';
    }
    
    /**
     * Generate QR code with logo overlay
     * @param string $data The data to encode
     * @param string $logo_path Path to logo file
     * @param int $size Size of QR code
     * @return array [success, data, mime_type]
     */
    public static function generateWithLogo($data, $logo_path, $size = 300) {
        // First generate the base QR code
        $qr_result = self::generate($data, $size);
        
        if (!$qr_result['success']) {
            return $qr_result;
        }
        
        // For now, return the base QR code
        // Logo overlay would require image processing libraries
        return $qr_result;
    }
    
    /**
     * Validate QR code format for event registrations
     * @param string $qr_code QR code to validate
     * @return bool Is valid format
     */
    public static function validateEventQRCode($qr_code) {
        // Event QR codes should follow pattern: EVENT_{event_id}_{user_id}_{timestamp}_{random}
        return preg_match('/^EVENT_\d+_\d+_\d+_[a-zA-Z0-9]+$/', $qr_code);
    }
    
    /**
     * Generate secure QR code for event registration
     * @param int $event_id Event ID
     * @param int $user_id User ID
     * @return string Secure QR code
     */
    public static function generateEventQRCode($event_id, $user_id) {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        return "EVENT_{$event_id}_{$user_id}_{$timestamp}_{$random}";
    }
    
    /**
     * Parse event QR code to extract information
     * @param string $qr_code QR code to parse
     * @return array|false Array with event_id, user_id, timestamp, random or false if invalid
     */
    public static function parseEventQRCode($qr_code) {
        if (!self::validateEventQRCode($qr_code)) {
            return false;
        }
        
        $parts = explode('_', $qr_code);
        if (count($parts) !== 5) {
            return false;
        }
        
        return [
            'type' => $parts[0],
            'event_id' => intval($parts[1]),
            'user_id' => intval($parts[2]),
            'timestamp' => intval($parts[3]),
            'random' => $parts[4]
        ];
    }
}