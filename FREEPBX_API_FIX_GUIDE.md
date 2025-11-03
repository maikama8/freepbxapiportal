# FreePBX API Permission Fix Guide

## 🚨 **Current Issue**
The FreePBX API is returning `{"error":"ajaxRequest declined"}` with HTTP 403 status.

**This means:** The API user doesn't have sufficient permissions to access the extensions endpoint.

---

## 🔧 **Step-by-Step Fix**

### **Step 1: Access FreePBX Admin Panel**
1. Open your browser and go to: **http://172.86.88.172**
2. Login with your FreePBX admin credentials

### **Step 2: Check API Status**
1. Navigate to: **Connectivity** → **API**
2. Ensure the **API is Enabled**
3. Check that **OAuth2** is enabled

### **Step 3: Verify OAuth2 Client Configuration**
1. In the API section, look for **OAuth2 Clients**
2. Find the client with ID: `f8a6b7dd0349b1c8393b4e9978b6771da36be0870e759099ed7060ea6e248804`
3. **IMPORTANT:** Ensure this client has the following scopes:
   - `extensions:read`
   - `extensions:write`
   - `extensions:delete`
   - `*` (full access) - **RECOMMENDED**

### **Step 4: Check User Permissions**
1. Go to: **Settings** → **User Management**
2. Find the user: `superadmin`
3. Ensure this user has:
   - **Admin** role
   - **Full system access**
   - **API access permissions**

### **Step 5: Alternative - Create New API User**
If the current user doesn't work, create a dedicated API user:

1. Go to: **Settings** → **User Management** → **Add User**
2. Create user with these settings:
   - **Username:** `apiuser`
   - **Password:** `SecureAPIPass123!`
   - **Role:** `Administrator`
   - **Permissions:** Check ALL boxes

### **Step 6: Update OAuth2 Client Permissions**
1. In **Connectivity** → **API** → **OAuth2 Clients**
2. Edit your OAuth2 client
3. Set **Scopes** to: `*` (asterisk for full access)
4. Set **Grant Types** to: `client_credentials`
5. **Save** the configuration

### **Step 7: Test API Access**
After making changes, test with:
```bash
php artisan freepbx:diagnose-api
```

---

## 🔍 **Common Issues & Solutions**

### **Issue 1: "ajaxRequest declined"**
**Cause:** User lacks API permissions
**Solution:** 
- Ensure user has Admin role
- Grant API access permissions
- Set OAuth2 scope to `*`

### **Issue 2: "invalid_client"**
**Cause:** Wrong OAuth2 client credentials
**Solution:**
- Verify Client ID and Secret match
- Regenerate OAuth2 client if needed

### **Issue 3: "invalid_grant"**
**Cause:** Wrong username/password
**Solution:**
- Verify user credentials
- Ensure user exists and is active

---

## 📋 **Current Configuration**
```env
FREEPBX_API_URL=http://172.86.88.172
FREEPBX_API_USERNAME=superadmin
FREEPBX_API_PASSWORD=Os:2AkiX3lKa
FREEPBX_API_CLIENT_ID=f8a6b7dd0349b1c8393b4e9978b6771da36be0870e759099ed7060ea6e248804
FREEPBX_API_CLIENT_SECRET=e930328a836c5f46f9a07c32a4665a96
```

---

## 🎯 **Quick Test Commands**

After fixing permissions, test with:

```bash
# Test connection
php artisan freepbx:test-connection

# Diagnose API
php artisan freepbx:diagnose-api

# Sync SIP accounts
php artisan freepbx:sync-sip-accounts
```

---

## 🆘 **If Still Not Working**

If you continue to get "ajaxRequest declined":

1. **Check FreePBX Logs:**
   - SSH into FreePBX server
   - Check: `/var/log/asterisk/freepbx.log`
   - Look for API-related errors

2. **Try Alternative API Method:**
   - Use FreePBX CLI commands instead
   - Direct database access for extensions

3. **Contact Support:**
   - FreePBX version: Check in **Admin** → **System Status**
   - Provide error logs and configuration details

---

## 📞 **Manual Extension Creation (Temporary Fix)**

If API continues to fail, manually create extensions in FreePBX:

1. Go to: **Applications** → **Extensions**
2. Click **Add Extension** → **Generic SIP Device**
3. Create extensions:
   - **Extension 2000** for "Fresh Test User"
   - **Extension 2001** for "Test Test"
4. Set passwords and save

This will allow your customers to use their SIP accounts while you fix the API permissions.