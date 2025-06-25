// ============================================================================
// File: tools/tools.js
// Description: Script for managing the entire tool page, including tool tab navigation, wallet analysis tab navigation, form submission, export, and copy functionality.
// Author: Vina Network
// Version: Updated for compatibility with wallet-analysis lazy-load and clear input functionality
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    // Initialize tool tab navigation
    const urlParams = new URLSearchParams(window.location.search);
    const tool = urlParams.get('tool');
    const tab = urlParams.get('tab'); // Fixed: Changed 'page' to 'tab' to match tools-load.php
    const tabsContainer = document.querySelector('.tools-nav');
    let activeTab = document.querySelector('.tools-nav-card.active');

    console.log('Initial tool:', tool);
    console.log('Initial tab:', tab);
    console.log('Active tab:', activeTab);

    // Activate tab based on URL if no active tab is found
    if (!activeTab && tool) {
        activeTab = document.querySelector(`.tools-nav-card[data-tool="${tool}"]`);
        if (activeTab) {
            activeTab.classList.add('active');
            console.log(`Activated tab for tool: ${tool}`);
        } else {
            console.error(`No tab found for tool: ${tool}`);
        }
    }

    // Scroll tool tab into view
    if (tabsContainer && activeTab) {
        setTimeout(() => {
            const tabRect = activeTab.getBoundingClientRect();
            const containerRect = tabsContainer.getBoundingClientRect();
            tabsContainer.scrollTo({
                left: activeTab.offsetLeft - (containerRect.width - tabRect.width) / 2,
                behavior: 'smooth'
            });
            console.log('Scrolled to active tab:', activeTab.getAttribute('data-tool'));
        }, 100);
    }

    // Handle tool tab click events
    document.querySelectorAll('.tools-nav-card').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            // Update active tool tab
            document.querySelectorAll('.tools-nav-card').forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');

            // Scroll tool tab into center
            if (tabsContainer) {
                const tabRect = this.getBoundingClientRect();
                const containerRect = tabsContainer.getBoundingClientRect();
                tabsContainer.scrollTo({
                    left: this.offsetLeft - (containerRect.width - tabRect.width) / 2,
                    behavior: 'smooth'
                });
            }

            // Load tool content
            const tool = this.getAttribute('data-tool');
            console.log(`Loading tool: ${tool}`);
            history.pushState({}, '', `?tool=${encodeURIComponent(tool)}`);

            fetch(`/tools/tools-load.php?tool=${encodeURIComponent(tool)}`, {
                method: 'GET',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => {
                console.log(`Tool ${tool} fetch status: ${response.status}`);
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.text();
            })
            .then(data => {
                console.log(`Tool ${tool} loaded successfully, response length: ${data.length}`);
                document.querySelector('.tools-content').innerHTML = data;
                // Initialize wallet tabs if wallet-analysis is loaded
                if (tool === 'wallet-analysis') {
                    initializeWalletTabs();
                }
                // Re-initialize clear input after AJAX
                initializeClearInput();
            })
            .catch(error => {
                console.error(`Error loading tool ${tool}:`, error);
                document.querySelector('.tools-content').innerHTML = `<div class="result-error"><p>Error loading tool: ${error.message}</p></div>`;
            });
        });
    });

    // Initialize wallet analysis tabs
    function initializeWalletTabs() {
        const walletTabsContainer = document.querySelector('.wallet-tabs');
        let activeWalletTab = document.querySelector('.wallet-tab-link.active');
        const loader = document.querySelector('.loader');

        console.log('Active wallet tab element:', activeWalletTab);

        // Activate wallet tab based on URL if no active tab
        if (!activeWalletTab && tab) {
            activeWalletTab = document.querySelector(`.wallet-tab-link[data-tab="${tab}"]`);
            if (activeWalletTab) {
                activeWalletTab.classList.add('active');
                console.log(`Activated wallet tab for tab: ${tab}`);
            } else {
                console.error(`No wallet tab found for tab: ${tab}`);
            }
        }

        // Scroll wallet tab into view
        if (walletTabsContainer && activeWalletTab) {
            setTimeout(() => {
                const tabRect = activeWalletTab.getBoundingClientRect();
                const containerRect = walletTabsContainer.getBoundingClientRect();
                walletTabsContainer.scrollTo({
                    left: activeWalletTab.offsetLeft - (containerRect.width - tabRect.width) / 2,
                    behavior: 'smooth'
                });
                console.log('Scrolled to active wallet tab:', activeWalletTab.getAttribute('data-tab'));
            }, 100);
        }

        // Handle wallet tab click events
        document.querySelectorAll('.wallet-tab-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.wallet-tab-link').forEach(tab => tab.classList.remove('active'));
                this.classList.add('active');

                if (walletTabsContainer) {
                    const tabRect = this.getBoundingClientRect();
                    const containerRect = walletTabsContainer.getBoundingClientRect();
                    walletTabsContainer.scrollTo({
                        left: this.offsetLeft - (containerRect.width - tabRect.width) / 2,
                        behavior: 'smooth'
                    });
                }

                const tab = this.getAttribute('data-tab');
                console.log(`Loading wallet tab: ${tab}`);
                history.pushState({}, '', `?tool=wallet-analysis&tab=${encodeURIComponent(tab)}`);

                // Show loader
                if (loader) loader.style.display = 'block';

                fetch(`/tools/tools-load.php?tool=wallet-analysis&tab=${encodeURIComponent(tab)}`, {
                    method: 'GET',
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(response => {
                    console.log(`Wallet tab ${tab} fetch status: ${response.status}`);
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.text();
                })
                .then(data => {
                    console.log(`Wallet tab ${tab} loaded successfully, response length: ${data.length}`);
                    document.querySelector('.wallet-tab-content').innerHTML = data;
                    if (loader) loader.style.display = 'none';
                    // Re-initialize clear input after AJAX
                    initializeClearInput();
                })
                .catch(error => {
                    console.error(`Error loading wallet tab ${tab}:`, error);
                    document.querySelector('.wallet-tab-content').innerHTML = `<div class="result-error"><p>Error loading tab: ${error.message}</p></div>`;
                    if (loader) loader.style.display = 'none';
                });
            });
        });
    }

    // Handle clear input button for all inputs with .clear-input
    function initializeClearInput() {
        document.querySelectorAll('.input-wrapper').forEach(wrapper => {
            const input = wrapper.querySelector('input');
            const clearButton = wrapper.querySelector('.clear-input');
            if (input && clearButton) {
                // Toggle clear button visibility based on input content
                function toggleClearButton() {
                    clearButton.classList.toggle('visible', input.value.length > 0);
                }
                // Initial check
                toggleClearButton();
                // Listen for input changes
                input.addEventListener('input', toggleClearButton);
                // Handle clear button click
                clearButton.addEventListener('click', () => {
                    input.value = '';
                    input.focus();
                    toggleClearButton();
                    console.log('Cleared input:', input.id);
                });
            }
        });
    }

    // Call initializeClearInput initially
    initializeClearInput();

    // Call initializeWalletTabs if wallet-analysis is loaded
    if (tool === 'wallet-analysis') {
        initializeWalletTabs();
    }

    // Handle form submissions
    document.addEventListener('submit', (e) => {
        console.log('Form submitted:', e.target.id, 'Action:', e.target.action);

        // Export form submission (NFT Holders)
        if (e.target.matches('.export-form')) {
            e.preventDefault();

            const form = e.target;
            const exportType = form.querySelector('[name="export_type"]').value;
            const mintAddress = form.querySelector('[name="mintAddress"]').value;
            const exportFormat = form.querySelector('[name="export_format"]').value;
            const loader = document.querySelector('.loader');
            const progressContainer = document.querySelector('.progress-container');
            const progressBarFill = document.querySelector('.progress-bar-fill');

            console.log('Export form submitted:', {
                formId: form.id,
                exportType,
                mintAddress,
                exportFormat,
                action: form.action
            });

            if (exportType !== 'all') {
                console.warn('Invalid export type:', exportType);
                alert('Invalid export type');
                return;
            }

            if (loader) loader.style.display = 'block';

            if (progressContainer && progressBarFill) {
                progressContainer.style.display = 'block';
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += 5;
                    progressBarFill.style.width = `${progress}%`;
                    if (progress >= 95) clearInterval(progressInterval);
                }, 300);
            }

            let exportPath;
            if (form.action.includes('nft-holders')) {
                exportPath = '/tools/nft-holders/nft-holders-export.php';
            } else {
                console.error('Invalid export form action:', form.action);
                alert('Invalid export form action');
                if (loader) loader.style.display = 'none';
                if (progressContainer) {
                    progressContainer.style.display = 'none';
                    progressBarFill.style.width = '0%';
                }
                return;
            }
            console.log('Export path:', exportPath);

            const formData = new FormData(form);
            console.log('Export form data:', Object.fromEntries(formData));
            fetch(exportPath, {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => {
                console.log(`Export fetch status: ${response.status}`);
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
                console.log('Export content type:', contentType);
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
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `holders_all_${mintAddress.substring(0, 8)}.${exportFormat}`;
                console.log('Export file:', a.download);
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);

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

        // Handle form submissions (walletAnalysisForm, nftHoldersForm, nftInfoForm)
        if (e.target.matches('#walletAnalysisForm, #nftHoldersForm, #nftInfoForm')) {
            e.preventDefault();
            const form = e.target;
            const loader = document.querySelector('.loader');
            const tool = document.querySelector('.tools-nav-card.active')?.getAttribute('data-tool') || 'unknown';

            console.log('Form submission:', {
                formId: form.id,
                tool,
                formData: Object.fromEntries(new FormData(form))
            });

            if (loader) loader.style.display = 'block';

            const formData = new FormData(form);
            fetch(`/tools/tools-load.php?tool=${encodeURIComponent(tool)}`, {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => {
                console.log(`Form fetch status for ${form.id}: ${response.status}`);
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
                return response.text();
            })
            .then(data => {
                console.log(`Form ${form.id} response length: ${data.length}`);
                try {
                    const json = JSON.parse(data);
                    if (json.error) {
                        document.querySelector('.tools-content').innerHTML = `<div class="result-error"><p>Error: ${json.error}</p></div>`;
                        return;
                    }
                } catch (e) {
                    // Not JSON, assume HTML
                    document.querySelector('.tools-content').innerHTML = data;
                }
                if (loader) loader.style.display = 'none';
                // Re-initialize wallet tabs after form submission
                if (tool === 'wallet-analysis') {
                    initializeWalletTabs();
                }
                // Re-initialize clear input after AJAX
                initializeClearInput();
            })
            .catch(error => {
                console.error(`Error submitting form ${form.id}:`, error);
                document.querySelector('.tools-content').innerHTML = `<div class="result-error"><p>Error submitting form: ${error.message}</p></div>`;
                if (loader) loader.style.display = 'none';
            });
        } else {
            console.warn('Unknown form submitted:', e.target.id);
        }
    });

    // Debug copy icons after AJAX
    console.log('DOM loaded, initializing copy debug');
    setTimeout(() => {
        console.log('Re-checking copy icons after AJAX');
        document.querySelectorAll('.copy-icon').forEach(icon => {
            console.log('Found copy-icon after AJAX:', icon, 'data-full:', icon.getAttribute('data-full'));
        });
    }, 1000);
});

// Copy functionality for wallet and table addresses
console.log('Initializing copy functionality');

document.addEventListener('click', function(e) {
    const icon = e.target.closest('.copy-icon');
    if (!icon) return;

    console.log('Copy icon clicked:', icon);

    // Get address from data-full
    const fullAddress = icon.getAttribute('data-full');
    if (!fullAddress) {
        console.error('Copy failed: data-full attribute not found or empty');
        alert('Unable to copy address: Invalid address');
        return;
    }

    console.log('Attempting to copy address:', fullAddress);

    // Try Clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        console.log('Using Clipboard API');
        navigator.clipboard.writeText(fullAddress).then(() => {
            showCopyFeedback(icon);
        }).catch(err => {
            console.error('Clipboard API failed:', err);
            fallbackCopy(fullAddress, icon);
        });
    } else {
        console.warn('Clipboard API unavailable, using fallback');
        fallbackCopy(fullAddress, icon);
    }
});

function fallbackCopy(text, icon) {
    console.log('Using fallback copy for:', text);
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = '0';
    textarea.style.left = '0';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    try {
        const success = document.execCommand('copy');
        console.log('Fallback copy result:', success);
        if (success) {
            showCopyFeedback(icon);
        } else {
            console.error('Fallback copy failed');
            alert('Unable to copy address: Copy error');
        }
    } catch (err) {
        console.error('Fallback copy error:', err);
        alert('Unable to copy address: ' + err.message);
    } finally {
        document.body.removeChild(textarea);
    }
}

function showCopyFeedback(icon) {
    console.log('Showing copy feedback');
    icon.classList.add('copied');
    const tooltip = document.createElement('span');
    tooltip.className = 'copy-tooltip';
    tooltip.textContent = 'Copied!';
    const parent = icon.parentNode;
    parent.style.position = 'relative';
    parent.appendChild(tooltip);
    setTimeout(() => {
        icon.classList.remove('copied');
        tooltip.remove();
    }, 1000);
    console.log('Copy successful');
}
