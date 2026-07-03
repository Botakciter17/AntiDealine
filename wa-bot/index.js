const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const sqlite3 = require('sqlite3').verbose();
const express = require('express');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

const dbPath = '../backend/data/tuntaz.db';
const db = new sqlite3.Database(dbPath);

const client = new Client({
    authStrategy: new LocalAuth(),
    puppeteer: { 
        executablePath: '/usr/bin/google-chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] 
    }
});

let isReady = false;

client.on('qr', (qr) => {
    console.log('\n--- SCAN QR CODE INI MENGGUNAKAN WHATSAPP ANDA ---');
    qrcode.generate(qr, { small: true });
    console.log('--------------------------------------------------\n');
});

client.on('ready', () => {
    console.log('✅ WhatsApp Bot Tuntaz siap digunakan!');
    isReady = true;
});

client.initialize();

// API for Backend/Frontend to request OTP
app.post('/send-otp', async (req, res) => {
    const { number, user_id } = req.body;
    if (!isReady) return res.status(500).json({ error: 'WhatsApp Bot belum siap' });
    if (!number || !user_id) return res.status(400).json({ error: 'Parameter tidak lengkap' });

    // Generate 6 digit OTP
    const otp = Math.floor(100000 + Math.random() * 900000).toString();

    // Clean number (e.g. 0812 -> 62812)
    let formattedNumber = number.replace(/\D/g, '');
    if (formattedNumber.startsWith('0')) formattedNumber = '62' + formattedNumber.substring(1);
    if (!formattedNumber.endsWith('@c.us')) formattedNumber += '@c.us';

    try {
        db.run('UPDATE users SET whatsapp_number = ?, whatsapp_otp = ? WHERE id = ?', [number, otp, user_id], async (err) => {
            if (err) return res.status(500).json({ error: 'Database error' });
            
            try {
                const message = `Halo! Ini adalah kode verifikasi Tuntaz Anda: *${otp}*.\n\nJangan berikan kode ini kepada siapapun.`;
                const numberId = await client.getNumberId(formattedNumber);
                if (numberId) {
                    await client.sendMessage(numberId._serialized, message);
                    res.json({ success: true, message: 'OTP terkirim' });
                } else {
                    res.status(400).json({ error: 'Nomor tidak terdaftar di WhatsApp' });
                }
            } catch (errSend) {
                console.error('Send message error:', errSend.message);
                res.status(500).json({ error: 'Gagal mengirim pesan WA: ' + errSend.message });
            }
        });
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

// Verify OTP
app.post('/verify-otp', (req, res) => {
    const { user_id, otp } = req.body;
    if (!user_id || !otp) return res.status(400).json({ error: 'Parameter tidak lengkap' });

    db.get('SELECT whatsapp_otp FROM users WHERE id = ?', [user_id], (err, row) => {
        if (err) return res.status(500).json({ error: 'Database error' });
        if (!row || row.whatsapp_otp !== otp) return res.status(400).json({ error: 'Kode OTP salah' });

        db.run('UPDATE users SET whatsapp_verified = 1, whatsapp_otp = NULL WHERE id = ?', [user_id], (err) => {
            if (err) return res.status(500).json({ error: 'Database error' });
            res.json({ success: true, message: 'WhatsApp berhasil diverifikasi!' });
        });
    });
});

// Cron job to check critical tasks
setInterval(() => {
    if (!isReady) return;
    
    const now = new Date();
    // find tasks deadline within 24h or overdue, not completed, and not notified
    const query = `
        SELECT t.*, u.whatsapp_number, u.username
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        WHERE u.whatsapp_verified = 1 
          AND t.whatsapp_notified = 0 
          AND t.completed = 0
    `;
    
    db.all(query, [], async (err, rows) => {
        if (err) return console.error(err);
        
        for (const task of rows) {
            const deadline = new Date(task.deadline);
            const hoursLeft = (deadline - now) / 3600000;
            
            // If deadline is within 24 hours or already overdue
            if (hoursLeft <= 24) {
                let formattedNumber = task.whatsapp_number.replace(/\D/g, '');
                if (formattedNumber.startsWith('0')) formattedNumber = '62' + formattedNumber.substring(1);
                if (!formattedNumber.endsWith('@c.us')) formattedNumber += '@c.us';
                
                let timeMsg = hoursLeft < 0 ? 'SUDAH OVERDUE 🚨' : `Tinggal ${Math.max(1, Math.round(hoursLeft))} jam lagi! ⏳`;
                
                const msg = `Halo ${task.username}! 🤖\nAI Tuntaz mengingatkan bahwa tugasmu sangat kritis:\n\n*${task.title}*\n⏰ ${timeMsg}\n\nJangan ditunda lagi, segera selesaikan! 💪`;
                
                try {
                    const numberId = await client.getNumberId(formattedNumber);
                    if (numberId) {
                        await client.sendMessage(numberId._serialized, msg);
                        db.run('UPDATE tasks SET whatsapp_notified = 1 WHERE id = ?', [task.id]);
                        console.log(`Sent reminder to ${task.username} for task ${task.id}`);
                    } else {
                        console.error(`Nomor WA tidak valid untuk user ${task.username}`);
                    }
                } catch (e) {
                    console.error('Failed to send WA:', e.message);
                }
            }
        }
    });
}, 60000); // Check every 1 minute

app.listen(3001, () => {
    console.log('WhatsApp Bot API berjalan di port 3001');
});
