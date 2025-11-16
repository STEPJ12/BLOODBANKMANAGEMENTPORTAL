# Red Cross Dashboard Security Implementation Guide

## Overview
This document outlines the comprehensive security features implemented for the Red Cross login and dashboard system.

## Security Features Implemented

### 1. Strong Password Enforcement
- **Requirements:**
  - Minimum 8 characters
  - Maximum 12 characters
  - At least one uppercase letter
  - At least one lowercase letter
  - At least one number
  - At least one special character

- **Function:** `validatePasswordStrength()` in `includes/redcross_security.php`
- **Usage:** Can be called when creating/updating passwords to validate strength

### 2. Account Lockout Mechanism
- **Configuration:**
  - Maximum failed attempts: **5**
  - Lockout duration: **15 minutes**
  - Lockout clears automatically after duration expires

- **Functions:**
  - `isAccountLocked()` - Check if account is currently locked
  - `recordFailedLogin()` - Record failed login attempt and lock if threshold reached
  - `clearFailedLoginAttempts()` - Clear attempts on successful login

- **Database Table:** `account_lockouts`

### 3. OTP (One-Time Password) via Email
- **Features:**
  - 6-digit numeric OTP
  - 10-minute expiry
  - Single-use (marked as used after verification)
  - Auto-cleanup of expired OTPs

- **Login Flow:**
  1. User enters email and password
  2. If credentials are correct, OTP is generated and sent via email
  3. User enters OTP to complete login
  4. Session is established only after OTP verification

- **Functions:**
  - `generateOTP()` - Generate and store OTP
  - `verifyOTP()` - Verify OTP code
  - `sendOTPEmail()` - Send OTP via email using PHPMailer

- **Database Table:** `otp_codes`

### 4. Session Management with Auto-Logout
- **Configuration:**
  - Session timeout: **15 minutes** of inactivity
  - Activity is tracked on every page load
  - Session destroyed automatically on timeout

- **Features:**
  - `trackSessionActivity()` - Records last activity timestamp
  - `checkSessionTimeout()` - Checks if session has expired
  - Auto-redirect to login on timeout with `?expired=1` parameter

- **Database Table:** `session_activities`

### 5. Access Logs & Audit Trails
All security events are logged to `audit_logs` table:
- Login success/failure
- OTP generation and verification
- Account lockouts
- Session timeouts
- Page access (for sensitive pages)
- Rate limit violations

- **Logged Information:**
  - User ID (if available)
  - Role
  - Action type
  - Details/description
  - IP address
  - User agent
  - Timestamp

### 6. Rate Limiting
- **Existing Implementation:**
  - 10 login attempts per 10 minutes per IP
  - Uses existing `rate_limit_exceeded()` function
  - Works alongside account lockout (per-user)

### 7. Input Validation & Sanitization
- **Already Implemented:**
  - All inputs sanitized using `normalize_input()` and `sanitize()`
  - Email validation using `filter_var($email, FILTER_VALIDATE_EMAIL)`
  - Prepared statements for all database queries (SQL injection protection)
  - `htmlspecialchars()` for output escaping (XSS protection)

### 8. Security Headers
Security headers are set automatically on all dashboard pages:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`

## Database Setup

Run the migration file to create required tables:
```sql
-- Run this file:
database/migrations/2025_10_29_add_redcross_security_tables.sql
```

This creates:
1. `account_lockouts` - Tracks failed login attempts and lockout status
2. `otp_codes` - Stores OTP codes for verification
3. `session_activities` - Tracks session activity and last activity time

## Implementation in Dashboard Pages

### For All Red Cross Dashboard Pages:

**Replace existing authentication code with:**
```php
<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/redcross_auth.php';

// Your page code continues here...
```

**What `redcross_auth.php` does automatically:**
1. Starts session if not started
2. Checks if user is logged in and has 'redcross' role
3. Checks session timeout (15 minutes)
4. Tracks session activity
5. Sets security headers
6. Logs access to sensitive pages
7. Redirects to login if any check fails

### Example: Updating a Dashboard Page

**Before:**
```php
<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'redcross') {
    header("Location: ../../loginredcross.php");
    exit;
}
require_once '../../config/db.php';
```

**After:**
```php
<?php
require_once '../../includes/redcross_auth.php';
```

## Login Flow

1. **User visits:** `loginredcross.php`
2. **User enters:** Email and password
3. **System checks:**
   - Rate limiting (10 attempts per 10 min)
   - Email validation
   - User exists in database
   - Account is not locked
   - Password is correct
4. **If password correct:**
   - OTP is generated and sent to user's email
   - User is redirected to `loginredcross.php?step=verify_otp`
   - Temporary session data stored
5. **User enters:** 6-digit OTP code
6. **System verifies:**
   - OTP exists and is not expired
   - OTP matches the code sent
7. **If OTP correct:**
   - Failed login attempts cleared
   - Full session established
   - Activity tracked
   - Redirected to dashboard

## Resending OTP

Users can resend OTP by clicking "Resend OTP" link, which:
- Generates a new OTP
- Sends it to the user's email
- Invalidates the previous OTP

## Account Lockout

After 5 failed login attempts:
- Account is locked for 15 minutes
- User sees message with time remaining
- Lockout automatically clears after 15 minutes
- Admin can manually clear via database if needed

## Session Timeout

- Session expires after 15 minutes of inactivity
- User is redirected to login with `?expired=1` parameter
- Session is destroyed
- Audit log records the timeout

## Password Strength Validation

To validate a password (e.g., in password reset or change):
```php
require_once 'includes/redcross_security.php';

$validation = validatePasswordStrength($password);
if (!$validation['valid']) {
    // Show errors
    foreach ($validation['errors'] as $error) {
        echo $error . "<br>";
    }
}
```

## Maintenance

### Cleanup Function
The `cleanupSecurityData()` function automatically:
- Deletes expired OTPs (older than 1 hour)
- Deletes old session activities (older than 7 days)
- Clears expired lockouts

This runs periodically (1% chance on each login page load) or can be run manually.

## Files Created/Modified

### New Files:
1. `includes/redcross_security.php` - Security functions
2. `includes/redcross_auth.php` - Authentication middleware
3. `database/migrations/2025_10_29_add_redcross_security_tables.sql` - Database schema
4. `SECURITY_IMPLEMENTATION_REDCROSS.md` - This documentation

### Modified Files:
1. `loginredcross.php` - Enhanced with OTP, lockout, and better security
2. `dashboard/redcross/index.php` - Updated to use security middleware

### To Update Other Dashboard Pages:
Replace the authentication block at the top of each file with:
```php
require_once '../../includes/redcross_auth.php';
```

## Security Best Practices

1. **All Red Cross dashboard pages should use `redcross_auth.php`**
2. **Never bypass security checks**
3. **Always use prepared statements for database queries** (already implemented)
4. **Always sanitize and validate user input** (already implemented)
5. **Monitor audit logs regularly** for suspicious activity
6. **Keep email configuration secure** (SMTP credentials in environment variables)
7. **Regular backups** of database including security tables
8. **Update security settings** as needed (lockout threshold, timeout duration)

## Testing

1. **Test Account Lockout:**
   - Try 5 incorrect passwords
   - Account should lock
   - Try logging in while locked
   - Wait 15 minutes and try again

2. **Test OTP:**
   - Login with correct credentials
   - Check email for OTP
   - Enter OTP correctly
   - Try entering wrong OTP

3. **Test Session Timeout:**
   - Login successfully
   - Wait 15+ minutes without activity
   - Try to access dashboard page
   - Should redirect to login

4. **Test Rate Limiting:**
   - Try 10+ login attempts quickly
   - Should see rate limit message

## Troubleshooting

### OTP Not Sending
- Check SMTP configuration in `config/api_keys.php` or environment variables
- Check email server logs
- Verify PHPMailer is properly installed

### Account Locked Forever
- Clear lockout manually in database:
  ```sql
  UPDATE account_lockouts SET locked_until = NULL, failed_attempts = 0 WHERE user_id = ?;
  ```

### Session Timeout Too Short/Long
- Adjust timeout in `redcross_auth.php`:
  ```php
  checkSessionTimeout(15); // Change 15 to desired minutes
  ```

## Additional Recommendations

1. **Regular Security Audits:** Review audit logs weekly
2. **Password Policy Enforcement:** Use `validatePasswordStrength()` when users change passwords
3. **Two-Factor Authentication:** Consider additional 2FA methods in future
4. **IP Whitelisting:** Consider for admin accounts
5. **Backup Encryption:** Ensure database backups are encrypted
6. **SSL/TLS:** Ensure HTTPS is enforced in production
7. **Firewall Rules:** Configure server firewall appropriately
8. **Antivirus:** Keep server antivirus updated

