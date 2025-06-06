// public_html/tools/tools.js
document.addEventListener('DOMContentLoaded', () => {
    // Lấy tool từ URL
    const urlParams = new URLSearchParams(window.location.search);
    const tool = urlParams.get('tool');
    const tabsContainer = document.querySelector('.tools-tabs');
    let activeTab = document.querySelector('.tab-link.active');

    // Nếu không có tab active (truy cập trực tiếp), kích hoạt tab dựa trên URL
    if (!activeTab && tool) {
        activeTab = document.querySelector(`.tab-link[data-tool="${tool}"]`);
        if (activeTab) {
            activeTab.classList.add('active');
        } else {
            console.error(`No tab found for tool: ${tool}`);
        }
    }

    // Thực hiện cuộn nếu có tabsContainer và activeTab
    if (tabsContainer && activeTab) {
        // Đợi một chút để đảm bảo DOM ổn định
        setTimeout(() => {
            const tabRect = activeTab.getBoundingClientRect();
            const containerRect = tabsContainer.getBoundingClientRect();
            tabsContainer.scrollTo({
                left: activeTab.offsetLeft - (containerRect.width - tabRect.width) / 2,
                behavior: 'smooth'
            });
        }, 100); // Đợi 100ms để DOM hoàn toàn sẵn sàng
    }

    // Xử lý chuyển tab bằng AJAX
    document.querySelectorAll('.tab-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            // Cập nhật trạng thái active của tab
            document.querySelectorAll('.tab-link').forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');

            // Cuộn đến tab vừa click
            if (tabsContainer) {
                const tabRect = this.getBoundingClientRect();
                const containerRect = tabsContainer.getBoundingClientRect();
                tabsContainer.scrollTo({
                    left: this.offsetLeft - (containerRect.width - tabRect.width) / 2,
                    behavior: 'smooth'
                });
            }

            // Lấy giá trị tool từ data-tool
            const tool = this.getAttribute('data-tool');
            
            // Cập nhật URL mà không làm mới trang
            history.pushState({}, '', `?tool=${encodeURIComponent(tool)}`);

            // Tải nội dung mới qua AJAX
            fetch(`/tools/load-tool.php?tool=${encodeURIComponent(tool)}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.text();
            })
            .then(data => {
                document.querySelector('.tool-content').innerHTML = data;
            })
            .catch(error => {
                console.error('Error loading tool content:', error);
                document.querySelector('.tool-content').innerHTML = '<p>Error loading content. Please try again.</p>';
            });
        });
    });

    // Xử lý submit form
    document.addEventListener('submit', (e) => {
        if (e.target.matches('.export-form')) {
            const exportType = e.target.querySelector('[name="export_type"]').value;
            if (exportType === 'all') {
                const progressContainer = document.querySelector('.progress-container');
                const progressBarFill = document.querySelector('.progress-bar-fill');
                if (progressContainer && progressBarFill) {
                    progressContainer.style.display = 'block';
                    let progress = 0;
                    const interval = setInterval(() => {
                        progress += 10;
                        progressBarFill.style.width = `${progress}%`;
                        if (progress >= 100) {
                            clearInterval(interval);
                            setTimeout(() => {
                                progressContainer.style.display = 'none';
                                progressBarFill.style.width = '0%';
                            }, 1000);
                        }
                    }, 500);
                }
            }
            return;
        }
        if (e.target.matches('#nftHoldersForm, #nftValuationForm, .transaction-form, #walletAnalysisForm')) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const tool = document.querySelector('.tab-link.active').getAttribute('data-tool');
            fetch(`/tools/load-tool.php?tool=${encodeURIComponent(tool)}`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.text();
            })
            .then(data => {
                document.querySelector('.tool-content').innerHTML = data;
            })
            .catch(error => {
                console.error('Error submitting form:', error);
                document.querySelector('.tool-content').innerHTML = '<p>Error submitting form. Please try again.</p>';
            });
        }
    });
});
