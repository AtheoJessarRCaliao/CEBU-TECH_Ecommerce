@echo off
SETLOCAL
SET "WINGET_PHP=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"

WHERE php >nul 2>nul
IF %ERRORLEVEL% EQU 0 (
    SET "PHP_BIN=php"
) ELSE IF EXIST "%WINGET_PHP%" (
    SET "PHP_BIN=%WINGET_PHP%"
) ELSE (
    echo [ERROR] PHP executable not found in PATH.
    pause
    exit /b 1
)

echo ============================================================================
echo Starting CubeTech Hub E-Commerce Server on http://localhost:8000
echo   Document Root       : public/
echo   Customer Storefront : http://localhost:8000/index.php
echo   Admin Dashboard     : http://localhost:8000/admin/index.php
echo   Demo Admin Login    : admin@cubetech.demo / AdminDemo123!
echo ============================================================================
"%PHP_BIN%" -S localhost:8000 -t public
