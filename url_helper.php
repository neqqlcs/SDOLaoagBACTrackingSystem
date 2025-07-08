<?php
// url_helper.php - Helper functions for URL handling with deployment support

// Load deployment configuration
if (!isset($deploymentConfig)) {
    $deploymentConfig = require 'deployment_config.php';
}

// Define the encryption key if not already defined (must match router.php)
if (!defined('URL_ENCRYPTION_KEY')) {
    define('URL_ENCRYPTION_KEY', 'YzkZyWVXOmvxfdibNtnozh');
}

/**
 * Safely get deployment configuration
 */
function getDeploymentConfig() {
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
 * Check if OpenSSL encryption is available and enabled
 */
function isEncryptionAvailable() {
    $deploymentConfig = getDeploymentConfig();
    
    return isset($deploymentConfig['url_encryption']['enabled']) && 
           $deploymentConfig['url_encryption']['enabled'] && 
           function_exists('openssl_encrypt') && 
           function_exists('openssl_decrypt') &&
           function_exists('openssl_random_pseudo_bytes');
}

/**
 * Simple base64 obfuscation for shared hosting
 */
function obfuscatePath($path) {
    $deploymentConfig = getDeploymentConfig();
    
    if (isset($deploymentConfig['url_encryption']['use_base64']) && $deploymentConfig['url_encryption']['use_base64']) {
        // Add a simple prefix and encode
        $obfuscated = base64_encode('DepEdBAC_' . $path);
        // Make URL safe
        return rtrim(strtr($obfuscated, '+/', '-_'), '=');
    }
    
    return $path;
}

/**
 * Decode obfuscated path
 */
function deobfuscatePath($obfuscatedPath) {
    $deploymentConfig = getDeploymentConfig();
    
    if (isset($deploymentConfig['url_encryption']['use_base64']) && $deploymentConfig['url_encryption']['use_base64']) {
        try {
            // Make base64 standard
            $base64 = strtr($obfuscatedPath, '-_', '+/');
            // Add padding if needed
            $base64 = str_pad($base64, strlen($base64) % 4, '=', STR_PAD_RIGHT);
            
            $decoded = base64_decode($base64);
            
            // Check if it starts with our prefix
            if (strpos($decoded, 'DepEdBAC_') === 0) {
                return substr($decoded, 9); // Remove the prefix
            }
        } catch (Exception $e) {
            return false;
        }
    }
    
    return $obfuscatedPath;
}

/**
 * Encrypts a URL path (with fallback to obfuscation)
 * 
 * @param string $path The path to encrypt
 * @return string The encrypted or obfuscated path
 */
if (!function_exists('encryptPath')) {
    function encryptPath($path) {
        if (isEncryptionAvailable()) {
            // Use OpenSSL encryption when available
            $iv = openssl_random_pseudo_bytes(16);
            $encrypted = openssl_encrypt($path, 'AES-256-CBC', URL_ENCRYPTION_KEY, 0, $iv);
            return urlencode(base64_encode($encrypted . '::' . base64_encode($iv)));
        } else {
            // Fallback to simple obfuscation
            return obfuscatePath($path);
        }
    }
}

/**
 * Generates a secure URL for navigation (with deployment support)
 * 
 * @param string $path The target PHP file (e.g., 'login.php')
 * @param array $params Optional query parameters
 * @return string The encrypted/obfuscated URL or direct path
 */
if (!function_exists('url')) {
    function url($path, $params = []) {
        $deploymentConfig = getDeploymentConfig();
        
        // Check if generateUrl exists (from router.php)
        if (function_exists('generateUrl')) {
            return generateUrl($path, $params);
        } else {
            // Check if URL processing is enabled
            if (isset($deploymentConfig['url_encryption']) && 
                (isset($deploymentConfig['url_encryption']['enabled']) && $deploymentConfig['url_encryption']['enabled']) || 
                (isset($deploymentConfig['url_encryption']['use_obfuscation']) && $deploymentConfig['url_encryption']['use_obfuscation'])) {
                // Build query string if params exist
                if (!empty($params)) {
                    $path .= '?' . http_build_query($params);
                }
                
                // Encrypt or obfuscate the path
                $processedPath = encryptPath($path);
                return $processedPath;
            } else {
                // Direct path for simple deployments
                if (!empty($params)) {
                    return $path . '?' . http_build_query($params);
                }
                return $path;
            }
        }
    }
}

/**
 * Redirects to a secure URL
 * 
 * @param string $path The target PHP file
 * @param array $params Optional query parameters
 */
if (!function_exists('redirect')) {
    function redirect($path, $params = []) {
        $url = url($path, $params);
        header("Location: $url");
        exit;
    }
}