// ============================================================================
// File: make-market/mm.js
// Description: Client-side script for Make Market with WebSocket status updates
// Created by: Vina Network
// ============================================================================

// Xử lý gửi form và hiển thị tiến trình
document.getElementById('makeMarketForm').addEventListener('submit', async function (e) {
  e.preventDefault();

  const form = e.target;
  const formData = new FormData(form);
  const privateKey = formData.get('privateKey');
  const processId = formData.get('processName');
  const statusBox = document.getElementById('mm-status');
  statusBox.innerHTML = '<p>⏳ Đang kết nối và thực hiện Make Market...</p>';

  // Kết nối WebSocket
  const ws = new WebSocket('ws://vina.network:8080'); // Thay bằng IP/domain server
  ws.onopen = () => {
    ws.send(JSON.stringify({ processId }));
    statusBox.innerHTML = '<p>🔗 Đã kết nối WebSocket, đang chờ trạng thái...</p>';
  };
  ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    const status = data.status || 'Không có trạng thái';
    statusBox.innerHTML += `<p>${status}</p>`;
    statusBox.scrollTop = statusBox.scrollHeight; // Cuộn xuống dòng mới nhất
  };
  ws.onerror = (error) => {
    statusBox.innerHTML += `<p>❌ Lỗi WebSocket: ${error.message}</p>`;
  };
  ws.onclose = () => {
    statusBox.innerHTML += '<p>🔌 WebSocket đã đóng</p>';
  };

  try {
    // Lấy SECRET_KEY từ server
    const keyResponse = await fetch('/api/get-encryption-key', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer your-auth-token' } // Thay bằng token thực tế
    });
    const { secretKey } = await keyResponse.json();
    if (!secretKey) throw new Error('Không lấy được khóa mã hóa');

    // Tạo IV ngẫu nhiên
    const iv = CryptoJS.lib.WordArray.random(16);
    const encryptedPrivateKey = CryptoJS.AES.encrypt(privateKey, secretKey, { iv: iv }).toString();

    // Gửi dữ liệu mã hóa và IV
    formData.set('privateKey', encryptedPrivateKey);
    formData.append('iv', iv.toString(CryptoJS.enc.Base64));

    const response = await fetch('mm-api.php', {
      method: 'POST',
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
      }
      html += `<p>❌ Lỗi: ${errorMessage}</p>`;
    }
    statusBox.innerHTML += html;
    ws.close(); // Đóng WebSocket sau khi hoàn tất
  } catch (err) {
    statusBox.innerHTML += `<p>❌ Lỗi kết nối hoặc hệ thống: ${err.message}</p>`;
    ws.close();
  }
});
