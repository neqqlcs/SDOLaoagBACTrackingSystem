<?php
// router.php - Central routing system with deployment support

// Load deployment configuration
if (!isset($deploymentConfig)) {
    $deploymentConfig = require 'deployment_config.php';
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define the encryption key (change this to a secure random string)
define('URL_ENCRYPTION_KEY', 'YzkZyWVXOmvxfdibNtnozh');

/**
 * Safely get deployment configuration
 */
function getDeploymentConfigRouter() {
    global $deploymentConfig;
    
    if (!isset($deploymentConfig) || !is_array($deploymentConfig)) {
        try {
            $deploymentConfig = require 'deployment_config.php';
        } catch (Exception $e) {
            // Return default config if file can't be loaded
            return [
                'url_encryption' => [
                    'enabled' => false,
                    'use_obfuscation' => false,
                    'use_base64' => false
                ]
            ];
        }
    }
    
    return $deploymentConfig;
}

/**
 * URL Encryption/Decryption Functions
 */

// Function to encrypt a URL path
if (!function_exists('encryptPath')) {
    function encryptPath($path) {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($path, 'AES-256-CBC', URL_ENCRYPTION_KEY, 0, $iv);
        return urlencode(base64_encode($encrypted . '::' . base64_encode($iv)));
    }
}

// Function to decrypt a URL path (with fallback support)
function decryptPath($encryptedPath) {
    $deploymentConfig = getDeploymentConfigRouter();
    
    // If URL encryption is disabled, try deobfuscation
    if (!isset($deploymentConfig['url_encryption']['enabled']) || !$deploymentConfig['url_encryption']['enabled']) {
        if (isset($deploymentConfig['url_encryption']['use_obfuscation']) && $deploymentConfig['url_encryption']['use_obfuscation']) {
            require_once 'url_helper.php';
            return deobfuscatePath($encryptedPath);
        } else {
            return $encryptedPath; // Return as-is for direct access
        }
    }
    
    // Try OpenSSL decryption if available
    if (function_exists('openssl_decrypt')) {
        try {
            $decoded = base64_decode(urldecode($encryptedPath));
            
            // Check if the decoded string contains the expected separator
            if (strpos($decoded, '::') === false) {
                return false; // Not an encrypted path
            }
            
            list($encrypted_data, $iv_encoded) = explode('::', $decoded, 2);
            
            // Validate that we have both parts
            if (empty($encrypted_data) || empty($iv_encoded)) {
                return false;
            }
            
            $iv = base64_decode($iv_encoded);
            $decrypted = openssl_decrypt($encrypted_data, 'AES-256-CBC', URL_ENCRYPTION_KEY, 0, $iv);
            
            return $decrypted !== false ? $decrypted : false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Fallback: try deobfuscation
    if ($deploymentConfig['url_encryption']['use_obfuscation']) {
        require_once 'url_helper.php';
        return deobfuscatePath($encryptedPath);
    }
    
    return false;
}

// Function to generate a URL (with deployment support)
function generateUrl($path, $params = []) {
    $deploymentConfig = getDeploymentConfigRouter();
    
    // Build query string if params exist
    if (!empty($params)) {
        $path .= '?' . http_build_query($params);
    }
    
    // Check if URL processing is enabled
    if ((isset($deploymentConfig['url_encryption']['enabled']) && $deploymentConfig['url_encryption']['enabled']) || 
        (isset($deploymentConfig['url_encryption']['use_obfuscation']) && $deploymentConfig['url_encryption']['use_obfuscation'])) {
        // Use encryption or obfuscation
        if (isset($deploymentConfig['url_encryption']['enabled']) && $deploymentConfig['url_encryption']['enabled'] && function_exists('openssl_encrypt')) {
            $encrypted = encryptPath($path);
        } else {
            require_once 'url_helper.php';
            $encrypted = obfuscatePath($path);
        }
        
        return $encrypted;
    } else {
        // Direct access for simple deployments
        return $path;
    }
}

/**
 * Routing Logic
 */

// Get the encrypted path from the URL
$encryptedPath = $_GET['path'] ?? '';

// Default to index if no path is provided
if (empty($encryptedPath)) {
    $targetFile = 'index.php';
} else {
    // Try to decrypt the path first
    $decryptedPath = decryptPath($encryptedPath);
    
    if ($decryptedPath !== false) {
        // Successfully decrypted - this was an encrypted URL
        $parts = explode('?', $decryptedPath, 2);
        $targetFile = $parts[0];
        
        if (isset($parts[1])) {
            // Parse and add query parameters
            parse_str($parts[1], $queryParams);
            foreach ($queryParams as $key => $value) {
                $_GET[$key] = $value;
            }
        }
    } else {
        // Decryption failed - try direct file access
        $targetFile = $encryptedPath;
        
        // If it's a .php file and exists, allow direct access
        if (!file_exists($targetFile)) {
            // File doesn't exist, redirect to index
            header("Location: index.php");
            exit;
        }
    }
}

// Check if the target file exists
if (!file_exists($targetFile)) {
    header("HTTP/1.0 404 Not Found");
    echo "404 - File not found";
    exit;
}

// Define a global function to be used in all pages for generating URLs
if (!function_exists('url')) {
    function url($path, $params = []) {
        return generateUrl($path, $params);
    }
}

// Include the target file
include $targetFile;