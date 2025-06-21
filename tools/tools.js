// ============================================================================
// File: tools/tools.js
// Description: Script for the entire tool page, including tab navigation, form submission, and export functionality.
// Author: Vina Network
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    // Get the "tool" parameter from URL to determine which tab should be active
    const urlParams = new URLSearchParams(window.location.search);
    const tool = urlParams.get('tool');
    const tabsContainer = document.querySelector('.t-3');
    let activeTab = document.querySelector('.t-link.active');

    // Debug: Log initial tool and active tab
    console.log('Initial tool from URL:', tool);
    console.log('Active tab element:', activeTab);

    // If no tab is active but a tool is specified in the URL, activate the corresponding tab
    if (!activeTab && tool) {
        activeTab = document.querySelector(`.t-link[data-tool="${tool}"]`);
        if (activeTab) {
            activeTab.classList.add('active');
            console.log(`Activated tab for tool: ${tool}`);
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
            console.log('Scrolled to active tab:', activeTab.getAttribute('data-tool'));
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
                document.querySelector('.t-4').innerHTML = data;
            })
            .catch(error => {
                console.error(`Error loading tool ${tool}:`, error);
                document.querySelector('.t-4').innerHTML = `<div class="result-error"><p>Error loading tool: ${error.message}</p></div>`;
            });
        });
    });

    // Handle form submissions
    document.addEventListener('submit', (e) => {
        console.log('Form submitted:', e.target.id, 'Action:', e.target.action);

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

            log_message(`export: Client - exportType=${exportType}, mintAddress=${mintAddress}, format=${exportFormat}`, 'client_log.txt');

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
            if (form.action.includes('nft-info')) {
                exportPath = '/tools/nft-info/nft-info-export.php';
            } else if (form.action.includes('nft-holders')) {
                exportPath = '/tools/nft-holders/nft-holders-export.php';
            } else {
                exportPath = '/tools/nft-transactions/nft-transactions-export.php';
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
                const toolName = exportPath.includes('nft-info') ? 'nft_info' : exportPath.includes('nft-holders') ? 'holders' : 'transactions';
                a.download = `${toolName}_all_${mintAddress.substring(0, 8)}.${exportFormat}`;
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

        // Handle other form submissions (walletAnalysisForm, nftHoldersForm, nftInfoForm)
        if (e.target.matches('#walletAnalysisForm, #nftHoldersForm, #nftInfoForm')) {
            e.preventDefault();
            const form = e.target;
            const loader = document.querySelector('.loader');
            const tool = document.querySelector('.t-link.active')?.getAttribute('data-tool') || 'unknown';

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
                        document.querySelector('.t-4').innerHTML = `<div class="result-error"><p>Error: ${json.error}</p></div>`;
                        return;
                    }
                } catch (e) {
                    // Not JSON, assume HTML
                    document.querySelector('.t-4').innerHTML = data;
                }
                if (loader) loader.style.display = 'none';
            })
            .catch(error => {
                console.error(`Error submitting form ${form.id}:`, error);
                document.querySelector('.t-4').innerHTML = `<div class="result-error"><p>Error submitting form: ${error.message}</p></div>`;
                if (loader) loader.style.display = 'none';
            });
        } else {
            console.warn('Unknown form submitted:', e.target.id);
        }
    });

    // Send client-side log message to the server (client_log.php)
    function log_message(message, file) {
        console.log('Logging to client_log:', message);
        fetch('/tools/logs/client_log.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ message, file })
        }).catch(err => console.error('Log error:', err));
    }

    // Wallet Analysis
    $(document).on('submit', '#walletAnalysisForm', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $loader = $form.next('.loader');
        $.ajax({
            url: '/tools/tools-load.php',
            type: 'POST',
            data: $form.serialize() + '&tool=wallet-analysis',
            beforeSend: function() {
                $loader.show();
            },
            success: function(response) {
                $loader.hide();
                $form.closest('.wallet-analysis-content').find('.result-section, .result-error').remove();
                $form.closest('.t-7').after(response);
            },
            error: function(xhr) {
                $loader.hide();
                alert('Error: ' + xhr.status + ', ' + xhr.responseText);
            }
        });
    });

    // ========================================================================
    // Copy functionality for wallet and table addresses (Updated version)
    // ========================================================================
    document.addEventListener('click', function(e) {
    const icon = e.target.closest('.copy-icon');
    if (!icon) return;

    console.log('Copy icon clicked:', icon);

    // Updated: Get full address from data attribute
    const fullAddress = icon.getAttribute('data-full');
    if (!fullAddress) {
        console.error('Copy failed: data-full attribute not found or empty');
        alert('Không thể sao chép địa chỉ: Địa chỉ không hợp lệ');
        return;
    }

    console.log('Attempting to copy address from data-full:', fullAddress);

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
});
