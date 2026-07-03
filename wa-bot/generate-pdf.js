const puppeteer = require('puppeteer-core');
const fs = require('fs');

const mermaidCode = `
graph TD
    classDef root fill:#008BFF,stroke:#005BBB,color:#fff,font-weight:bold,stroke-width:3px
    classDef auth fill:#FF9800,stroke:#F57C00,color:#fff,stroke-width:2px
    classDef tab fill:#2D313A,stroke:#3E4451,color:#fff,stroke-width:2px,font-weight:bold
    classDef screen fill:#1E2028,stroke:#008BFF,color:#fff,stroke-width:2px
    classDef leaf fill:#181A20,stroke:#4C566A,color:#A3BE8C,stroke-dasharray: 5 5
    
    App((AntiDeadline<br>App)):::root --> Auth[Halaman Autentikasi]:::auth
    Auth --> Login[Form Login]:::screen
    Auth --> Register[Form Register]:::screen
    Auth --> SSO[Google SSO]:::screen
    
    App --> Main[Navigasi Utama<br>Bottom Bar]:::root
    
    Main --> T1[Tab 1:<br>Tasks / Home]:::tab
    T1 --> Card[Daftar Kartu Tugas]:::screen
    T1 --> Add[Modal Tambah Tugas Manual]:::screen
    Card --> Detail[Modal Detail Tugas]:::screen
    Detail --> Slider[Slider Kunci Selesai]:::leaf
    Detail --> Hapus[Hapus Tugas]:::leaf
    
    Main --> T2[Tab 2:<br>AI Chat]:::tab
    T2 --> Hist[Riwayat Chat AI]:::screen
    T2 --> Input[Panel Input]:::screen
    Input --> Teks[Input Teks]:::leaf
    Input --> Mic[Voice-to-Text Microphone]:::leaf
    
    Main --> T3[Tab 3:<br>Grup & Kolaborasi]:::tab
    T3 --> GList[Daftar Grup]:::screen
    T3 --> GAdd[Modal Buat Grup Baru]:::screen
    GList --> GDetail[Halaman Detail & Chat Grup]:::screen
    GDetail --> MList[Daftar Anggota Grup]:::leaf
    GDetail --> GAssign[Modal Admin: Bagi Tugas]:::leaf
    GDetail --> GUpload[Panel Laporan: Upload Attachment]:::leaf
    
    Main --> T4[Tab 4:<br>Akun & Profil]:::tab
    T4 --> PEdit[Edit Nama & Avatar]:::screen
    T4 --> API[Konfigurasi AI API Key]:::screen
    T4 --> WA[Sistem Peringatan WhatsApp]:::screen
    WA --> WAInput[Input Nomor WA & Validasi OTP]:::leaf
    T4 --> SysOpt[Pengaturan Sistem]:::screen
    SysOpt --> Theme[Toggle Dark/Light Mode]:::leaf
    SysOpt --> Lang[Ubah Bahasa Aplikasi]:::leaf
    T4 --> Logout[Tombol Keluar]:::screen
`;

const htmlContent = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
  <style>
    body { font-family: sans-serif; padding: 20px; background: #fff; text-align: center; }
    h1 { color: #333; margin-bottom: 30px; }
    .mermaid { display: flex; justify-content: center; }
  </style>
</head>
<body>
  <h1>Information Architecture - AntiDeadline</h1>
  <div class="mermaid">
    ${mermaidCode}
  </div>
  <script>
    mermaid.initialize({ startOnLoad: true, theme: 'default' });
  </script>
</body>
</html>
`;

(async () => {
  fs.writeFileSync('ia_temp.html', htmlContent);
  const browser = await puppeteer.launch({ 
    executablePath: '/usr/bin/google-chrome',
    args: ['--no-sandbox'] 
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1200, height: 1600 });
  await page.goto('file://' + __dirname + '/ia_temp.html', { waitUntil: 'networkidle0' });
  // Wait a little bit extra for mermaid to render
  await new Promise(r => setTimeout(r, 2000));
  await page.pdf({ path: '../IA_AntiDeadline.pdf', format: 'A3', printBackground: true, landscape: false });
  await browser.close();
  fs.unlinkSync('ia_temp.html');
  console.log('PDF Generated Successfully!');
})();
