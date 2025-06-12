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

            const loader = document.querySelector('.loader');
            if (loader) loader.style.display = 'block';

            fetch(`/tools/load-tool.php?tool=${encodeURIComponent(tool)}`, {
                method: 'GET',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.text();
            })
            .then(data => {
                document.querySelector('.t-4').innerHTML = data;
                if (loader) loader.style.display = 'none';
            })
            .catch(error => {
                console.error('Error loading tool content:', error);
                document.querySelector('.t-4').innerHTML = '<p>Error loading content. Please try again.</p>';
                if (loader) loader.style.display = 'none';
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
            const loader = form.parentElement.querySelector('.loader');
            console.log('Loader element:', loader);
            if (loader) {
                loader.style.display = 'block';
                console.log('Loader activated');
            } else {
                console.error('Loader not found in form parent');
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
                    console.log('Loader deactivated');
                }
                initPagination();
            })
            .catch(error => {
                console.error('Error submitting form:', error);
                document.querySelector('.t-4').innerHTML = '<p>Error submitting form. Please try again.</p>';
                if (loader) {
                    loader.style.display = 'none';
                }
            });
        }
    });

    function initPagination() {
        const holdersList = document.getElementById('holders-list');
        if (holdersList) {
            holdersList.addEventListener('click', function(e) {
                if (e.target.classList.contains('page-button') && e.target.dataset.type !== 'ellipsis') {
                    e.preventDefault();
                    const loader = document.querySelector('.loader');
                    if (loader) loader.style.display = 'block';

                    const page = e.target.closest('form')?.querySelector('input[name="page"]')?.value
                        || e.target.dataset.page;
                    const mint = holdersList.dataset.mint;
                    if (!page || !mint) {
                        if (loader) loader.style.display = 'none';
                        return;
                    }
                    console.log('Sending AJAX request for page:', page, 'mint:', mint);
                    fetch('/tools/nft-holders/nft-holders-list.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `mintAddress=${encodeURIComponent(mint)}&page=${encodeURIComponent(page)}`
                    })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                        return response.text();
                    })
                    .then(data => {
                        holdersList.innerHTML = data;
                        if (loader) loader.style.display = 'none';
                    })
                    .catch(error => {
                        console.error('AJAX error:', error);
                        holdersList.innerHTML = '<p>Error loading holders. Please try again.</p>';
                        if (loader) loader.style.display = 'none';
                    });
                }
            });
        }
    }

    initPagination();
});
