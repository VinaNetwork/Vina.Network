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
      if (!data.success && data.message) {
        statusBox.innerHTML = `<p>❌ ${data.message}</p>`;
        return;
      }

      const results = data.results || [];
      if (results.length === 0) {
        statusBox.innerHTML = `<p>⚠️ Không có tiến trình nào được thực hiện.</p>`;
        return;
      }

      // Bắt đầu dựng bảng hiển thị tiến trình
      let html = `
        <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%;">
          <thead style="background: #f3f3f3;">
            <tr>
              <th style="text-align:left;">📌 Tên tiến trình</th>
              <th style="text-align:left;">🔑 Ví</th>
              <th style="text-align:left;">📊 Trạng thái</th>
            </tr>
          </thead>
          <tbody>`;

      results.forEach((process, index) => {
        const status = process.error ? '⛔ Đã dừng' : '✅ Hoạt động';
        const processName = process.name || `(Tiến trình ${index + 1})`;

        html += `
          <tr style="cursor:pointer;" onclick="toggleProcessDetail('detail-${index}')">
            <td><strong>${processName}</strong></td>
            <td>${process.wallet}</td>
            <td>${status}</td>
          </tr>
          <tr id="detail-${index}" style="display:none;">
            <td colspan="3">
              <ul style="margin: 0.5em 1em;">`;

        if (process.rounds && process.rounds.length > 0) {
          process.rounds.forEach(round => {
            html += `<li>🌀 Vòng ${round.round}: `;
            if (round.error) {
              html += `❌ ${round.error}`;
            } else {
              html += `🛒 <a href="https://solscan.io/tx/${round.buyTx}" target="_blank">Mua</a> – 
                       💸 <a href="https://solscan.io/tx/${round.sellTx}" target="_blank">Bán</a>`;
            }
            html += `</li>`;
          });
        } else {
          html += `<li>⚠️ Không có vòng nào được thực hiện.</li>`;
        }

        html += `</ul></td></tr>`;
      });

      html += `</tbody></table>`;
      statusBox.innerHTML = html;
    })
    .catch(err => {
      statusBox.innerHTML = `<p>❌ Lỗi kết nối hoặc hệ thống: ${err.message}</p>`;
    });
});

// Toggle hiển thị chi tiết tiến trình
function toggleProcessDetail(id) {
  const row = document.getElementById(id);
  if (row) {
    row.style.display = (row.style.display === 'none') ? '' : 'none';
  }
}
