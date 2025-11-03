#!/bin/bash

# FreePBX VoIP Platform - Development Server Manager
# Quick commands to manage the development environment

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

show_help() {
    echo -e "${BLUE}FreePBX VoIP Platform - Development Server Manager${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo "Usage: $0 [command]"
    echo ""
    echo "Commands:"
    echo "  start, ssl     Start SSL development server (Laravel + HTTPS proxy)"
    echo "  http           Start HTTP-only Laravel server"
    echo "  stop           Stop all development servers"
    echo "  status         Check server status"
    echo "  setup          Setup SSL certificates and dependencies"
    echo "  clean          Clean cache and restart"
    echo "  help           Show this help message"
    echo ""
    echo "URLs:"
    echo "  HTTP:  http://127.0.0.1:8000"
    echo "  HTTPS: https://freepbx-dev.local:8443"
    echo ""
}

setup_ssl() {
    echo -e "${YELLOW}🔧 Setting up SSL development environment...${NC}"
    
    # Create SSL directory
    mkdir -p storage/ssl
    
    # Generate SSL certificates if they don't exist
    if [ ! -f "storage/ssl/cert.pem" ] || [ ! -f "storage/ssl/key.pem" ]; then
        echo -e "${YELLOW}📜 Generating SSL certificates...${NC}"
        openssl req -x509 -newkey rsa:4096 -keyout storage/ssl/key.pem -out storage/ssl/cert.pem -days 365 -nodes -subj "/C=US/ST=CA/L=San Francisco/O=FreePBX VoIP/OU=Development/CN=freepbx-dev.local"
    fi
    
    # Install Node.js dependencies
    if [ ! -d "node_modules" ]; then
        echo -e "${YELLOW}📦 Installing Node.js dependencies...${NC}"
        npm install
    fi
    
    # Install Composer dependencies
    if [ ! -d "vendor" ]; then
        echo -e "${YELLOW}📦 Installing Composer dependencies...${NC}"
        composer install
    fi
    
    # Setup .env file
    if [ ! -f ".env" ]; then
        echo -e "${YELLOW}⚙️  Setting up .env file...${NC}"
        cp .env.example .env
        php artisan key:generate
    fi
    
    # Add to hosts file if not already there
    if ! grep -q "freepbx-dev.local" /etc/hosts; then
        echo -e "${YELLOW}🌐 Adding freepbx-dev.local to hosts file...${NC}"
        echo "127.0.0.1 freepbx-dev.local" | sudo tee -a /etc/hosts
    fi
    
    echo -e "${GREEN}✅ SSL development environment setup complete!${NC}"
}

start_ssl() {
    echo -e "${BLUE}🚀 Starting SSL development server...${NC}"
    ./start-ssl-dev.sh
}

start_http() {
    echo -e "${BLUE}🚀 Starting HTTP development server...${NC}"
    php artisan serve
}

stop_servers() {
    echo -e "${YELLOW}🛑 Stopping development servers...${NC}"
    
    # Kill Laravel artisan serve processes
    pkill -f "php artisan serve" 2>/dev/null || true
    
    # Kill Node.js SSL proxy processes
    pkill -f "ssl-proxy.js" 2>/dev/null || true
    
    echo -e "${GREEN}✅ All development servers stopped${NC}"
}

check_status() {
    echo -e "${BLUE}📊 Development Server Status${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    # Check Laravel server
    if pgrep -f "php artisan serve" > /dev/null; then
        echo -e "${GREEN}✅ Laravel Server: Running (http://127.0.0.1:8000)${NC}"
    else
        echo -e "${RED}❌ Laravel Server: Not running${NC}"
    fi
    
    # Check SSL proxy
    if pgrep -f "ssl-proxy.js" > /dev/null; then
        echo -e "${GREEN}✅ SSL Proxy: Running (https://freepbx-dev.local:8443)${NC}"
    else
        echo -e "${RED}❌ SSL Proxy: Not running${NC}"
    fi
    
    # Check if ports are in use
    if lsof -i :8000 > /dev/null 2>&1; then
        echo -e "${YELLOW}⚠️  Port 8000 is in use${NC}"
    fi
    
    if lsof -i :8443 > /dev/null 2>&1; then
        echo -e "${YELLOW}⚠️  Port 8443 is in use${NC}"
    fi
}

clean_restart() {
    echo -e "${YELLOW}🧹 Cleaning cache and restarting...${NC}"
    
    # Stop servers
    stop_servers
    
    # Clear Laravel caches
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    
    # Wait a moment
    sleep 2
    
    # Start SSL server
    start_ssl
}

# Main command handling
case "${1:-help}" in
    "start"|"ssl")
        setup_ssl
        start_ssl
        ;;
    "http")
        start_http
        ;;
    "stop")
        stop_servers
        ;;
    "status")
        check_status
        ;;
    "setup")
        setup_ssl
        ;;
    "clean")
        clean_restart
        ;;
    "help"|*)
        show_help
        ;;
esac