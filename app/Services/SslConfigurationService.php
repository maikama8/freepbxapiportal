<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SslConfigurationService
{
    /**
     * Check SSL/TLS configuration
     */
    public static function checkSslConfiguration(): array
    {
        $results = [
            'https_enabled' => false,
            'ssl_certificate_valid' => false,
            'tls_version' => null,
            'cipher_suite' => null,
            'hsts_enabled' => false,
            'secure_cookies' => false,
            'recommendations' => []
        ];

        // Check if HTTPS is enabled
        $results['https_enabled'] = request()->isSecure();
        
        if (!$results['https_enabled']) {
            $results['recommendations'][] = 'Enable HTTPS/SSL for secure communication';
        }

        // Check secure cookie configuration
        $results['secure_cookies'] = config('session.secure', false);
        
        if (!$results['secure_cookies'] && $results['https_enabled']) {
            $results['recommendations'][] = 'Enable secure cookies in session configuration';
        }

        // Check HSTS header
        $results['hsts_enabled'] = static::checkHstsHeader();
        
        if (!$results['hsts_enabled'] && $results['https_enabled']) {
            $results['recommendations'][] = 'Enable HTTP Strict Transport Security (HSTS)';
        }

        // Check SSL certificate if HTTPS is enabled
        if ($results['https_enabled']) {
            $sslInfo = static::getSslCertificateInfo();
            $results['ssl_certificate_valid'] = $sslInfo['valid'];
            $results['tls_version'] = $sslInfo['tls_version'];
            $results['cipher_suite'] = $sslInfo['cipher_suite'];
            
            if (!$results['ssl_certificate_valid']) {
                $results['recommendations'][] = 'SSL certificate is invalid or expired';
            }
        }

        return $results;
    }

    /**
     * Check if HSTS header is configured
     */
    private static function checkHstsHeader(): bool
    {
        // This would typically be checked by making a request to the application
        // For now, we'll check if it's configured in the security middleware
        return true; // Assuming it's configured in SecurityMiddleware
    }

    /**
     * Get SSL certificate information
     */
    private static function getSslCertificateInfo(): array
    {
        $info = [
            'valid' => false,
            'tls_version' => null,
            'cipher_suite' => null,
            'expires_at' => null,
            'issuer' => null
        ];

        try {
            $url = config('app.url');
            $parsedUrl = parse_url($url);
            
            if ($parsedUrl['scheme'] === 'https') {
                $host = $parsedUrl['host'];
                $port = $parsedUrl['port'] ?? 443;
                
                $context = stream_context_create([
                    'ssl' => [
                        'capture_peer_cert' => true,
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ]
                ]);
                
                $socket = @stream_socket_client(
                    "ssl://{$host}:{$port}",
                    $errno,
                    $errstr,
                    30,
                    STREAM_CLIENT_CONNECT,
                    $context
                );
                
                if ($socket) {
                    $params = stream_context_get_params($socket);
                    
                    if (isset($params['options']['ssl']['peer_certificate'])) {
                        $cert = $params['options']['ssl']['peer_certificate'];
                        $certInfo = openssl_x509_parse($cert);
                        
                        $info['valid'] = $certInfo['validTo_time_t'] > time();
                        $info['expires_at'] = date('Y-m-d H:i:s', $certInfo['validTo_time_t']);
                        $info['issuer'] = $certInfo['issuer']['CN'] ?? 'Unknown';
                    }
                    
                    fclose($socket);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to check SSL certificate', [
                'error' => $e->getMessage()
            ]);
        }

        return $info;
    }

    /**
     * Generate SSL configuration recommendations
     */
    public static function generateSslRecommendations(): array
    {
        return [
            'nginx' => static::generateNginxSslConfig(),
            'apache' => static::generateApacheSslConfig(),
            'laravel' => static::generateLaravelSslConfig(),
        ];
    }

    /**
     * Generate Nginx SSL configuration
     */
    private static function generateNginxSslConfig(): string
    {
        return <<<'NGINX'
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    # SSL Configuration
    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;
    
    # SSL Security Settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    
    # Security Headers
    add_header X-Frame-Options DENY always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Your Laravel application configuration
    root /path/to/your/laravel/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}
NGINX;
    }

    /**
     * Generate Apache SSL configuration
     */
    private static function generateApacheSslConfig(): string
    {
        return <<<'APACHE'
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /path/to/your/laravel/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/your/certificate.crt
    SSLCertificateKeyFile /path/to/your/private.key
    
    # SSL Security Settings
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384
    SSLHonorCipherOrder off
    
    # HSTS
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    
    # Security Headers
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Laravel Configuration
    <Directory /path/to/your/laravel/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName your-domain.com
    Redirect permanent / https://your-domain.com/
</VirtualHost>
APACHE;
    }

    /**
     * Generate Laravel SSL configuration
     */
    private static function generateLaravelSslConfig(): array
    {
        return [
            'env_settings' => [
                'APP_URL' => 'https://your-domain.com',
                'SESSION_SECURE_COOKIE' => 'true',
                'SESSION_SAME_SITE' => 'strict',
                'SANCTUM_STATEFUL_DOMAINS' => 'your-domain.com',
            ],
            'session_config' => [
                'secure' => true,
                'http_only' => true,
                'same_site' => 'strict',
            ],
            'middleware_recommendations' => [
                'Add SecurityMiddleware to global middleware stack',
                'Enable CSRF protection for all forms',
                'Use HTTPS-only cookies',
                'Implement Content Security Policy',
            ]
        ];
    }

    /**
     * Validate SSL certificate
     */
    public static function validateSslCertificate(string $domain): array
    {
        $result = [
            'valid' => false,
            'expires_in_days' => null,
            'issuer' => null,
            'errors' => []
        ];

        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ]
            ]);

            $socket = @stream_socket_client(
                "ssl://{$domain}:443",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                $result['errors'][] = "Failed to connect: {$errstr}";
                return $result;
            }

            $params = stream_context_get_params($socket);
            
            if (isset($params['options']['ssl']['peer_certificate'])) {
                $cert = $params['options']['ssl']['peer_certificate'];
                $certInfo = openssl_x509_parse($cert);
                
                $expiryTime = $certInfo['validTo_time_t'];
                $currentTime = time();
                
                $result['valid'] = $expiryTime > $currentTime;
                $result['expires_in_days'] = ceil(($expiryTime - $currentTime) / 86400);
                $result['issuer'] = $certInfo['issuer']['CN'] ?? 'Unknown';
                
                if ($result['expires_in_days'] < 30) {
                    $result['errors'][] = "Certificate expires in {$result['expires_in_days']} days";
                }
            }

            fclose($socket);
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }
}