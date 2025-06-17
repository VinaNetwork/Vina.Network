// ============================================================================
// File: tools/tools.js
// Description: Script of the entire tool page.
// Created by: Vina Network
// Updated: 17/06/2025 - Add support for nftValuationForm, nftTransactionsForm
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    // Get the "tool" parameter from URL to determine which tab should be active
    const urlParams = new URLSearchParams(window.location.search);
    const tool = urlParams.get('tool');
    const tabsContainer = document.querySelector('.t-3');
    let activeTab = document.querySelector('.t-link.active');

    // If no tab is active but a tool is specified in the URL, activate the corresponding tab
    if (!activeTab && tool) {
        activeTab = document.querySelector(`.t-link[data-tool="${tool}"]`);
        if (activeTab) {
            activeTab.classList.add('active');
        } else {
            console.error(`No tab found for tool: ${tool}`);
        }
    }

    // Scroll the active tab into view
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

    // Handle tab click events
    document.querySelectorAll('.t-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            // Remove active class from all tabs and set the clicked tab as active
            document.querySelectorAll('.t-link').forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');

            // Scroll the clicked tab into center view
            if (tabsContainer) {
                const tabRect = this.getBoundingClientRect();
                const containerRect = tabsContainer.getBoundingClientRect();
                tabsContainer.scrollTo({
                    left: this.offsetLeft - (containerRect.width - tabRect.width) / 2,
                    behavior: 'smooth'
                });
            }

            // Update URL and load the corresponding tool content
            const tool = this.getAttribute('data-tool');
            history.pushState({}, '', `?tool=${encodeURIComponent(tool)}`);

            fetch(`/tools/tools-load.php?tool=${encodeURIComponent(tool)}`, {
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

    // Handle form submissions
    document.addEventListener('submit', (e) => {
        // Export form submission
        if (e.target.matches('.export-form')) {
            e.preventDefault();

            const form = e.target;
            const exportType = form.querySelector('[name="export_type"]').value;
            const mintAddress = form.querySelector('[name="mintAddress"]').value;
            const exportFormat = form.querySelector('[name="export_format"]').value;
            const loader = document.querySelector('.loader');
            const progressContainer = document.querySelector('.progress-container');
            const progressBarFill = document.querySelector('.progress-bar-fill');

            if (exportType !== 'all') {
                alert('Invalid export type');
                return;
            }

            // Client-side log
            log_message(`export-holders: Client - exportType=${exportType}, mintAddress=${mintAddress}, format=${exportFormat}`, 'client_log.txt');

            // Show loading indicator
            if (loader) loader.style.display = 'block';

            // Simulate progress bar
            if (progressContainer && progressBarFill) {
                progressContainer.style.display = 'block';
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += 5;
                    progressBarFill.style.width = `${progress}%`;
                    if (progress >= 95) clearInterval(progressInterval);
                }, 300);
            }

            // Submit the export form via fetch
            const formData = new FormData(form);
            fetch('/tools/nft-holders/nft-holders-export.php', {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        try {
                            const json = JSON.parse(text);
                            throw new Error(json.error || `HTTP error! Status: ${response.status}`);
                        } catch (e) {
                            throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                        }
                    });
                }
                const contentType = response.headers.get('Content-Type');
                if (contentType.includes('text/csv') || contentType.includes('application/json')) {
                    return response.blob().then(blob => ({ blob, contentType }));
                }
                return response.text().then(text => {
                    try {
                        const json = JSON.parse(text);
                        throw new Error(json.error || 'Unexpected response');
                    } catch (e) {
                        throw new Error(`Unexpected content type: ${contentType}, Response: ${text}`);
                    }
                });
            })
            .then(({ blob, contentType }) => {
                // Create a temporary download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `holders_all_${mintAddress}.${exportFormat}`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);

                // Hide loader and reset progress
                if (loader) loader.style.display = 'none';
                if (progressContainer) {
                    progressContainer.style.display = 'none';
                    progressBarFill.style.width = '0%';
                }
            })
            .catch(error => {
                console.error('Export error:', error.message);
                alert(`Export failed: ${error.message}`);
                if (loader) loader.style.display = 'none';
                if (progressContainer) {
                    progressContainer.style.display = 'none';
                    progressBarFill.style.width = '0%';
                }
            });

            return;
        }

        // Handle NFT Valuation and NFT Transactions forms
        if (e.target.matches('#nftValuationForm, #nftTransactionsForm')) {
            e.preventDefault();
            const form = e.target;
            const mintAddress = form.querySelector(`#${form.id === 'nftValuationForm' ? 'mintAddressValuation' : 'mintAddressTransactions'}`).value;
            const resultDiv = document.querySelector('.result-section');
            const errorDiv = document.querySelector('.result-error');
            const loader = document.querySelector('.loader');

            if (!mintAddress) {
                errorDiv.innerHTML = `<p>Please enter a valid ${form.id === 'nftValuationForm' ? 'Collection' : 'Mint'} Address.</p>`;
                errorDiv.style.display = 'block';
                return;
            }

            resultDiv.innerHTML = '';
            errorDiv.style.display = 'none';
            loader.style.display = 'block';

            const apiEndpoint = form.id === 'nftValuationForm' ? '/tools/tools-api.php' : '/tools/tools-api2.php';
            const requestBody = form.id === 'nftValuationForm' 
                ? { endpoint: 'getAsset', params: { id: mintAddress } }
                : { endpoint: 'v0/addresses', params: { address: mintAddress } };

            log_message(`${form.id}: Client - mintAddress=${mintAddress}, endpoint=${requestBody.endpoint}`, 'client_log.txt');

            fetch(apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestBody)
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error.message || 'API error');
                }

                if (form.id === 'nftTransactionsForm' && !data.length) {
                    errorDiv.innerHTML = '<p>No transactions found for this Mint Address.</p>';
                    errorDiv.style.display = 'block';
                    return;
                }

                if (form.id === 'nftValuationForm' && !data.result.grouping?.[0]?.group_value) {
                    errorDiv.innerHTML = '<p>No valuation data found for this Collection Address.</p>';
                    errorDiv.style.display = 'block';
                    return;
                }

                // Reload tool content to display results
                const tool = document.querySelector('.t-link.active').getAttribute('data-tool');
                const formData = new FormData(form);
                fetch(`/tools/tools-load.php?tool=${encodeURIComponent(tool)}`, {
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
                    errorDiv.innerHTML = '<p>Data loaded successfully.</p>';
                    errorDiv.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error reloading tool content:', error);
                    errorDiv.innerHTML = `<p>Error loading content: ${error.message}. Please try again.</p>`;
                    errorDiv.style.display = 'block';
                });
            })
            .catch(error => {
                console.error(`${form.id} error:`, error);
                errorDiv.innerHTML = `<p>${error.message.includes('No valuation data found') || error.message.includes('No transactions found') ? error.message : 'Error loading content: ' + error.message + '. Please try again.'}</p>`;
                errorDiv.style.display = 'block';
            })
            .finally(() => {
                loader.style.display = 'none';
            });

            return;
        }

        // Handle other form submissions (walletAnalysisForm, nftHoldersForm, etc.)
        if (e.target.matches('#walletAnalysisForm, #nftHoldersForm')) {
            e.preventDefault();
            const form = e.target;
            const loader = document.querySelector('.loader');
            if (loader) loader.style.display = 'block';

            const formData = new FormData(form);
            const tool = document.querySelector('.t-link.active').getAttribute('data-tool');
            fetch(`/tools/tools-load.php?tool=${encodeURIComponent(tool)}`, {
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
                if (loader) loader.style.display = 'none';
            })
            .catch(error => {
                console.error('Error submitting form:', error);
                document.querySelector('.t-4').innerHTML = '<p>Error submitting form. Please try again.</p>';
                if (loader) loader.style.display = 'none';
            });
        }
    });

    /**
     * Send client-side log message to the server (log-client.php)
     */
    function log_message(message, file) {
        fetch('/tools/log-client.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({message, file})
        }).catch(err => console.error('Log error:', err));
    }
});
