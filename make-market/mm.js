// ============================================================================
// File: make-market/mm.js
// Description: Client-side JavaScript for Make Market functionality
// Created by: Vina Network
// ============================================================================

const statusBox = document.getElementById('statusBox');
const loginForm = document.getElementById('loginForm');
const makeMarketForm = document.getElementById('makeMarketForm');

const token = localStorage.getItem('jwtToken');
if (token) {
    loginForm.style.display = 'none';
    makeMarketForm.style.display = 'block';
}

const ws = new WebSocket('ws://your_server_ip:8080');
ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    statusBox.innerHTML += `<p>${data.status}</p>`;
};

loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(loginForm);
    try {
        const response = await fetch('/accounts/login.php', { // Cập nhật từ /make-market/accounts/login.php
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.token) {
            localStorage.setItem('jwtToken', data.token);
            loginForm.style.display = 'none';
            makeMarketForm.style.display = 'block';
            statusBox.innerHTML = '<p>✅ Đăng nhập thành công</p>';
        } else {
            statusBox.innerHTML = `<p>⛔ ${data.error}</p>`;
        }
    } catch (error) {
        statusBox.innerHTML = `<p>⛔ Lỗi: ${error.message}</p>`;
    }
});

makeMarketForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(makeMarketForm);
    formData.append('processName', 'process_' + Date.now());
    
    const token = localStorage.getItem('jwtToken');
    try {
        const response = await fetch('/make-market/mm-api.php', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            statusBox.innerHTML += `<p>${data.message}</p>`;
            data.results.forEach(result => {
                statusBox.innerHTML += `<p>Vòng ${result.round}: Mua ${result.buyTx}, Bán ${result.sellTx}</p>`;
            });
        } else {
            statusBox.innerHTML += `<p>${data.message}</p>`;
        }
    } catch (error) {
        statusBox.innerHTML += `<p>⛔ Lỗi: ${error.message}</p>`;
    }
});
