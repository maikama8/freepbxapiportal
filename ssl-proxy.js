#!/usr/bin/env node

const https = require('https');
const http = require('http');
const httpProxy = require('http-proxy');
const fs = require('fs');
const path = require('path');

// Configuration
const config = {
    target: 'http://127.0.0.1:8000', // Laravel dev server
    ssl: {
        port: 8443,
        host: 'freepbx-dev.local',
        cert: path.join(__dirname, 'storage/ssl/cert.pem'),
        key: path.join(__dirname, 'storage/ssl/key.pem')
    }
};

// Check if SSL certificates exist
if (!fs.existsSync(config.ssl.cert) || !fs.existsSync(config.ssl.key)) {
    console.error('SSL certificates not found!');
    console.error('Please ensure cert.pem and key.pem exist in storage/ssl/');
    process.exit(1);
}

// Create proxy server
const proxy = httpProxy.createProxyServer({
    target: config.target,
    changeOrigin: true,
    secure: false
});

// Handle proxy errors
proxy.on('error', (err, req, res) => {
    console.error('Proxy error:', err.message);
    if (res && !res.headersSent) {
        res.writeHead(500, { 'Content-Type': 'text/plain' });
        res.end('Proxy error: ' + err.message);
    }
});

// Create HTTPS server
const server = https.createServer({
    cert: fs.readFileSync(config.ssl.cert),
    key: fs.readFileSync(config.ssl.key)
}, (req, res) => {
    // Add security headers
    res.setHeader('X-Forwarded-Proto', 'https');
    res.setHeader('X-Forwarded-Host', req.headers.host);
    
    // Proxy the request
    proxy.web(req, res, {
        target: config.target
    });
});

// Start the SSL proxy server
server.listen(config.ssl.port, config.ssl.host, () => {
    console.log('\n🚀 SSL Development Server Started!');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log(`📡 HTTPS Server: https://${config.ssl.host}:${config.ssl.port}`);
    console.log(`🎯 Proxying to:  ${config.target}`);
    console.log(`📁 SSL Cert:     ${config.ssl.cert}`);
    console.log(`🔑 SSL Key:      ${config.ssl.key}`);
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('\n💡 Make sure to:');
    console.log('   1. Start Laravel dev server: php artisan serve');
    console.log('   2. Accept the self-signed certificate in your browser');
    console.log('   3. Access: https://freepbx-dev.local:8443');
    console.log('\n⏹️  Press Ctrl+C to stop the server\n');
});

// Handle WebSocket upgrades
server.on('upgrade', (req, socket, head) => {
    proxy.ws(req, socket, head, {
        target: config.target.replace('http:', 'ws:')
    });
});

// Graceful shutdown
process.on('SIGINT', () => {
    console.log('\n\n🛑 Shutting down SSL proxy server...');
    server.close(() => {
        console.log('✅ Server stopped gracefully');
        process.exit(0);
    });
});

process.on('SIGTERM', () => {
    console.log('\n\n🛑 Received SIGTERM, shutting down...');
    server.close(() => {
        console.log('✅ Server stopped gracefully');
        process.exit(0);
    });
});