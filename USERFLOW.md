# 🌊 Tuntaz User Flow

Berikut adalah alur penggunaan (User Flow) lengkap dari aplikasi **Tuntaz**, mulai dari pendaftaran hingga fitur peringatan otomatis via WhatsApp.

```mermaid
graph TD
    %% Styling
    classDef default fill:#1E2028,stroke:#3E4451,stroke-width:2px,color:#E0E6ED
    classDef startEnd fill:#008BFF,stroke:#005BBB,stroke-width:2px,color:#FFF,font-weight:bold
    classDef action fill:#2D313A,stroke:#008BFF,stroke-width:2px,color:#FFF
    classDef ai fill:#673AB7,stroke:#512DA8,stroke-width:2px,color:#FFF
    classDef condition fill:#FF9800,stroke:#F57C00,stroke-width:2px,color:#FFF
    classDef wa fill:#25D366,stroke:#128C7E,stroke-width:2px,color:#FFF

    User((Mulai)):::startEnd --> Auth{Punya Akun?}:::condition
    
    %% Authentication
    Auth -- Belum --> Reg[Register / Google Login]:::action
    Auth -- Sudah --> Log[Login Akun]:::action
    Reg --> Log
    
    Log --> Dash[🏠 Dashboard Utama]:::startEnd
    
    %% Alur 1: Tugas Pribadi
    Dash -->|Tab Tasks| Personal[Manajemen Tugas Pribadi]:::action
    Personal --> AddAI[💬 Tambah via AI Chat Assistant]:::ai
    Personal --> AddMan[📝 Tambah Manual]:::action
    Personal --> Swipe[✨ Geser Slider untuk Selesai]:::action
    
    %% Alur 2: Grup & Kolaborasi
    Dash -->|Tab Friends| GroupFlow[Fitur Grup & Kolaborasi]:::action
    GroupFlow --> NewGroup[➕ Buat Grup Baru]:::action
    GroupFlow --> GroupChat[Masuk ke Chat Grup]:::action
    
    GroupChat -->|Admin Role| AssignTask[Bagi Tugas ke Anggota]:::action
    GroupChat -->|Anggota| Upload[Kirim Lampiran Bukti Progress]:::action
    
    AssignTask --> TaskMuncul[Tugas Muncul di Dashboard Anggota]:::action
    
    Upload --> AIEval[AI Evaluasi Bukti & Beri Skor %]:::ai
    AIEval --> AdminApprove[Admin Menekan Tombol Approve]:::action
    AdminApprove --> AutoComplete[Progress Grup Naik & Tugas Anggota Otomatis Selesai]:::action
    
    %% Alur 3: Notifikasi WhatsApp
    Dash -->|Tab Akun| Settings[Pengaturan & Profil]:::action
    Settings --> SetupWA[Masukkan Nomor WA]:::action
    SetupWA --> RequestOTP[Minta Kode OTP]:::action
    
    RequestOTP -.-> WABot[Bot WA Mengirim Pesan]:::wa
    WABot -.-> InputOTP[User Memasukkan OTP di Web]:::action
    InputOTP --> Verified[WhatsApp Terverifikasi!]:::action
    
    %% Cron Job
    Verified -.-> CronSystem((Background System<br>Cron Job Tiap 1 Menit)):::action
    CronSystem --> Check{Tugas < 24 Jam<br>atau Overdue?}:::condition
    Check -- Ya --> SendAlert[Kirim Peringatan Kritis ke WA User]:::wa
    Check -- Tidak --> Loop[Menunggu Pengecekan Berikutnya]:::action
```

---

### 📖 Penjelasan Detail Tiap Fase

#### 1. Autentikasi (Pintu Masuk)
Pengguna baru dapat mendaftar menggunakan Email/Password atau *One-Click Login* menggunakan akun Google. Jika sudah memiliki sesi (*token* tersimpan), pengguna akan langsung diarahkan ke Dashboard.

#### 2. Manajemen Tugas Pribadi (Personal Mode)
Ini adalah fungsi utama (inti) dari Tuntaz. 
- Pengguna bisa berbicara dengan AI Assistant (menggunakan Gemini API) di panel kanan untuk mendiktekan tugas-tugasnya. AI akan mengekstrak judul, tingkat kesulitan, dan *deadline*, lalu memasukkannya ke daftar tugas secara otomatis.
- Setelah selesai mengerjakan, pengguna cukup membuka kartu tugas dan menggeser *slider* (seperti membuka kunci layar) untuk menandai tugas tersebut sebagai selesai.

#### 3. Manajemen Tim / Kelompok (Group Mode)
Fokus pada tugas kolaboratif dan transparansi:
- **Admin Grup** dapat membagikan tugas spesifik ke anggota tertentu.
- Tugas tersebut akan muncul di *Dashboard* si anggota dengan lencana khusus (ikon grup warna biru). Anggota **tidak bisa** menyelesaikan tugas ini menggunakan *slider* biasa.
- **Workflow Penyelesaian:** 
  1. Anggota masuk ke Chat Grup dan mengunggah foto laporan kerja (*Upload Attachment*).
  2. AI akan menganalisis foto tersebut dan memberikan perkiraan *progress* (misalnya: "+25%").
  3. Admin mengeklik tombol **Approve**.
  4. Secara otomatis, sistem memperbarui *progress* keseluruhan grup DAN mencoret status tugas anggota tersebut menjadi "Selesai" di *dashboard* pribadinya.

#### 4. Notifikasi Bot WhatsApp
Sistem anti-lupa yang bertindak layaknya asisten proaktif:
- Pengguna mengatur nomor WhatsApp di menu Profil, lalu memvalidasinya menggunakan OTP.
- Sistem di *backend* (Node.js + `whatsapp-web.js`) terus berjalan di belakang layar. Setiap 1 menit, AI mengecek jika ada tugas (baik pribadi maupun kelompok) yang memiliki tenggat waktu kurang dari 24 jam.
- Jika ditemukan tugas kritis, bot WhatsApp akan segera mengirimkan *chat* peringatan (*Alert*) langsung ke HP pengguna agar tidak ketiduran.
