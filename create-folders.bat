REM ============================================
REM Evento - Create Upload Directories
REM Run this script to create required folders
REM ============================================

@echo off
echo.
echo ====================================
echo  EVENTO - Setup Upload Directories
echo ====================================
echo.

cd /d %~dp0

echo Creating upload directories...
echo.

mkdir "public\uploads" 2>nul
mkdir "public\uploads\events" 2>nul
mkdir "public\uploads\clubs" 2>nul
mkdir "public\uploads\profiles" 2>nul
mkdir "public\uploads\themes" 2>nul

echo [OK] public\uploads\
echo [OK] public\uploads\events\
echo [OK] public\uploads\clubs\
echo [OK] public\uploads\profiles\
echo [OK] public\uploads\themes\

echo.
echo ====================================
echo  Upload directories created!
echo ====================================
echo.

pause
