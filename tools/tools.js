// File: tools/tools.js
// Description: Script for managing the entire tool page, including tool tab navigation, wallet analysis tab navigation, form submission, export, and copy functionality.
// Created by: Vina Network

document.addEventListener('DOMContentLoaded', () => {
    // Initialize tool tab navigation
    const urlParams = new URLSearchParams(window.location.search);
    const tool = urlParams.get('tool');
    const tab = urlParams.get('tab');
    const tabsContainer = document.querySelector('.tools-nav');
    const contentContainer = document.querySelector('.tools-content');

    console.log('Initial tool:', tool);
    console.log('Initial tab:', tab);

    // Function to load tool content with slide animation
    function loadToolContent(tool) {
        const loader = document.querySelector('.loader');
        if (loader) loader.style.display = 'block';
        contentContainer.classList.remove('slide-in');
        contentContainer.style.display = 'block';
        tabsContainer.style.display = 'none';
        document.querySelector('.note').style.display = 'none';

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
            contentContainer.innerHTML = `
                <div class="tools-back">
                    <button class="back-button"><i class="fa-solid fa-arrow-left"></i> Back to Tools</button>
                </div>
                ${data}
            `;
            contentContainer.classList.add('slide-in');
            if (loader) loader.style.display = 'none';
            if (tool === 'wallet-analysis') {
                initializeWalletTabs();
            }
            initializeClearInput();
        })
        .catch(error => {
            console.error(`Error loading tool ${tool}:`, error);
            contentContainer.innerHTML = `
                <div class="tools-back">
                    <button class="back-button"><i class="fa-solid fa-arrow-left"></i> Back to Tools</button>
                </div>
                <div class="result-error"><p>Error loading tool: ${error.message}</p></div>
            `;
            contentContainer.classList.add('slide-in');
            if (loader) loader.style.display = 'none';
        });
    }

    if (tool) {
        loadToolContent(tool);
    }

    document.querySelectorAll('.tools-nav-card').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.tools-nav-card').forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');
            const tool = this.getAttribute('data-tool');
            history.pushState({}, '', `?tool=${encodeURIComponent(tool)}`);
            loadToolContent(tool);
        });
    });

    document.addEventListener('click', (e) => {
        if (e.target.closest('.back-button')) {
            e.preventDefault();
            history.pushState({}, '', '/tools/');
            tabsContainer.style.display = 'grid';
            document.querySelector('.note').style.display = 'block';
            contentContainer.style.display = 'none';
            contentContainer.innerHTML = `
                <div class="tools-back">
                    <button class="back-button"><i class="fa-solid fa-arrow-left"></i> Back to Tools</button>
                </div>
            `;
            document.querySelectorAll('.tools-nav-card').forEach(tab => tab.classList.remove('active'));
        }
    });

    function initializeWalletTabs() {
        const walletTabsContainer = document.querySelector('.wallet-tabs');
        let activeWalletTab = document.querySelector('.wallet-tab-link.active');

        console.log('Active wallet tab element:', activeWalletTab);

        if (!activeWalletTab && tab) {
            activeWalletTab = document.querySelector(`.wallet-tab-link[data-tab="${tab}"]`);
            if (activeWalletTab) {
                activeWalletTab.classList.add('active');
                console.log(`Activated wallet tab for tab: ${tab}`);
            } else {
                console.error(`No wallet tab found for tab: ${tab}`);
            }
        }

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

                const loader = document.querySelector('.loader');
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

    function initializeClearInput() {
        document.querySelectorAll('.input-wrapper').forEach(wrapper => {
            const input = wrapper.querySelector('input');
            const clearButton = wrapper.querySelector('.clear-input');
            if (input && clearButton) {
                function toggleClearButton() {
                    clearButton.classList.toggle('visible', input.value.length > 0);
                }
                toggleClearButton();
                input.addEventListener('input', toggleClearButton);
                clearButton.addEventListener('click', () => {
                    input.value = '';
                    input.focus();
                    toggleClearButton();
                    console.log('Cleared input:', input.id);
                });
            }
        });
    }

    initializeClearInput();

    if (tool === 'wallet-analysis') {
        initializeWalletTabs();
    }

    // Handle form submissions
    document.addEventListener('submit', (e) => {
        console.log('Form submitted:', e.target.id, 'Action:', e.target.action);

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

            if (!['all', 'address-only'].includes(exportType)) {
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

        if (e.target.matches('#nftInfoForm, #nftHoldersForm, #nftTransactionForm, #walletCreatorForm, #walletAnalysisForm, #tokenBurnForm')) {
            e.preventDefault();
            const form = e.target;
            const loader = document.querySelector('.loader');
            const tool = form.getAttribute('data-tool') || urlParams.get('tool') || 'unknown';

            console.log('Form submission:', {
                formId: form.id,
                tool,
                formData: Object.fromEntries(new FormData(form))
            });

            if (tool === 'unknown') {
                console.error('Form submission failed: Unknown tool');
                contentContainer.innerHTML = `
                    <div class="tools-back">
                        <button class="back-button"><i class="fa-solid fa-arrow-left"></i> Back to Tools</button>
                    </div>
                    <div class="result-error"><p>Error: Unknown tool</p></div>
                `;
                contentContainer.classList.add('slide-in');
                if (loader) loader.style.display = 'none';
                return;
            }

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
                        contentContainer.innerHTML = `
                            <div class="tools-back">
                                <button class="back-button"><i class="fa-solid fa-arrow-left"></i> Back to Tools</button>
                            </div>
                            <div class="result-error"><p>Error: ${json.error}</p></div>
                        `;
                        return;
                    }
                } catch (e) {
                    contentContainer.innerHTML = `
                        <div class="tools-back">
                            <button class="back-button"><i class="fa-solid fa-arrow-left"></i> Back to Tools</button>
                        </div>
                        ${data}
                    `;
                }
                contentContainer.classList.add('slide-in');
                if (loader) loader.style.display = 'none';
                if (tool === 'wallet-analysis') {
                    initializeWalletTabs();
                }
                initializeClearInput();
            })
            .catch(error => {
                console.error(`Error submitting form ${form.id}:`, error);
                contentContainer.innerHTML = `
                    <div class="tools-back">
                        <button class="back-button"><i class="fa-solid fa-arrow-left"></i> Back to Tools</button>
                    </div>
                    <div class="result-error"><p>Error submitting form: ${error.message}</p></div>
                `;
                contentContainer.classList.add('slide-in');
                if (loader) loader.style.display = 'none';
            });
        } else {
            console.warn('Unknown form submitted:', e.target.id);
        }
    });

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

    const fullAddress = icon.getAttribute('data-full');
    if (!fullAddress) {
        console.error('Copy failed: data-full attribute not found or empty');
        alert('Unable to copy address: Invalid address');
        return;
    }

    console.log('Attempting to copy address:', fullAddress);

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
