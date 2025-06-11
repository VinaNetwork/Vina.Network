document.addEventListener('DOMContentLoaded', () => {
    // Lấy CSRF token từ PHP (được chèn vào DOM hoặc biến toàn cục)
    const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>';
    if (!csrfToken) {
        console.error('CSRF token not found. Ensure session is active and token is set.');
    } else {
        console.log('CSRF token initialized:', csrfToken);
    }

    // Lấy tool từ URL
    const urlParams = new URLSearchParams(window.location.search);
    const tool = urlParams.get('tool');
    const tabsContainer = document.querySelector('.t-3');
    let activeTab = document.querySelector('.t-link.active');

    // Kích hoạt tab dựa trên URL
    if (!activeTab && tool) {
        activeTab = document.querySelector(`.t-link[data-tool="${tool}"]`);
        if (activeTab) {
            activeTab.classList.add('active');
        } else {
            console.error(`No tab found for tool: ${tool}`);
        }
    }

    // Cuộn đến tab đang active
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

    // Xử lý click tab
    document.querySelectorAll('.t-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.t-link').forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');

            // Cuộn đến tab được click
            if (tabsContainer) {
                const tabRect = this.getBoundingClientRect();
                const containerRect = tabsContainer.getBoundingClientRect();
                tabsContainer.scrollTo({
                    left: this.offsetLeft - (containerRect.width - tabRect.width) / 2,
                    behavior: 'smooth'
                });
            }

            // Cập nhật URL
            const tool = this.getAttribute('data-tool');
            history.pushState({}, '', `?tool=${encodeURIComponent(tool)}`);

            // AJAX load nội dung tab
            fetch(`/tools/load-tool.php?tool=${encodeURIComponent(tool)}&csrf_token=${encodeURIComponent(csrfToken)}`, {
                method: 'GET',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.text();
            })
            .then(data => {
                document.querySelector('.t-4').innerHTML = data;
                console.log(`Loaded content for tool: ${tool}`);
            })
            .catch(error => {
                console.error('Error loading tool content:', error);
                document.querySelector('.t-4').innerHTML = '<p>Error loading content. Please try again.</p>';
            });
        });
    });

    // Xử lý submit form
    document.addEventListener('submit', e => {
        // Xử lý form export
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
            // Thêm CSRF token vào form data
            const formData = new FormData(e.target);
            formData.append('csrf_token', csrfToken);
            return;
        }

        // Xử lý form NFT tools
        if (e.target.matches('#nftHoldersForm, #nftValuationForm, .transaction-form, #walletAnalysisForm')) {
            e.preventDefault();
            const form = e.target;
            const loader = document.querySelector('.loader');
            console.log('Loader element:', loader);

            if (loader) {
                loader.style.display = 'block';
                console.log('Loader activated');
            } else {
                console.error('Loader not found in DOM');
            }

            const formData = new FormData(form);
            formData.append('csrf_token', csrfToken); // Thêm CSRF token
            const tool = document.querySelector('.t-link.active')?.getAttribute('data-tool');
            if (!tool) {
                console.error('No active tool found');
                if (loader) loader.style.display = 'none';
                return;
            }

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
                document.querySelector('.t-4').innerHTML = data;
                if (loader) {
                    loader.style.display = 'none';
                    console.log('Loader deactivated');
                }
                console.log(`Form submitted successfully for tool: ${tool}`);
            })
            .catch(error => {
                console.error('Error submitting form:', error);
                document.querySelector('.t-4').innerHTML = '<p>Error submitting form. Please try again.</p>';
                if (loader) {
                    loader.style.display = 'none';
                    console.log('Loader deactivated due to error');
                }
            });
        }
    });

    // Xử lý phân trang AJAX cho NFT Holders
    const holdersList = document.getElementById('holders-list');
    if (holdersList) {
        holdersList.addEventListener('click', e => {
            if (e.target.classList.contains('page-button') && e.target.dataset.type !== 'ellipsis') {
                e.preventDefault();
                const page = e.target.closest('form')?.querySelector('input[name="page"]')?.value || e.target.dataset.page;
                const mint = holdersList.dataset.mint;
                if (!page || !mint || !csrfToken) {
                    console.error('Missing page, mint, or CSRF token');
                    return;
                }
                console.log('Sending AJAX request for page:', page, 'mint:', mint);

                const formData = new FormData();
                formData.append('mintAddress', mint);
                formData.append('page', page);
                formData.append('csrf_token', csrfToken);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'nft-holders/nft-holders-list.php', true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onreadystatechange = () => {
                    if (xhr.readyState === 4) {
                        console.log('AJAX response status:', xhr.status);
                        if (xhr.status === 200) {
                            holdersList.innerHTML = xhr.responseText;
                            console.log('Pagination updated successfully');
                        } else {
                            console.error('AJAX error:', xhr.statusText);
                            holdersList.innerHTML = '<p>Error loading page. Please try again.</p>';
                        }
                    }
                };
                // Chuyển FormData thành URL-encoded string
                const params = new URLSearchParams(formData).toString();
                xhr.send(params);
            }
        });
    }
});
