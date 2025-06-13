document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tool = urlParams.get('tool');
    const tabsContainer = document.querySelector('.t-3');
    let activeTab = document.querySelector('.t-link.active');

    if (!activeTab && tool) {
        activeTab = document.querySelector(`.t-link[data-tool="${tool}"]`);
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

    document.querySelectorAll('.t-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.t-link').forEach(tab => tab.classList.remove('active'));
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
            .then(data => document.querySelector('.t-4').innerHTML = data)
            .catch(error => {
                console.error('Error loading tool content:', error);
                document.querySelector('.t-4').innerHTML = '<p>Error loading content. Please try again.</p>';
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
        if (e.target.matches('#nftValuationForm, .transaction-form, #walletAnalysisForm, #nftHoldersForm')) {
            e.preventDefault();
            const form = e.target;
            const loader = document.querySelector('.loader');
            console.log('Form submit - Loader element:', loader);
            if (loader) {
                loader.style.display = 'block';
                console.log('Form submit - Loader activated');
            } else {
                console.error('Form submit - Loader not found in DOM');
            }

            const formData = new FormData(form);
            const tool = document.querySelector('.t-link.active').getAttribute('data-tool');
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
                    console.log('Form submit - Loader deactivated');
                }
            })
            .catch(error => {
                console.error('Form submit - Error submitting form:', error);
                document.querySelector('.t-4').innerHTML = '<p>Error submitting form. Please try again.</p>';
                if (loader) {
                    loader.style.display = 'none';
                }
            });
        }
    });

    document.addEventListener('click', (e) => {
        if (e.target.matches('.page-button') && !e.target.dataset.type?.includes('ellipsis')) {
            e.preventDefault();
            const holdersList = document.getElementById('holders-list');
            if (!holdersList) {
                console.error('Pagination - holders-list not found in DOM');
                return;
            }
            const page = e.target.closest('form')?.querySelector('input[name="page"]')?.value || e.target.dataset.page;
            const mint = holdersList.dataset.mint;
            if (!page || !mint) {
                console.error('Pagination - Missing page or mint:', { page, mint });
                return;
            }
            console.log('Pagination - Sending AJAX request for page:', page, 'mint:', mint);
            const loader = document.querySelector('.loader');
            if (!loader) {
                console.error('Pagination - Loader not found in DOM');
                return;
            }
            console.log('Pagination - Loader found:', loader);
            loader.style.display = 'block';
            console.log('Pagination - Loader display set to block:', loader.style.display);
            const minLoaderTime = 1000; // Tăng lên 1s để dễ thấy
            const startTime = performance.now();
            setTimeout(() => {
                fetch('/tools/nft-holders/nft-holders-list.php', {
                    method: 'POST',
                    body: 'mintAddress=' + encodeURIComponent(mint) + '&page=' + encodeURIComponent(page),
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.text();
                })
                .then(data => {
                    const elapsed = performance.now() - startTime;
                    const remaining = minLoaderTime - elapsed;
                    console.log('Pagination - AJAX complete, elapsed:', elapsed, 'remaining:', remaining);
                    setTimeout(() => {
                        holdersList.innerHTML = data;
                        loader.style.display = 'none';
                        console.log('Pagination - Loader hidden after timeout');
                    }, remaining > 0 ? remaining : 0);
                })
                .catch(error => {
                    console.error('Pagination - AJAX error:', error);
                    holdersList.innerHTML = '<div class="result-error"><p>Error loading holders. Please try again.</p></div>';
                    loader.style.display = 'none';
                    console.log('Pagination - Loader hidden on error');
                });
            }, 1000); // Delay giả 1s
        }
    });
});
