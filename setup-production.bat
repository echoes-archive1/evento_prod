@echo off
REM Production Deployment Setup Script for Evento
REM Run this before deploying to production

echo ==============================
echo Evento Production Setup
echo ==============================
echo.

REM Create logs directory
echo Creating logs directory...
if not exist "logs" mkdir logs
echo Done: Logs directory created
echo.

REM Create backup directory
echo Creating backup directory...
if not exist "backups" mkdir backups
echo Done: Backup directory created
echo.

echo ==============================
echo Production Checklist:
echo ==============================
echo [ ] Run database migration: database/migrate_email_verification.php
echo [ ] Update BASE_URL in config/config.php
echo [ ] Update email credentials (MAIL_USERNAME, MAIL_PASSWORD)
echo [ ] Set up Gmail App Password
echo [ ] Enable HTTPS (SSL certificate)
echo [ ] Test email sending: database/test-email.php
echo [ ] Review PRODUCTION_DEPLOYMENT.md guide
echo.
echo Setup script completed!
echo.
pause
