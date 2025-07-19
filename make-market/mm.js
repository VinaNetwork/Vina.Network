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
      if (data.success) {
        let html = `<p>✅ Đã hoàn thành ${data.results.length} vòng Make Market:</p><ul>`;
        data.results.forEach(round => {
          html += `<li>🌀 Vòng ${round.round}: 
            🛒 <a href="https://solscan.io/tx/${round.buyTx}" target="_blank">Mua</a> – 
            💸 <a href="https://solscan.io/tx/${round.sellTx}" target="_blank">Bán</a></li>`;
        });
        html += '</ul>';
        statusBox.innerHTML = html;
      } else {
        statusBox.innerHTML = `<p>❌ Lỗi: ${data.error}</p>`;
      }
    })
    .catch(err => {
      statusBox.innerHTML = `<p>❌ Lỗi kết nối hoặc hệ thống: ${err.message}</p>`;
    });
});
