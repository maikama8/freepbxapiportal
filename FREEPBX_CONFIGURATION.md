# FreePBX Integration Configuration Guide

## 📋 **Required Information from Your FreePBX Server**

To integrate your Laravel VoIP platform with FreePBX, you need to gather the following information from your FreePBX installation:

### **1. FreePBX API Access**
```bash
# API Endpoint URL
FREEPBX_API_URL=http://your-freepbx-server.com

# API Credentials (Admin user with API access)
FREEPBX_API_USERNAME=your_admin_username
FREEPBX_API_PASSWORD=your_admin_password

# API Version (usually v17 for FreePBX 17)
FREEPBX_API_VERSION=v17
```

### **2. FreePBX Database Access (for CDR)**
```bash
# Database connection details
FREEPBX_DB_HOST=your-freepbx-server.com
FREEPBX_DB_PORT=3306
FREEPBX_DB_DATABASE=asteriskcdrdb
FREEPBX_DB_USERNAME=freepbx_user
FREEPBX_DB_PASSWORD=freepbx_password
```

### **3. SIP Server Details**
```bash
# SIP server configuration
FREEPBX_SIP_DOMAIN=your-freepbx-server.com
FREEPBX_SIP_PORT=5060
FREEPBX_SIP_TRANSPORT=udp
FREEPBX_SIP_CONTEXT=from-internal
```

---

## 🔧 **How to Get This Information**

### **Step 1: FreePBX API Configuration**

1. **Log into FreePBX Admin Panel**
   - URL: `http://your-freepbx-server.com/admin`
   - Use your admin credentials

2. **Enable API Access**
   - Go to: `Settings` → `Advanced Settings`
   - Search for: "API"
   - Enable: `Enable API` = Yes
   - Set: `API Username` = your desired API username
   - Set: `API Password` = your desired API password

3. **Get API URL**
   - Base URL: `http://your-freepbx-server.com/admin/api/api/`
   - Full API URL: `http://your-freepbx-server.com`

### **Step 2: Database Access**

1. **MySQL/MariaDB Access**
   - Host: Same as your FreePBX server IP/domain
   - Port: Usually 3306
   - Database: `asteriskcdrdb` (for call records)
   - Username: Create a dedicated user or use existing
   - Password: Set a secure password

2. **Create Database User (if needed)**
   ```sql
   CREATE USER 'freepbx_user'@'%' IDENTIFIED BY 'secure_password';
   GRANT SELECT ON asteriskcdrdb.* TO 'freepbx_user'@'%';
   GRANT SELECT ON asterisk.* TO 'freepbx_user'@'%';
   FLUSH PRIVILEGES;
   ```

### **Step 3: SIP Server Information**

1. **Get SIP Domain**
   - Usually your FreePBX server IP or domain name
   - Check: `Settings` → `Asterisk SIP Settings` → `General`

2. **Get SIP Port**
   - Default: 5060 (UDP) or 5061 (TLS)
   - Check: `Settings` → `Asterisk SIP Settings` → `General`

---

## 📝 **Configuration Files to Update**

### **1. Update `.env` file**

Replace the FreePBX section in your `.env` file with your actual values:

```bash
# FreePBX API Configuration
FREEPBX_API_URL=http://192.168.1.100
FREEPBX_API_USERNAME=api_user
FREEPBX_API_PASSWORD=secure_api_password
FREEPBX_API_VERSION=v17
FREEPBX_API_TIMEOUT=30
FREEPBX_API_RETRY_ATTEMPTS=3
FREEPBX_API_RETRY_DELAY=1000

# FreePBX Database Configuration (for CDR access)
FREEPBX_DB_HOST=192.168.1.100
FREEPBX_DB_PORT=3306
FREEPBX_DB_DATABASE=asteriskcdrdb
FREEPBX_DB_USERNAME=freepbx_user
FREEPBX_DB_PASSWORD=secure_db_password

# FreePBX SIP Configuration
FREEPBX_SIP_DOMAIN=192.168.1.100
FREEPBX_SIP_PORT=5060
FREEPBX_SIP_TRANSPORT=udp
FREEPBX_SIP_CONTEXT=from-internal
```

### **2. Configuration is automatically loaded**

The `config/voip.php` file automatically reads these environment variables.

---

## 🧪 **Testing the Connection**

### **Test API Connection**
```bash
php artisan tinker

# Test FreePBX API connection
$client = app(\App\Services\FreePBX\FreePBXApiClient::class);
$result = $client->testConnection();
var_dump($result);
```

### **Test Database Connection**
```bash
php artisan tinker

# Test CDR database connection
$cdr = app(\App\Services\FreePBX\CDRService::class);
$records = $cdr->getRecentCalls(10);
var_dump($records);
```

---

## 🔒 **Security Considerations**

### **1. Network Security**
- Ensure FreePBX server is accessible from your Laravel app server
- Use VPN or private network if possible
- Configure firewall rules appropriately

### **2. API Security**
- Create dedicated API user with minimal required permissions
- Use strong passwords
- Consider API key rotation

### **3. Database Security**
- Create read-only database user for CDR access
- Limit database access to specific IP addresses
- Use encrypted connections if possible

---

## 📊 **What Gets Synced**

Once configured, the platform will sync:

### **From FreePBX to Laravel:**
- ✅ Call Detail Records (CDR)
- ✅ Extension status
- ✅ Active calls
- ✅ User extensions

### **From Laravel to FreePBX:**
- ✅ New user extensions
- ✅ Extension configuration
- ✅ Call routing rules
- ✅ User permissions

---

## 🚨 **Common Issues & Solutions**

### **API Connection Failed**
- Check FreePBX API is enabled
- Verify credentials are correct
- Ensure network connectivity
- Check firewall settings

### **Database Connection Failed**
- Verify database credentials
- Check MySQL/MariaDB is running
- Ensure user has proper permissions
- Test connection from Laravel server

### **SIP Registration Failed**
- Check SIP domain and port
- Verify network connectivity on SIP port
- Check FreePBX SIP settings
- Ensure extensions are properly configured

---

## 📞 **Example Configuration**

Here's a complete example for a typical setup:

```bash
# Example: FreePBX server at 192.168.1.100
FREEPBX_API_URL=http://192.168.1.100
FREEPBX_API_USERNAME=voip_api
FREEPBX_API_PASSWORD=VoIP@2024!Secure
FREEPBX_API_VERSION=v17

FREEPBX_DB_HOST=192.168.1.100
FREEPBX_DB_PORT=3306
FREEPBX_DB_DATABASE=asteriskcdrdb
FREEPBX_DB_USERNAME=voip_cdr_user
FREEPBX_DB_PASSWORD=CDR@2024!Read

FREEPBX_SIP_DOMAIN=192.168.1.100
FREEPBX_SIP_PORT=5060
FREEPBX_SIP_TRANSPORT=udp
FREEPBX_SIP_CONTEXT=from-internal
```

After updating these values, run:
```bash
php artisan config:clear
php artisan config:cache
```

Your Laravel VoIP platform should now be able to communicate with FreePBX!