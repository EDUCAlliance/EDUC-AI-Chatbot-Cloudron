<?php
/**
 * Health Check Endpoint for Cloudron
 * Simple health check that responds quickly
 */

// Simple health check - don't require database connectivity
$status = [
    'status' => 'ok',
    'timestamp' => time(),
    'version' => '1.0.0'
];

// Check if we can write to session directory
if (!is_writable('/run/app/sessions')) {
    $status['status'] = 'warning';
    $status['message'] = 'Session directory not writable';
}

// Return JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache');
http_response_code(200);

echo json_encode($status);
?> 