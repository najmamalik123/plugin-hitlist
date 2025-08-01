
let currentPage = 1;
const itemsPerPage = 20;


let currentPage = 1;
const itemsPerPage = 20;

function renderTable(data) {
    if (data.length === 0) {
        resultsEl.innerHTML = '<p>Geen gegevens gevonden.</p>';
        return;
    }

    // Paginate the data
    const paginatedData = data.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

    let html = `<div class="hit-lists-header-info">
        <h3>Hier is de ${selectYear.value} Indie500</h3>
        <div class="hit-lists-count">${data.length} titels</div>
    </div>
    <div class="hit-lists-results-simple">`;

    paginatedData.forEach((item, index) => {
        html += `<div class="hit-lists-result-item">
            <span class="rank">${(currentPage - 1) * itemsPerPage + index + 1}</span> – 
            <span class="artist">${item.artist_name}</span> – 
            <span class="title">${item.song_title}</span>
        </div>`;
    });

    html += `</div>`;

    // Pagination controls
    html += `
        <div class="pagination">
            <button id="prev-page" ${currentPage === 1 ? 'disabled' : ''}>Previous</button>
            <span>Page ${currentPage}</span>
            <button id="next-page" ${currentPage * itemsPerPage >= data.length ? 'disabled' : ''}>Next</button>
        </div>
    `;

    resultsEl.innerHTML = html;

    // Pagination button events
    document.getElementById('prev-page').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderTable(data); // Re-render with updated page
        }
    });

    document.getElementById('next-page').addEventListener('click', () => {
        if (currentPage * itemsPerPage < data.length) {
            currentPage++;
            renderTable(data); // Re-render with updated page
        }
    });
}

    if (data.length === 0) {
        resultsEl.innerHTML = '<p>Geen gegevens gevonden.</p>';
        return;
    }

    // Paginate the data
    const paginatedData = data.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

    let html = `<div class="hit-lists-header-info">
        <h3>Hier is de ${selectYear.value} Indie500</h3>
        <div class="hit-lists-count">${data.length} titels</div>
    </div>
    <div class="hit-lists-results-simple">`;

    paginatedData.forEach((item, index) => {
        html += `<div class="hit-lists-result-item">
            <span class="rank">${(currentPage - 1) * itemsPerPage + index + 1}</span> – 
            <span class="artist">${item.artist_name}</span> – 
            <span class="title">${item.song_title}</span>
        </div>`;
    });

    html += `</div>`;

    // Pagination controls
    html += `
        <div class="pagination">
            <button id="prev-page" ${currentPage === 1 ? 'disabled' : ''}>Previous</button>
            <span>Page ${currentPage}</span>
            <button id="next-page" ${currentPage * itemsPerPage >= data.length ? 'disabled' : ''}>Next</button>
        </div>
    `;

    resultsEl.innerHTML = html;

    // Pagination button events
    document.getElementById('prev-page').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderTable(data); // Re-render with updated page
        }
    });

    document.getElementById('next-page').addEventListener('click', () => {
        if (currentPage * itemsPerPage < data.length) {
            currentPage++;
            renderTable(data); // Re-render with updated page
        }
    });

