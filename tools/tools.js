// tools.js
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tool = urlParams.get('tool');
    const tabsContainer = document.querySelector('.tools-tabs');
    let activeTab = document.querySelector('.tab-link.active');

    if (!activeTab && tool) {
        activeTab = document.querySelector(`.tab-link[data-tool="${tool}"]`);
        if (activeTab) {
            activeTab.classList.add('active');
        } else {
            console.error(`No tab found for tool: ${tool}`);
        }
    }

    if (tabsContainer && activeTab) {
        setTimeout(() => {
            const tabRect = activeTab.getBoundingClientRect();
            const containerRect = tabsContainer.getBoundingClientRect();
            tabsContainer.scrollTo({
                left: activeTab.offsetLeft - (containerRect.width - tabRect.width) / 2,
                behavior: 'smooth'
            });
        }, 100);
    }

    document.querySelectorAll('.tab-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.tab-link').forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');

            if (tabsContainer) {
                const tabRect = this.getBoundingClientRect();
                const containerRect = tabsContainer.getBoundingClientRect();
                tabsContainer.scrollTo({
                    left: this.offsetLeft - (containerRect.width - tabRect.width) / 2,
                    behavior: 'smooth'
                });
            }

            const tool = this.getAttribute('data-tool');
            history.pushState({}, '', `?tool=${encodeURIComponent(tool)}`);

            fetch(`/tools/load-tool.php?tool=${encodeURIComponent(tool)}`, {
                method: 'GET',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.text();
            })
            .then(data => document.querySelector('.tool-content').innerHTML = data)
            .catch(error => {
                console.error('Error loading tool content:', error);
                document.querySelector('.tool-content').innerHTML = '<p>Error loading content. Please try again.</p>';
            });
        });
    });

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
            const form = e.target;
            const loader = document.querySelector('.loader'); // Tìm loader trong toàn bộ DOM
            console.log('Loader element:', loader); // Debug
            if (loader) {
                loader.style.display = 'block'; // Hiển thị loader
                console.log('Loader activated');
            } else {
                console.error('Loader not found in DOM');
            }

            const formData = new FormData(form);
            const tool = document.querySelector('.tab-link.active').getAttribute('data-tool');
            fetch(`/tools/load-tool.php?tool=${encodeURIComponent(tool)}`, {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.text();
            })
            .then(data => {
                document.querySelector('.tool-content').innerHTML = data;
                if (loader) {
                    loader.style.display = 'none'; // Ẩn loader sau khi load xong
                    console.log('Loader deactivated');
                }
            })
            .catch(error => {
                console.error('Error submitting form:', error);
                document.querySelector('.tool-content').innerHTML = '<p>Error submitting form. Please try again.</p>';
                if (loader) {
                    loader.style.display = 'none'; // Ẩn loader nếu có lỗi
                }
            });
        }
    });
});

// Chuyển trang chỉ tải phần nội dung
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('page-link')) {
        e.preventDefault();
        const page = e.target.getAttribute('data-page');
        // Đảm bảo lấy đúng tool hiện tại
        const tool = document.querySelector('.tab-link.active').getAttribute('data-tool') || 'nft-holders';
        // Hiển thị loading nếu muốn
        const holdersList = document.getElementById('holders-list');
        holdersList.innerHTML = '<div class="loader">Loading...</div>';
        fetch(`/tools/nft-holders.php?tool=${encodeURIComponent(tool)}&page=${page}`, {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(res => res.text())
        .then(html => {
            holdersList.innerHTML = html;
        })
        .catch(() => {
            holdersList.innerHTML = '<p>Error loading page. Please try again.</p>';
        });
    }
});
