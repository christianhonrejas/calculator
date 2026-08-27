@echo off
title Diozabeth Fitness - Download Assets
color 0A
echo.
echo  ==========================================
echo   Diozabeth Fitness - Asset Downloader
echo   Run this from your gym-system folder
echo  ==========================================
echo.

:: Create directories
echo [1/7] Creating asset folders...
mkdir assets\css\fontawesome\webfonts 2>nul
mkdir assets\js 2>nul
mkdir assets\fonts 2>nul
echo       Done.

:: Bootstrap CSS
echo [2/7] Downloading Bootstrap...
curl -sL "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" -o "assets\css\bootstrap.min.css"
curl -sL "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" -o "assets\js\bootstrap.bundle.min.js"
echo       Done.

:: Font Awesome
echo [3/7] Downloading Font Awesome...
curl -sL "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" -o "assets\css\fontawesome\all.min.css"
curl -sL "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-solid-900.woff2" -o "assets\css\fontawesome\webfonts\fa-solid-900.woff2"
curl -sL "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-solid-900.ttf" -o "assets\css\fontawesome\webfonts\fa-solid-900.ttf"
curl -sL "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-regular-400.woff2" -o "assets\css\fontawesome\webfonts\fa-regular-400.woff2"
curl -sL "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-regular-400.ttf" -o "assets\css\fontawesome\webfonts\fa-regular-400.ttf"
curl -sL "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-brands-400.woff2" -o "assets\css\fontawesome\webfonts\fa-brands-400.woff2"
curl -sL "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-brands-400.ttf" -o "assets\css\fontawesome\webfonts\fa-brands-400.ttf"
echo       Done.

:: jQuery + DataTables
echo [4/7] Downloading jQuery and DataTables...
curl -sL "https://code.jquery.com/jquery-3.7.1.min.js" -o "assets\js\jquery.min.js"
curl -sL "https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" -o "assets\css\dataTables.bootstrap5.min.css"
curl -sL "https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js" -o "assets\js\jquery.dataTables.min.js"
curl -sL "https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js" -o "assets\js\dataTables.bootstrap5.min.js"
echo       Done.

:: SweetAlert2
echo [5/7] Downloading SweetAlert2...
curl -sL "https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" -o "assets\css\sweetalert2.min.css"
curl -sL "https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js" -o "assets\js\sweetalert2.all.min.js"
echo       Done.

:: Chart.js + jsPDF
echo [6/7] Downloading Chart.js and jsPDF...
curl -sL "https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" -o "assets\js\chart.umd.min.js"
curl -sL "https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" -o "assets\js\jspdf.umd.min.js"
curl -sL "https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.6.0/jspdf.plugin.autotable.min.js" -o "assets\js\jspdf.plugin.autotable.min.js"
echo       Done.

:: Google Fonts (Barlow) - embed as CSS with system fallback
echo [7/7] Creating Barlow font CSS fallback...
(
echo /* Barlow font - downloaded from Google Fonts */
echo /* If download failed, falls back to system sans-serif */
echo @import url^('https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800^&family=Barlow+Condensed:wght@700;800^&display=swap'^);
) > "assets\css\barlow.css"

:: Try to download the actual font CSS too
curl -sL --user-agent "Mozilla/5.0 (Windows NT 10.0; Win64; x64)" "https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@700;800&display=swap" >> "assets\css\barlow.css" 2>nul
echo       Done.

:: Fix Font Awesome webfont paths in CSS
echo.
echo  Fixing Font Awesome webfont paths...
powershell -Command "(Get-Content 'assets\css\fontawesome\all.min.css') -replace '../webfonts/', 'webfonts/' | Set-Content 'assets\css\fontawesome\all.min.css'"

echo.
echo  ==========================================
echo   SUCCESS! All assets downloaded.
echo   You can now open the system in any browser.
echo   http://localhost/gym-system/
echo  ==========================================
echo.
pause
