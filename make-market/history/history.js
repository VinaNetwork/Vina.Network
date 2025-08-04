// ============================================================================
// File: make-market/history/history.js
// Description: JavaScript for Make Market history page
// Created by: Vina Network
// ============================================================================

// Log message function
function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
    if (log_type === 'DEBUG' && (!window.ENVIRONMENT || window.ENVIRONMENT !== 'development')) {
        return;
    }
    fetch('/make-market/log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message, log_file, module, log_type })
    }).catch(err => console.error('Log error:', err));
}

// Show error message
function showError(message) {
    const detailsDiv = document.getElementById('sub-transaction-details');
    detailsDiv.innerHTML = `
        <div class="alert alert-danger">
            <strong>Error:</strong> ${message}
        </div>
    `;
    detailsDiv.classList.add('active');
    log_message(`Error: ${message}`, 'make-market.log', 'make-market', 'ERROR');
}

// Fetch sub-transaction details
async function fetchSubTransactions(transactionId) {
    try {
        const response = await fetch(`/make-market/history/get-sub-transactions.php?id=${transactionId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message);
        }
        log_message(`Fetched sub-transactions for transaction ID=${transactionId}, count=${result.data.length}`, 'make-market.log', 'make-market', 'INFO');
        return result.data;
    } catch (err) {
        log_message(`Failed to fetch sub-transactions: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        showError('Failed to load sub-transaction details: ' + err.message);
        return [];
    }
}

// Display sub-transaction details
function displaySubTransactions(subTransactions, transactionId) {
    const detailsDiv = document.getElementById('sub-transaction-details');
    if (subTransactions.length === 0) {
        detailsDiv.innerHTML = '<p>No sub-transactions found.</p>';
        detailsDiv.classList.add('active');
        return;
    }

    let html = `
        <h3>Sub-Transactions for Transaction ID ${transactionId}</h3>
        <div style="overflow-x: auto;">
            <table class="sub-transaction-table">
                <thead>
                    <tr>
                        <th>Loop</th>
                        <th>Batch</th>
                        <th>Status</th>
                        <th>Transaction ID</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
    `;
    subTransactions.forEach(tx => {
        const statusClass = tx.status === 'success' ? 'text-success' : (tx.status === 'partial' ? 'text-warning' : 'text-danger');
        html += `
            <tr>
                <td>${tx.loop_number}</td>
                <td>${tx.batch_index}</td>
                <td class="${statusClass}">${tx.status}</td>
                <td>
                    ${tx.txid ? 
                        `<a href="https://solscan.io/tx/${tx.txid}" target="_blank">${tx.txid.substring(0, 4)}...${tx.txid.substring(tx.txid.length - 4)}</a>
                        <i class="fas fa-copy copy-icon" title="Copy full transaction ID" data-full="${tx.txid}"></i>` 
                        : '-'}
                </td>
                <td>${tx.error || '-'}</td>
            </tr>
        `;
    });
    html += '</tbody></table></div>';
    detailsDiv.innerHTML = html;
    detailsDiv.classList.add('active');
}

// Create card view for mobile
function createCardView() {
    const transactions = document.querySelectorAll('.transaction-row');
    const transactionHistory = document.querySelector('.transaction-history');
    const existingCards = document.querySelectorAll('.transaction-card');
    existingCards.forEach(card => card.remove()); // Remove old cards

    if (window.innerWidth > 768) return; // Only create cards on mobile

    transactions.forEach(row => {
        const id = row.getAttribute('data-id');
        const cells = row.querySelectorAll('td');
        const processName = cells[1].textContent;
        const publicKey = cells[2].textContent === 'N/A' ? 'N/A' : cells[2].querySelector('a').textContent;
        const tokenAddress = cells[3].querySelector('a').textContent;
        const totalTx = cells[9].textContent;
        const status = cells[10].textContent;
        const statusClass = cells[10].className;

        const card = document.createElement('div');
        card.className = 'transaction-card';
        card.setAttribute('data-id', id);
        card.innerHTML = `
            <p><strong>Process:</strong> ${processName}</p>
            <p><strong>Public Key:</strong> ${publicKey}</p>
            <p><strong>Token:</strong> ${tokenAddress}</p>
            <p><strong>Total Tx:</strong> ${totalTx}</p>
            <p><strong>Status:</strong> <span class="${statusClass}">${status}</span></p>
            <button class="details-btn" data-id="${id}">View Details</button>
        `;
        transactionHistory.appendChild(card);
    });
}

// Main process
document.addEventListener('DOMContentLoaded', () => {
    log_message('history.js loaded', 'make-market.log', 'make-market', 'DEBUG');

    // Create card view for mobile
    createCardView();
    window.addEventListener('resize', createCardView);

    // Attach event listeners to table rows and cards
    const rowsAndCards = document.querySelectorAll('.transaction-row, .transaction-card');
    rowsAndCards.forEach(item => {
        item.addEventListener('click', (e) => {
            if (e.target.classList.contains('details-btn') || e.target.classList.contains('copy-icon')) return;
            const transactionId = item.getAttribute('data-id');
            log_message(`Row/card clicked for transaction ID=${transactionId}`, 'make-market.log', 'make-market', 'INFO');
            if (window.innerWidth <= 768) {
                const detailsRow = item.nextElementSibling;
                if (detailsRow && detailsRow.classList.contains('mobile-details')) {
                    detailsRow.style.display = detailsRow.style.display === 'table-row' ? 'none' : 'table-row';
                }
            } else {
                document.getElementById('sub-transaction-details').innerHTML = '<p>Loading...</p>';
                document.getElementById('sub-transaction-details').classList.add('active');
                fetchSubTransactions(transactionId).then(subTransactions => {
                    displaySubTransactions(subTransactions, transactionId);
                });
            }
        });
    });

    // Attach event listeners to details buttons
    const detailButtons = document.querySelectorAll('.details-btn');
    detailButtons.forEach(button => {
        button.addEventListener('click', async () => {
            const transactionId = button.getAttribute('data-id');
            log_message(`Details button clicked for transaction ID=${transactionId}`, 'make-market.log', 'make-market', 'INFO');
            document.getElementById('sub-transaction-details').innerHTML = '<p>Loading...</p>';
            document.getElementById('sub-transaction-details').classList.add('active');
            const subTransactions = await fetchSubTransactions(transactionId);
            displaySubTransactions(subTransactions, transactionId);
        });
    });

    // Copy functionality
    const copyIcons = document.querySelectorAll('.copy-icon');
    log_message(`Found ${copyIcons.length} copy icons`, 'make-market.log', 'make-market', 'DEBUG');
    if (copyIcons.length === 0) {
        log_message('No .copy-icon elements found in DOM', 'make-market.log', 'make-market', 'ERROR');
    }

    copyIcons.forEach(icon => {
        icon.addEventListener('click', () => {
            log_message('Copy icon clicked', 'make-market.log', 'make-market', 'INFO');
            if (!window.isSecureContext) {
                log_message('Copy blocked: Not in secure context', 'make-market.log', 'make-market', 'ERROR');
                showError('Cannot copy: This feature requires HTTPS');
                return;
            }

            const fullAddress = icon.getAttribute('data-full');
            if (!fullAddress) {
                log_message('Copy failed: data-full attribute not found or empty', 'make-market.log', 'make-market', 'ERROR');
                showError('Cannot copy: Invalid address');
                return;
            }

            const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,88}$/;
            if (!base58Regex.test(fullAddress)) {
                log_message(`Invalid address format: ${fullAddress}`, 'make-market.log', 'make-market', 'ERROR');
                showError('Cannot copy: Invalid address format');
                return;
            }

            navigator.clipboard.writeText(fullAddress).then(() => {
                log_message('Copy successful', 'make-market.log', 'make-market', 'INFO');
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
                    log_message('Copy feedback removed', 'make-market.log', 'make-market', 'DEBUG');
                }, 2000);
            }).catch(err => {
                log_message(`Clipboard API failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
                showError(`Cannot copy: ${err.message}`);
            });
        });
    });
});
