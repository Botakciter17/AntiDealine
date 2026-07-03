#!/bin/bash

echo "=========================================="
echo "🚀 Memulai AntiDeadline..."
echo "=========================================="

# Matikan proses yang mungkin masih nyangkut di port 8001 (Backend)
if lsof -Pi :8001 -sTCP:LISTEN -t >/dev/null ; then
    echo "Membersihkan port 8001..."
    kill -9 $(lsof -Pi :8001 -sTCP:LISTEN -t)
fi

echo "🟢 1. Menyalakan Backend PHP (localhost:8001)"
cd backend
php -S localhost:8001 index.php > ../backend_log.txt 2>&1 &
cd ..

echo "🟢 2. Menyalakan Frontend Vue (Dapat diakses di jaringan lokal)"
cd frontend
npm run dev -- --host &
cd ..

echo "🟢 3. Memeriksa koneksi 9router (AI Proxy)..."
if curl -s --head --request GET http://localhost:20128 | grep "200 OK" > /dev/null; then
    echo "   [OK] 9router terdeteksi berjalan di localhost:20128"
elif curl -s --head --request GET http://localhost:20128 > /dev/null; then
    echo "   [OK] 9router terdeteksi (merespons di localhost:20128)"
else
    echo "   [STARTING] 9router belum jalan. Menghidupkan 9router secara otomatis..."
    npx 9router --port 20128 > /dev/null 2>&1 &
    sleep 3
    if curl -s --head --request GET http://localhost:20128 > /dev/null; then
        echo "   [OK] 9router berhasil dihidupkan di localhost:20128!"
    else
        echo "   [ERROR] Gagal menghidupkan 9router secara otomatis. Jalankan 'npx 9router' manual."
    fi
fi

echo "=========================================="
echo "✅ AntiDeadline berhasil dijalankan!"
echo "👉 Buka browser: http://localhost:5173"
echo ""
echo "Catatan:"
echo "- Pastikan 9router (AI) sudah nyala di localhost:20128 agar fitur AI berfungsi."
echo "- Untuk mematikan server, tekan Ctrl+C"
echo "=========================================="

# Biarkan terminal tetap hidup untuk memantau proses
wait
