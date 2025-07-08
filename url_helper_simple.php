<?php
// Simple URL helper for testing/deployment - bypasses all encryption/obfuscation

/**
 * Simple URL function that just returns the path with parameters
 */
function url($path, $params = []) {
    $url = $path;
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    return $url;
}

/**
 * Simple redirect function
 */
function redirect($path, $params = []) {
    $url = url($path, $params);
    header("Location: $url");
    exit();
}

/**
 * No-op functions to prevent errors
 */
function generateUrl($path, $params = []) {
    return url($path, $params);
}

function encryptPath($path) {
    return $path;
}

function decryptPath($encryptedPath) {
    return $encryptedPath;
}

function obfuscatePath($path) {
    return $path;
}

function deobfuscatePath($path) {
    return $path;
}
?>
