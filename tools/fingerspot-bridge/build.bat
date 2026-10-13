@echo off
rem Build FingerspotBridge.exe memakai compiler .NET Framework bawaan Windows.
rem Tidak perlu install Visual Studio / .NET SDK.

setlocal

set "CSC=%WINDIR%\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
if not exist "%CSC%" set "CSC=%WINDIR%\Microsoft.NET\Framework\v4.0.30319\csc.exe"
if not exist "%CSC%" (
  echo [ERROR] csc.exe tidak ditemukan. Pastikan .NET Framework 4.x terpasang.
  exit /b 1
)

"%CSC%" /nologo /target:exe /platform:anycpu /out:FingerspotBridge.exe Bridge.cs
if errorlevel 1 (
  echo [ERROR] Kompilasi gagal.
  exit /b 1
)

echo.
echo [OK] FingerspotBridge.exe berhasil dibuat.
echo      Letakkan DLL vendor (SBXPCDLL64.dll, SBPCCOMM64.dll, GEN_FONT64.dll)
echo      di folder ini sebelum menjalankan bridge.
endlocal
