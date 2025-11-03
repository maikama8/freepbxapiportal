<?php
/**
 * SSL Development Server for FreePBX VoIP Platform
 * This script creates a simple HTTPS server for local development
 */

// Set the document root
$documentRoot = __DIR__ . '/public';
$host = 'freepbx-dev.local';
$port = 8443;

// SSL certificate paths
$certFile = __DIR__ . '/storage/ssl/cert.pem';
$keyFile = __DIR__ . '/storage/ssl/key.pem';

// Check if SSL files exist
if (!file_exists($certFile) || !file_exists($keyFile)) {
    echo "SSL certificates not found. Please run the setup script first.\n";
    exit(1);
}

// Create SSL context
$context = stream_context_create([
    'ssl' => [
        'local_cert' => $certFile,
        'local_pk' => $keyFile,
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
    ],
]);

// Start the server
echo "Starting SSL development server...\n";
echo "Server: https://{$host}:{$port}\n";
echo "Document Root: {$documentRoot}\n";
echo "Press Ctrl+C to stop the server\n\n";

// Use PHP's built-in server with SSL
$command = sprintf(
    'php -S %s:%d -t %s',
    $host,
    $port,
    escapeshellarg($documentRoot)
);

// Set environment variables for Laravel
putenv('APP_ENV=local');
putenv('APP_DEBUG=true');
putenv('APP_URL=https://freepbx-dev.local:8443');

// Execute the command
passthru($command);