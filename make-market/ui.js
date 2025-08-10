// ============================================================================
// File: make-market/ui.js
// Description: JavaScript file for UI interactions (copy functionality) on Make Market page
// Created by: Vina Network
// ============================================================================

// Show error message
function showError(message) {
    const resultDiv = document.getElementById('mm-result');
    resultDiv.innerHTML = `<p>Error: ${message}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Clear notification</button>`;
    resultDiv.classList.add('active');
    document.querySelector('#makeMarketForm button').disabled = false;
}

// Copy functionality for public_key
document.addEventListener('DOMContentLoaded', () => {
    console.log('ui.js loaded');

    const copyIcons = document.querySelectorAll('.copy-icon');

    copyIcons.forEach(icon => {
        icon.addEventListener('click', (e) => {
            console.log('Copy icon clicked');

            const fullAddress = icon.getAttribute('data-full');
            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' : 'Invalid';
            console.log(`Attempting to copy address: ${shortAddress}`);

            navigator.clipboard.writeText(fullAddress).then(() => {
                console.log('Copy successful');
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
                    console.log('Copy feedback removed');
                }, 2000);
            }).catch(err => {
                console.error('Clipboard API failed:', err.message);
                showError(`Unable to copy: ${err.message}`);
            });
        });
    });
});
