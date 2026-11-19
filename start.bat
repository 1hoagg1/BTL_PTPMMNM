@echo off
chcp 65001 >nul
title DevTrack Server - BTL_PTPMMNM
echo ============================================================
echo      KHOI DONG HE THONG DEVTRACK (PHP LOCAL SERVER)
echo ============================================================
echo.
echo  Dia chi web: http://127.0.0.1:8000/login.php
echo  Tai khoan mac dinh: admin / admin123
echo.
echo  Dang mo trinh duyet...
start http://127.0.0.1:8000/login.php
echo.
echo  Server dang chay. Nhan Ctrl + C de dung lai.
echo ------------------------------------------------------------
echo.
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -c "C:\xampp\php\php.ini" -S 127.0.0.1:8000
) else (
    php -S 127.0.0.1:8000
)
pause

