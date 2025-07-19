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

      // Hiển thị danh sách tiến trình
      let html = `
        <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
          <thead>
            <tr><th>Tên tiến trình</th><th>Ví</th><th>Trạng thái</th></tr>
          </thead><tbody>`;

      data.results.forEach((process, index) => {
        const status = process.error ? '⛔ Đã dừng' : '✅ Hoạt động';
        html += `
          <tr style="cursor: pointer;" onclick="toggleProcessDetail('detail-${index}')">
            <td><strong>${process.name}</strong></td>
            <td>${process.wallet}</td>
            <td>${status}</td>
          </tr>
          <tr id="detail-${index}" style="display: none;"><td colspan="3">
            <ul>`;
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
        html += `</ul></td></tr>`;
      });

      html += `</tbody></table>`;
      statusBox.innerHTML = html;
    })
    .catch(err => {
      statusBox.innerHTML = `<p>❌ Lỗi kết nối hoặc hệ thống: ${err.message}</p>`;
    });
});

// Toggle chi tiết
function toggleProcessDetail(id) {
  const row = document.getElementById(id);
  if (row.style.display === 'none') {
    row.style.display = '';
  } else {
    row.style.display = 'none';
  }
}
