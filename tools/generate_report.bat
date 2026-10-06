@echo off
REM Monthly Git Report Generator - Quick Launcher
REM Usage: 
REM   - Double-click for current month report
REM   - Run from command line with: generate_report.bat [month] [year]

setlocal enabledelayedexpansion

echo.
echo ========================================
echo   Monthly Git Activity Report Generator
echo ========================================
echo.

cd /d "%~dp0"

REM Set PHP path - adjust if your XAMPP is installed elsewhere
set PHP_PATH=C:\xampp\php\php.exe

REM Check if PHP is available
if not exist "%PHP_PATH%" (
    echo ERROR: PHP not found at %PHP_PATH%
    echo Please adjust PHP_PATH in this batch file
    echo.
    pause
    exit /b 1
)

REM Check if arguments provided
if "%1"=="" (
    echo Generating report for CURRENT MONTH...
    echo.
    "%PHP_PATH%" generate_monthly_report.php
) else (
    echo Generating report for %1/%2...
    echo.
    "%PHP_PATH%" generate_monthly_report.php %1 %2
)

if errorlevel 1 (
    echo.
    echo ERROR: Report generation failed!
    echo Please check the error messages above.
    echo.
    pause
    exit /b 1
)

echo.
echo ========================================
echo   Report Generated Successfully!
echo ========================================
echo.

REM Find the most recently created HTML file
for /f "delims=" %%i in ('dir /b /o-d monthly_report_*.html 2^>nul') do (
    set "LATEST_REPORT=%%i"
    goto :found
)

:found
if defined LATEST_REPORT (
    echo Opening report in browser...
    start "" "%CD%\!LATEST_REPORT!"
    echo.
    echo File location: %CD%\!LATEST_REPORT!
) else (
    echo No report file found.
)

echo.
echo TIP: Click the Print button in the browser to save as PDF!
echo.
pause
