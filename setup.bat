@echo off
echo ================================
echo APEX PostMaster Setup Script
echo ================================
echo.

REM Check if MySQL is available
where mysql >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [WARNING] MySQL command not found in PATH.
    echo Please ensure MySQL is installed and added to PATH.
    echo.
)

REM Step 1: Create database
echo [Step 1] Setting up database...
cd database
php migrate.php
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Database migration failed.
    pause
    exit /b 1
)
cd ..
echo.

REM Step 2: Install frontend dependencies
echo [Step 2] Installing frontend dependencies...
cd frontend
call npm install
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] npm install failed.
    pause
    exit /b 1
)
cd ..
echo.

REM Step 3: Build frontend
echo [Step 3] Building frontend...
cd frontend
call npm run build
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Frontend build failed.
    pause
    exit /b 1
)
cd ..
echo.

echo ================================
echo Setup Complete!
echo ================================
echo.
echo Next steps:
echo 1. Configure your .env file at public_html/.env
echo 2. Set up OAuth credentials for Google/Facebook
echo 3. Configure Stripe for payments
echo 4. Start PHP development server: php -S localhost:8080 -t public_html
echo 5. Or configure your web server to point to public_html
echo.
pause
