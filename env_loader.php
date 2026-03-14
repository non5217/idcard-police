<?php
// idcard/env_loader.php
// Load environment variables from .env file

function loadEnv($file) {
    if (!file_exists($file)) {
        return false;
    }
    
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
    return true;
}

// Load .env file
$envFile = __DIR__ . '/.env';
loadEnv($envFile);

// Define constants for backward compatibility
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

define('CONSOLE_API_URL', $_ENV['CONSOLE_API_URL'] ?? '');
define('CLIENT_ID', $_ENV['CLIENT_ID'] ?? '');
define('CLIENT_SECRET', $_ENV['CLIENT_SECRET'] ?? '');
define('REDIRECT_URI', $_ENV['REDIRECT_URI'] ?? '');

define('TOKEN_SECRET', $_ENV['TOKEN_SECRET'] ?? '');
define('TURNSTILE_SECRET', $_ENV['TURNSTILE_SECRET'] ?? '');

define('UPLOAD_MAX_SIZE', (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 8388608));
define('UPLOAD_DIR', $_ENV['UPLOAD_DIR'] ?? 'secure_uploads');

define('ALLOWED_ORIGINS', $_ENV['ALLOWED_ORIGINS'] ?? '');
define('RATE_LIMIT_ATTEMPTS', (int)($_ENV['RATE_LIMIT_ATTEMPTS'] ?? 10));
define('RATE_LIMIT_WINDOW', (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 300));
?>
