// public_html/tools/tools.js
document.addEventListener('DOMContentLoaded', () => {
    // Không cần đặt lại active tab, vì PHP đã xử lý đúng trong index.php
    // Chỉ cuộn đến tab active trên di động
    const tabsContainer = document.querySelector('.tools-tabs');
    const activeTab = document.querySelector('.tab-link.active');
    if (tabsContainer && activeTab) {
        const tabRect = activeTab.getBoundingClientRect();
        const containerRect = tabsContainer.getBoundingClientRect();
        tabsContainer.scrollTo({
            left: activeTab.offsetLeft - (containerRect.width - tabRect.width) / 2,
            behavior: 'smooth' // Sửa từ 'reload' thành 'smooth'
        });
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
});

// Xử lý submit form bằng AJAX
document.addEventListener('submit', (e) => {
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
