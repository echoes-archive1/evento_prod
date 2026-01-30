@echo off
REM Email Notification System Setup for Windows
REM This script sets up Windows Task Scheduler tasks for email processing

echo Setting up Email Notification System...
echo.

REM Get PHP path
set PHP_PATH=C:\xampp\php\php.exe
set PROJECT_PATH=%cd%

echo PHP Path: %PHP_PATH%
echo Project Path: %PROJECT_PATH%
echo.

REM Create Task Scheduler tasks

echo Creating Email Queue Processor task (runs every 5 minutes)...
schtasks /create /sc minute /mo 5 /tn "Evento-EmailProcessor" /tr "\"%PHP_PATH%\" \"%PROJECT_PATH%\cron_email_processor.php\"" /f

echo Creating Event Reminder task (runs every hour)...
schtasks /create /sc hourly /tn "Evento-EventReminders" /tr "\"%PHP_PATH%\" \"%PROJECT_PATH%\cron_event_reminders.php\"" /f

echo.
echo Task creation completed!
echo.
echo You can verify the tasks were created by running:
echo schtasks /query /tn "Evento-EmailProcessor"
echo schtasks /query /tn "Evento-EventReminders"
echo.
echo To remove these tasks later, run:
echo schtasks /delete /tn "Evento-EmailProcessor" /f
echo schtasks /delete /tn "Evento-EventReminders" /f
echo.

REM Create logs directory if it doesn't exist
if not exist "logs" mkdir logs

echo Setup complete!
echo.
echo The following tasks are now scheduled:
echo 1. Email Queue Processor - Runs every 5 minutes
echo 2. Event Reminders - Runs every hour
echo.
echo Check the logs folder for execution logs:
echo - logs/cron_email.log
echo - logs/cron_reminders.log
echo.
pause