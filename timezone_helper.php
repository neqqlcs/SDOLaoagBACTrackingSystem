<?php
// timezone_helper.php - Helper functions for timezone handling

function setPhilippinesTimezone() {
    // Set PHP timezone to Philippines
    date_default_timezone_set('Asia/Manila');
}

function getCurrentPhilippinesTime($format = 'Y-m-d H:i:s') {
    // Ensure timezone is set
    setPhilippinesTimezone();
    return date($format);
}

function formatDateTimeForDisplay($datetime, $format = 'M d, Y h:i A') {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'Not Available';
    }
    
    // Create DateTime object and set timezone
    $date = new DateTime($datetime);
    $date->setTimezone(new DateTimeZone('Asia/Manila'));
    
    return $date->format($format);
}

function convertToPhilippinesTime($datetime) {
    if (empty($datetime)) return null;
    
    $date = new DateTime($datetime, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Asia/Manila'));
    
    return $date->format('Y-m-d H:i:s');
}

// Set timezone when this file is included
setPhilippinesTimezone();
?>
