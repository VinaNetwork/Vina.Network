// ============================================================================
// File: tools/tools.js
// Description: Script for the entire tool page, including tab navigation, form submission, export, and copy functionality.
// Author: Vina Network
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    // Initialize tab navigation
    const urlParams = new URLSearchParams(window.location.search);
    const tool = urlParams.get('tool');
    const tabsContainer = document.querySelector('.tools-nav');
    let activeTab = document.querySelector('.tools-nav-link.active');

    console.log('Initial tool from URL:', tool);
    console.log('Active tab element:', activeTab);

    // Activate tab based on URL if no active tab
    if (!activeTab && tool) {
        activeTab = document.querySelector(`.tools-nav-link[data-tool="${tool}"]`);
        if (activeTab) {
            activeTab.classList.add('active');
            console.log(`Activated tab for tool: ${tool}`);
        } else {
            console.error(`No tab found for tool: ${tool}`);
        }
    }

    // Scroll active tab into view
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

    // Handle tab click events
    document.querySelectorAll('.tools-nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            // Update active tab
            document.querySelectorAll('.tools-nav-link').forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');

            // Scroll tab into center
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
            })
            .catch(error => {
                console.error(`Error loading tool ${tool}:`, error);
                document.querySelector('.tools-content').innerHTML = `<div class="result-error"><p>Error loading tool: ${error.message}</p></div>`;
            });
        });
    });

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
            const tool = document.querySelector('.tools-nav-link.active')?.getAttribute('data-tool') || 'unknown';

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
        alert('Không thể sao chép địa chỉ: Địa chỉ không hợp lệ');
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
            alert('Không thể sao chép địa chỉ: Lỗi sao chép');
        }
    } catch (err) {
        console.error('Fallback copy error:', err);
        alert('Không thể sao chép địa chỉ: ' + err.message);
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
