'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const searchForm = document.querySelector('[data-search-form]');
    if (searchForm) {
        searchForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const type = searchForm.querySelector('[name="search_type"]:checked')?.value;
            const classSelect = searchForm.querySelector('[name="kelas_id"]');
            const lecturerSelect = searchForm.querySelector('[name="dosen_id"]');
            if (type === 'kelas' && classSelect?.value) {
                window.location.href = 'detail_kelas.php?id=' + encodeURIComponent(classSelect.value);
                return;
            }
            if (type === 'dosen' && lecturerSelect?.value) {
                window.location.href = 'detail_dosen.php?id=' + encodeURIComponent(lecturerSelect.value);
                return;
            }
            window.alert('Silakan pilih data terlebih dahulu.');
        });
        const updateSearchMode = () => {
            const type = searchForm.querySelector('[name="search_type"]:checked')?.value;
            document.querySelector('[data-kelas-field]')?.classList.toggle('d-none', type !== 'kelas');
            document.querySelector('[data-dosen-field]')?.classList.toggle('d-none', type !== 'dosen');
        };
        searchForm.querySelectorAll('[name="search_type"]').forEach((radio) => radio.addEventListener('change', updateSearchMode));
        updateSearchMode();
    }
});

function createChart(canvasId, config) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || typeof Chart === 'undefined') {
        return null;
    }
    return new Chart(canvas, config);
}
