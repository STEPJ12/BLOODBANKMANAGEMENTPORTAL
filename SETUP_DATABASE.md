# Database Configuration Guide

## Step-by-Step Database Setup for Local Development (XAMPP)

### Step 1: Open phpMyAdmin
1. Start XAMPP Control Panel
2. Click **"Start"** next to **Apache** (if not already running)
3. Click **"Start"** next to **MySQL** (if not already running)
4. Click **"Admin"** next to MySQL (this opens phpMyAdmin in your browser)
   - Or manually go to: `http://localhost/phpmyadmin`

### Step 2: Create Database (if not exists)
1. In phpMyAdmin, click on **"New"** in the left sidebar
2. Enter database name: `blood_bank_portal`
3. Select **"utf8mb4_general_ci"** as collation (important for special characters)
4. Click **"Create"**

### Step 3: Import Database Schema (if needed)
1. If the database is empty, you may need to import the schema:
   - Look for `blood_bank_portal.sql` in the project root or `database/` folder
   - In phpMyAdmin, select `blood_bank_portal` database
   - Click **"Import"** tab
   - Choose the `.sql` file
   - Click **"Go"**

### Step 4: Configure Database Connection
**Option A: Using .env File (Recommended)**

1. Create a file named `.env` in the project root folder (`C:\xampp\htdocs\blood\`)
2. Open `.env` in a text editor (Notepad, VS Code, etc.)
3. Add the following configuration:

```
# Database Configuration
DB_HOST=localhost
DB_NAME=blood_bank_portal
DB_USER=root
DB_PASS=

# Application Environment
APP_ENV=development
```

4. **Save** the file

**Option B: Using Default Values (No .env file)**

If you don't create a `.env` file, the system will use these defaults:
- Host: `localhost`
- Database: `blood_bank_portal`
- Username: `root`
- Password: `` (empty - for XAMPP default setup)

### Step 5: Verify Database Connection
1. Refresh your browser where the application is running
2. If you see the database error message, check:
   - MySQL is running in XAMPP
   - Database name is correct: `blood_bank_portal`
   - Username is `root`
   - Password is empty (for default XAMPP setup)

### Step 6: Common XAMPP Database Credentials

For **default XAMPP installation**:
- **Host:** `localhost` or `127.0.0.1`
- **Username:** `root`
- **Password:** `` (empty/blank)
- **Database:** `blood_bank_portal`

### Step 7: If You Set a MySQL Password

If you have configured a MySQL password:
1. Update `.env` file:
   ```
   DB_PASS=your_password_here
   ```
2. Or update `config/db.php` directly (not recommended for production)

### Troubleshooting

**Error: "Database configuration error"**
- Check that MySQL is running in XAMPP
- Verify database name is `blood_bank_portal`
- Check `.env` file exists and has correct values
- Ensure `root` user has no password (or update `.env` with correct password)

**Error: "Access denied"**
- Check MySQL username/password
- Verify database exists in phpMyAdmin
- Ensure MySQL service is running

**Error: "Unknown database"**
- Create the database in phpMyAdmin (Step 2)
- Or import the SQL file if it exists

### For Production Deployment

When deploying to a production server:
1. **MUST** set `DB_PASS` with a strong password
2. Set `APP_ENV=production` in `.env`
3. Use secure database credentials
4. Never commit `.env` file to version control

