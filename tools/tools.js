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
            e.preventDefault();
            const form = e.target;
            const exportType = form.querySelector('[name="export_type"]').value;
            const mintAddress = form.querySelector('[name="mintAddress"]').value;
            const exportFormat = form.querySelector('[name="export_format"]').value;
            const page = form.querySelector('[name="page"]').value;
            const loader = document.querySelector('.loader');
            const progressContainer = document.querySelector('.progress-container');
            const progressBarFill = document.querySelector('.progress-bar-fill');

            if (loader) {
                loader.style.display = 'block';
            }

            if (exportType === 'all' && progressContainer && progressBarFill) {
                progressContainer.style.display = 'block';
                let progress = 0;
                const interval = setInterval(() => {
                    progress += 5;
                    progressBarFill.style.width = `${progress}%`;
                    if (progress >= 95) {
                        clearInterval(interval);
                    }
                }, 300);
            }

            const formData = new FormData(form);
            fetch('/tools/nft-holders/export-holders.php', {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.error || `HTTP error! Status: ${response.status}`);
                    });
                }
                const contentType = response.headers.get('Content-Type');
                if (contentType.includes('text/csv') || contentType.includes('application/json')) {
                    return response.blob().then(blob => ({ blob, contentType }));
                }
                return response.json().then(data => {
                    throw new Error(data.error || 'Unexpected response');
                });
            })
            .then(({ blob, contentType }) => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `holders_${exportType}_${mintAddress}_${page}.${exportFormat}`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
                if (loader) {
                    loader.style.display = 'none';
                }
                if (progressContainer) {
                    progressContainer.style.display = 'none';
                    progressBarFill.style.width = '0%';
                }
            })
            .catch(error => {
                console.error('Export error:', error.message);
                alert(`Export failed: ${error.message}`);
                if (loader) {
                    loader.style.display = 'none';
                }
                if (progressContainer) {
                    progressContainer.style.display = 'none';
                    progressBarFill.style.width = '0%';
                }
            });
            return;
        }
        if (e.target.matches('#nftValuationForm, .transaction-form, #walletAnalysisForm, #nftHoldersForm')) {
            e.preventDefault();
            const form = e.target;
            const loader = document.querySelector('.loader');
            if (loader) {
                loader.style.display = 'block';
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
                }
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
});
