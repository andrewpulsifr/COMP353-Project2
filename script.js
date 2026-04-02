/**
 * script.js
 * Purpose: Client-side UI helpers for showing/hiding panels and submitting query forms.
 */

/**
 * Shows a UI panel by id.
 * For results, keeps query/results visible together
 * Otherwise hides all and shows only the requested panel.
 *
 * @param {string} panelId The id of the panel to show.
 */
function showPanel(panelId) {
    // If showing results, just show query and results panels without hiding others
    if (panelId === 'resultsPanel') {
        const queryPanel = document.getElementById('queryPanel');
        const resultsPanel = document.getElementById('resultsPanel');
        if (queryPanel) queryPanel.classList.add('show');
        if (resultsPanel) resultsPanel.classList.add('show');
        return;
    }

    if (!panelId) return;

    // For other panels, hide all first then show the requested one
    const idsToHide = [
        'queryPanel',
        'transactionPanel',
        'dbContent',
        'resultsPanel',
        'query3-options',
        'query4-options',
    ];

    idsToHide.forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('show');
    });

    const panel = document.getElementById(panelId);
    if (panel) panel.classList.add('show');
}

/**
 * Hides all major panels and clears any rendered query result HTML.
 */
function hideAll() {
    const idsToHide = [
        'queryPanel',
        'transactionPanel',
        'dbContent',
        'resultsPanel',
        'query3-options',
        'query4-options',
    ];

    idsToHide.forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('show');
    });

    const resultContent = document.getElementById('resultContent');
    if (resultContent) resultContent.innerHTML = '';
}

/**
 * Sets the selected query type and submits the query form.
 * Resets group-by controls unless query 3 or 4 is selected.
 *
 * @param {string} queryType Query identifier (1-9).
 */
function executeQuery(queryType) {
    const selectedQuery = document.getElementById('selectedQuery');
    if (selectedQuery) selectedQuery.value = queryType;

    // For Query 3 and 4, keep dropdown state; for others, reset group-by
    if (queryType !== '3' && queryType !== '4') {
        const query3Options = document.getElementById('query3-options');
        const query4Options = document.getElementById('query4-options');
        const query3GroupBy = document.getElementById('query3GroupBy');
        const query4GroupBy = document.getElementById('query4GroupBy');

        if (query3Options) query3Options.classList.remove('show');
        if (query3GroupBy) query3GroupBy.value = 'none';
        if (query4Options) query4Options.classList.remove('show');
        if (query4GroupBy) query4GroupBy.value = 'none';
    }

    const queryForm = document.getElementById('queryForm');
    if (queryForm) queryForm.submit();
}

/**
 * Updates Query 3 grouping mode and submits the query form.
 *
 * @param {string} value Group-by value for query 3.
 */
function updateQuery3GroupBy(value) {
    const query3GroupBy = document.getElementById('query3GroupBy');
    if (query3GroupBy) query3GroupBy.value = value;
    const selectedQuery = document.getElementById('selectedQuery');
    if (selectedQuery) selectedQuery.value = '3';
    const queryForm = document.getElementById('queryForm');
    if (queryForm) queryForm.submit();
}

/**
 * Updates Query 4 grouping mode and submits the query form.
 *
 * @param {string} value Group-by value for query 4.
 */
function updateQuery4GroupBy(value) {
    const query4GroupBy = document.getElementById('query4GroupBy');
    if (query4GroupBy) query4GroupBy.value = value;
    const selectedQuery = document.getElementById('selectedQuery');
    if (selectedQuery) selectedQuery.value = '4';
    const queryForm = document.getElementById('queryForm');
    if (queryForm) queryForm.submit();
}

// Transaction 10 is completion-only no mission-status-driven UI logic.
