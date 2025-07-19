// ============================================================================
// File: make-market/mm.js
// Description:
// Created by: Vina Network
// ============================================================================

// Xử lý gửi form và hiển thị tiến trình
document.getElementById('makeMarketForm').addEventListener('submit', async function (e) {
  e.preventDefault();

  const form = e.target;
  const formData = new FormData(form);
  const privateKey = formData.get('privateKey');
  const statusBox = document.getElementById('mm-status');
  statusBox.innerHTML = '<p>⏳ Đang thực hiện Make Market...</p>';

  try {
    // Lấy SECRET_KEY từ server (giả định API bảo mật)
    const keyResponse = await fetch('/api/get-encryption-key', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer your-auth-token' } // Thay bằng token xác thực thực tế
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
      if (data.error) {
        // Hiển thị lỗi cụ thể
        let errorMessage = data.error;
        if (data.error.includes('Slippage quá cao')) {
          errorMessage = '⚠️ Giao dịch thất bại do trượt giá vượt quá mức cho phép';
        } else if (data.error.includes('Không đủ thanh khoản')) {
          errorMessage = '⚠️ Giao dịch thất bại do pool không đủ thanh khoản';
        }
        html += `<p>❌ Lỗi: ${errorMessage}</p>`;
      }
    }
    statusBox.innerHTML = html;
  } catch (err) {
    statusBox.innerHTML = `<p>❌ Lỗi kết nối hoặc hệ thống: ${err.message}</p>`;
  }
});
