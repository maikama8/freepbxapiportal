#!/bin/bash

# FreePBX VoIP Platform - SSL Development Server Startup Script
# This script starts both Laravel artisan serve and the SSL proxy

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
LARAVEL_HOST="127.0.0.1"
LARAVEL_PORT="8000"
SSL_HOST="freepbx-dev.local"
SSL_PORT="8443"

echo -e "${BLUE}🚀 FreePBX VoIP Platform - SSL Development Server${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Check if SSL certificates exist
if [ ! -f "storage/ssl/cert.pem" ] || [ ! -f "storage/ssl/key.pem" ]; then
    echo -e "${YELLOW}⚠️  SSL certificates not found. Creating self-signed certificates...${NC}"
    mkdir -p storage/ssl
    openssl req -x509 -newkey rsa:4096 -keyout storage/ssl/key.pem -out storage/ssl/cert.pem -days 365 -nodes -subj "/C=US/ST=CA/L=San Francisco/O=FreePBX VoIP/OU=Development/CN=freepbx-dev.local"
    echo -e "${GREEN}✅ SSL certificates created successfully${NC}"
fi

# Check if Node.js dependencies are installed
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}⚠️  Node.js dependencies not found. Installing...${NC}"
    npm install
    echo -e "${GREEN}✅ Node.js dependencies installed${NC}"
fi

# Check if Laravel dependencies are installed
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}⚠️  Laravel dependencies not found. Installing...${NC}"
    composer install
    echo -e "${GREEN}✅ Laravel dependencies installed${NC}"
fi

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo -e "${YELLOW}⚠️  .env file not found. Copying from .env.example...${NC}"
    cp .env.example .env
    php artisan key:generate
    echo -e "${GREEN}✅ .env file created and key generated${NC}"
fi

# Function to cleanup background processes
cleanup() {
    echo -e "\n${YELLOW}🛑 Shutting down servers...${NC}"
    if [ ! -z "$LARAVEL_PID" ]; then
        kill $LARAVEL_PID 2>/dev/null || true
        echo -e "${GREEN}✅ Laravel server stopped${NC}"
    fi
    if [ ! -z "$SSL_PID" ]; then
        kill $SSL_PID 2>/dev/null || true
        echo -e "${GREEN}✅ SSL proxy stopped${NC}"
    fi
    exit 0
}

# Set trap to cleanup on script exit
trap cleanup SIGINT SIGTERM EXIT

echo -e "\n${BLUE}📡 Starting Laravel development server...${NC}"
php artisan serve --host=$LARAVEL_HOST --port=$LARAVEL_PORT &
LARAVEL_PID=$!

# Wait a moment for Laravel server to start
sleep 2

# Check if Laravel server started successfully
if ! curl -s http://$LARAVEL_HOST:$LARAVEL_PORT > /dev/null; then
    echo -e "${RED}❌ Failed to start Laravel server${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Laravel server started at http://$LARAVEL_HOST:$LARAVEL_PORT${NC}"

echo -e "\n${BLUE}🔒 Starting SSL proxy server...${NC}"
node ssl-proxy.js &
SSL_PID=$!

# Wait a moment for SSL proxy to start
sleep 2

echo -e "\n${GREEN}🎉 Development servers are running!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}📱 Laravel Server: http://$LARAVEL_HOST:$LARAVEL_PORT${NC}"
echo -e "${GREEN}🔐 HTTPS Server:   https://$SSL_HOST:$SSL_PORT${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "\n${YELLOW}💡 Browser Setup:${NC}"
echo -e "   1. Open: ${BLUE}https://$SSL_HOST:$SSL_PORT${NC}"
echo -e "   2. Accept the self-signed certificate warning"
echo -e "   3. Bookmark for easy access"
echo -e "\n${YELLOW}⏹️  Press Ctrl+C to stop both servers${NC}\n"

# Wait for both processes
wait