// ============================================================================
// File: make-market/mm.js
// Description: Client-side script for Make Market with WebSocket status updates and JWT authentication
// Created by: Vina Network
// ============================================================================

// Kiểm tra token khi tải trang
document.addEventListener('DOMContentLoaded', () => {
  const token = localStorage.getItem('jwtToken');
  const loginForm = document.getElementById('loginForm');
  const makeMarketForm = document.getElementById('makeMarketForm');
  const statusBox = document.getElementById('mm-status');

  if (token) {
    loginForm.style.display = 'none';
    makeMarketForm.style.display = 'block';
  } else {
    loginForm.style.display = 'block';
    makeMarketForm.style.display = 'none';
  }

  // Xử lý đăng nhập
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(loginForm);
    try {
      const response = await fetch('/accounts/login.php', {
        method: 'POST',
        body: formData
      });
      const data = await response.json();
      if (data.token) {
        localStorage.setItem('jwtToken', data.token);
        loginForm.style.display = 'none';
        makeMarketForm.style.display = 'block';
        statusBox.innerHTML = '<p>✅ Đăng nhập thành công!</p>';
      } else {
        statusBox.innerHTML = `<p>❌ Lỗi đăng nhập: ${data.error}</p>`;
      }
    } catch (err) {
      statusBox.innerHTML = `<p>❌ Lỗi kết nối: ${err.message}</p>`;
    }
  });
});

// Xử lý gửi form Make Market
document.getElementById('makeMarketForm').addEventListener('submit', async function (e) {
  e.preventDefault();

  const form = e.target;
  const formData = new FormData(form);
  const privateKey = formData.get('privateKey');
  const processId = formData.get('processName');
  const statusBox = document.getElementById('mm-status');
  statusBox.innerHTML = '<p>⏳ Đang kết nối và thực hiện Make Market...</p>';

  // Lấy token từ localStorage
  const token = localStorage.getItem('jwtToken');
  if (!token) {
    statusBox.innerHTML += '<p>❌ Lỗi: Vui lòng đăng nhập để lấy token</p>';
    return;
  }

  // Kết nối WebSocket
  const ws = new WebSocket('<?php echo $_ENV['WEBSOCKET_URL'] ?? 'ws://your_server_ip:8080'; ?>');
  ws.onopen = () => {
    ws.send(JSON.stringify({ processId }));
    statusBox.innerHTML = '<p>🔗 Đã kết nối WebSocket, đang chờ trạng thái...</p>';
  };
  ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    const status = data.status || 'Không có trạng thái';
    statusBox.innerHTML += `<p>${status}</p>`;
    statusBox.scrollTop = statusBox.scrollHeight;
  };
  ws.onerror = (error) => {
    statusBox.innerHTML += `<p>❌ Lỗi WebSocket: ${error.message}</p>`;
  };
  ws.onclose = () => {
    statusBox.innerHTML += '<p>🔌 WebSocket đã đóng</p>';
  };

  try {
    // Lấy SECRET_KEY từ server
    const keyResponse = await fetch('/api/get-encryption-key.php', {
      method: 'POST',
      headers: { 
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
    const keyData = await keyResponse.json();
    if (!keyData.secretKey) {
      throw new Error(keyData.error || 'Không lấy được khóa mã hóa');
    }

    // Tạo IV ngẫu nhiên
    const iv = CryptoJS.lib.WordArray.random(16);
    const encryptedPrivateKey = CryptoJS.AES.encrypt(privateKey, keyData.secretKey, { iv: iv }).toString();

    // Gửi dữ liệu mã hóa và IV
    formData.set('privateKey', encryptedPrivateKey);
    formData.append('iv', iv.toString(CryptoJS.enc.Base64));

    const response = await fetch('mm-api.php', {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` },
      body: formData
    });
    const data = await response.json();

    let html = '';
    if (data.message) {
      html += `<p><strong>${data.message}</strong></p>`;
    }
    if (data.success && Array.isArray(data.results)) {
      html += `<p>✅ Đã xử lý ${data.results.length} vòng Make Market:</p><ul>`;
      data.results.forEach(round => {
        html += `<li>🌀 Vòng ${round.round}: `;
        if (round.error) {
          html += `❌ ${round.error}`;
        } else {
          html += `🛒 <a href="https://solscan.io/tx/${round.buyTx}" target="_blank">Mua (Đã xác nhận)</a> – 
                   💸 <a href="https://solscan.io/tx/${round.sellTx}" target="_blank">Bán (Đã xác nhận)</a>`;
        }
        html += `</li>`;
      });
      html += '</ul>';
    } else if (!data.success) {
      let errorMessage = data.error;
      if (data.error.includes('Slippage quá cao')) {
        errorMessage = '⚠️ Giao dịch thất bại do trượt giá vượt quá mức cho phép';
      } else if (data.error.includes('Không đủ thanh khoản')) {
        errorMessage = '⚠️ Giao dịch thất bại do pool không đủ thanh khoản';
      } else if (data.error.includes('Token không hợp lệ')) {
        errorMessage = '⚠️ Phiên đăng nhập hết hạn, vui lòng đăng nhập lại';
      }
      html += `<p>❌ Lỗi: ${errorMessage}</p>`;
    }
    statusBox.innerHTML += html;
    ws.close();
  } catch (err) {
    statusBox.innerHTML += `<p>❌ Lỗi kết nối hoặc hệ thống: ${err.message}</p>`;
    ws.close();
  }
});
