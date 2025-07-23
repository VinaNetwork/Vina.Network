// File: accounts/auth.js
const express = require('express');
const mysql = require('mysql2/promise');
const bs58 = require('bs58');
const nacl = require('tweetnacl');
const app = express();
const winston = require('winston');

// Cấu hình logger
const logger = winston.createLogger({
    level: 'info',
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.printf(({ timestamp, level, message }) => {
            return `${timestamp} [${level}]: ${message}`;
        })
    ),
    transports: [
        new winston.transports.File({ filename: '../logs/accounts/accounts.log' })
    ]
});

// Cấu hình database (lấy từ config.php)
const dbConfig = {
    host: 'localhost',
    user: 'vina_user',
    password: 'Nguyen@142462212',
    database: 'vina'
};

app.use(express.json());

// API xác minh chữ ký
app.post('/', async (req, res) => {
    const { publicKey, signature, message } = req.body;

    if (!publicKey || !signature || !message) {
        logger.error('Invalid request data');
        return res.status(400).json({ success: false, message: 'Invalid request data' });
    }

    try {
        // Giải mã publicKey và signature
        const publicKeyBytes = bs58.decode(publicKey);
        const signatureBytes = Buffer.from(signature, 'base64');
        const messageBytes = new TextEncoder().encode(message);

        // Xác minh chữ ký
        const isValid = nacl.sign.detached.verify(messageBytes, signatureBytes, publicKeyBytes);

        if (!isValid) {
            logger.error(`Invalid signature for publicKey: ${publicKey}`);
            return res.status(401).json({ success: false, message: 'Invalid signature' });
        }

        // Kết nối database
        const connection = await mysql.createConnection(dbConfig);

        // Kiểm tra ví đã đăng ký chưa
        const [rows] = await connection.execute('SELECT * FROM accounts WHERE public_key = ?', [publicKey]);

        if (rows.length === 0) {
            // Đăng ký tài khoản mới
            await connection.execute(
                'INSERT INTO accounts (public_key, created_at, last_login) VALUES (?, NOW(), NOW())',
                [publicKey]
            );
            logger.info(`New account registered: ${publicKey}`);
        } else {
            // Cập nhật last_login
            await connection.execute(
                'UPDATE accounts SET last_login = NOW() WHERE public_key = ?',
                [publicKey]
            );
            logger.info(`User logged in: ${publicKey}`);
        }

        await connection.end();

        // Lưu publicKey vào session (trả về client để PHP xử lý session)
        res.json({ success: true, publicKey: publicKey });
    } catch (error) {
        logger.error(`Error processing request: ${error.message}`);
        res.status(500).json({ success: false, message: error.message });
    }
});

// Khởi động server
app.listen(3000, () => {
    logger.info('Auth server running on port 3000');
});
