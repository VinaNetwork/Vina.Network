// Make Market – xử lý gửi form và hiển thị tiến trình

document.getElementById('makeMarketForm').addEventListener('submit', function (e) {
  e.preventDefault();

  const form = e.target;
  const formData = new FormData(form);
  const statusBox = document.getElementById('mm-status');
  statusBox.innerHTML = "<p>⏳ Đang thực hiện Make Market...</p>";

  fetch('mm-api.php', {
    method: 'POST',
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      let html = '';

      // ✅ Hiển thị message ở dòng đầu nếu có
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
            html += `🛒 <a href="https://solscan.io/tx/${round.buyTx}" target="_blank">Mua</a> – 
                     💸 <a href="https://solscan.io/tx/${round.sellTx}" target="_blank">Bán</a>`;
          }
          html += `</li>`;
        });
        html += '</ul>';
      } else if (!data.success) {
        // ✅ Nếu có message thì đã hiện rồi, bổ sung lỗi cụ thể (nếu có)
        if (data.error) {
          html += `<p>❌ Lỗi: ${data.error}</p>`;
        }
      }

      statusBox.innerHTML = html;
    })
    .catch(err => {
      statusBox.innerHTML = `<p>❌ Lỗi kết nối hoặc hệ thống: ${err.message}</p>`;
    });
});
