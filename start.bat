@echo off
REM ============================================
REM EVENTO - Quick Start Script
REM Automates common tasks
REM ============================================

:MENU
cls
echo.
echo ========================================
echo       EVENTO - Quick Start Menu
echo ========================================
echo.
echo  1. Open phpMyAdmin (Database)
echo  2. Open Evento (Browser)
echo  3. Open Check Installation
echo  4. Create Upload Folders
echo  5. View Documentation
echo  6. Exit
echo.
echo ========================================

set /p choice="Enter your choice (1-6): "

if "%choice%"=="1" goto PHPMYADMIN
if "%choice%"=="2" goto BROWSER
if "%choice%"=="3" goto CHECK
if "%choice%"=="4" goto FOLDERS
if "%choice%"=="5" goto DOCS
if "%choice%"=="6" goto EXIT
goto MENU

:PHPMYADMIN
echo.
echo Opening phpMyAdmin...
start https://hitanshparikh.tech/evento/phpmyadmin
timeout /t 2 >nul
goto MENU

:BROWSER
echo.
echo Opening Evento...
start https://hitanshparikh.tech/evento
timeout /t 2 >nul
goto MENU

:CHECK
echo.
echo Opening Installation Check...
start https://hitanshparikh.tech/evento/check.php
timeout /t 2 >nul
goto MENU

:FOLDERS
echo.
echo Creating upload directories...
call create-folders.bat
goto MENU

:DOCS
echo.
echo Opening documentation...
start README.md
start SETUP.md
timeout /t 2 >nul
goto MENU

:EXIT
echo.
echo Thank you for using Evento!
echo.
timeout /t 2 >nul
exit
