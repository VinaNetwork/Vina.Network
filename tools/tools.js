// ============================================================================
// File: tools/tools.js
// Description: Script for managing the entire tool page, including tool tab navigation, wallet analysis tab navigation, form submission, export, and copy functionality.
// Created by: Vina Network
// ============================================================================

// Cache for downloaded export blobs
const exportCache = {};

document.addEventListener('DOMContentLoaded', () => {
    ...
    // Replace fetch in export form submission with caching logic
    document.addEventListener('submit', (e) => {
        if (e.target.matches('.export-form')) {
            e.preventDefault();
            const form = e.target;
            const exportType = form.querySelector('[name="export_type"]').value;
            const mintAddress = form.querySelector('[name="mintAddress"]').value;
            const exportFormat = form.querySelector('[name="export_format"]').value;
            const loader = document.querySelector('.loader');
            const progressContainer = document.querySelector('.progress-container');
            const progressBarFill = document.querySelector('.progress-bar-fill');

            if (!['all', 'address-only'].includes(exportType)) {
                alert('Invalid export type');
                return;
            }

            const cacheKey = `${exportType}_${mintAddress}_${exportFormat}`;
            if (exportCache[cacheKey]) {
                const { blob } = exportCache[cacheKey];
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `holders_${exportType}_${mintAddress.substring(0, 8)}.${exportFormat}`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
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

            const exportPath = form.action.includes('nft-holders') ? '/tools/nft-holders/nft-holders-export.php' : null;
            if (!exportPath) {
                alert('Invalid export form action');
                if (loader) loader.style.display = 'none';
                if (progressContainer) {
                    progressContainer.style.display = 'none';
                    progressBarFill.style.width = '0%';
                }
                return;
            }

            const formData = new FormData(form);
            fetch(exportPath, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => {
                if (!response.ok) throw new Error('Export failed');
                return response.blob();
            })
            .then(blob => {
                exportCache[cacheKey] = { blob };
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `holders_${exportType}_${mintAddress.substring(0, 8)}.${exportFormat}`;
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
                alert(`Export failed: ${error.message}`);
                if (loader) loader.style.display = 'none';
                if (progressContainer) {
                    progressContainer.style.display = 'none';
                    progressBarFill.style.width = '0%';
                }
            });
        }
    });
    ...
});