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
        statusBox.innerHTML = `
          <p>✅ Mua thành công: <a href="https://solscan.io/tx/${data.buyTx}" target="_blank">${data.buyTx}</a></p>
          <p>✅ Bán thành công: <a href="https://solscan.io/tx/${data.sellTx}" target="_blank">${data.sellTx}</a></p>
        `;
      } else {
        statusBox.innerHTML = `<p>❌ Lỗi: ${data.error}</p>`;
      }
    })
    .catch(err => {
      statusBox.innerHTML = `<p>❌ Lỗi kết nối hoặc hệ thống: ${err.message}</p>`;
    });
});
