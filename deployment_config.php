<?php
// deployment_config.php - Configuration for different hosting environments

// Deployment environment configuration
return [
    // Environment type: 'development', 'production', 'shared_hosting'
    'environment' => 'shared_hosting',
    
    // URL encryption settings
    'url_encryption' => [
        // Enable URL encryption (disable for shared hosting like InfinityFree)
        'enabled' => false,
        
        // Fallback to simple obfuscation when encryption is not available
        'use_obfuscation' => true,
        
        // Base64 encode URLs for basic obfuscation
        'use_base64' => true
    ],
    
    // Security settings for different environments
    'security' => [
        // Enable debug mode (false for production)
        'debug' => true,
        
        // Session security level ('high', 'medium', 'basic')
        'session_security' => 'high',
        
        // Enable HTTPS enforcement (true for production)
        'force_https' => true,
        
        // Enable password hashing (always true for production)
        'password_hashing' => true
    ],
    
    // Database settings can be overridden per environment
    'database' => [
        // Use environment-specific database settings
        'use_env_config' => false,
        
        // Alternative database configuration for production
        'production' => [
            'host' => 'localhost',
            'dbname' => 'your_production_db',
            'username' => 'your_production_user',
            'password' => 'your_production_password'
        ]
    ],
    
    // Feature flags for different environments
    'features' => [
        // Enable session timeout (can be disabled for testing)
        'session_timeout' => false,
        
        // Enable audit logging (for production tracking)
        'audit_logging' => false,
        
        // Enable file upload security checks
        'file_upload_security' => false
    ]
];
?>
