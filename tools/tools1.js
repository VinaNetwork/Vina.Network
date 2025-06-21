
// ============================================================================
// File: tools/tools.js
// Description: Script for the entire tool page, including tab navigation, form submission, and export functionality.
// Author: Vina Network
// ============================================================================

// ... (remaining JavaScript content omitted for brevity)

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

// ...
