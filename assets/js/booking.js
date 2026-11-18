/**
 * MediCare Hub - Appointment Booking Script
 * Enterprise tabular doctor selection and date validation
 */
document.addEventListener('DOMContentLoaded', function () {
    const specializationSelect = document.getElementById('specialization');
    const doctorContainer = document.getElementById('doctorContainer');
    const doctorIdInput = document.getElementById('doctor_id');
    const appointmentDateInput = document.getElementById('appointment_date');

    if (!specializationSelect || !doctorContainer) return;

    specializationSelect.addEventListener('change', function () {
        const specialization = this.value;

        // Reset doctor selection
        if (doctorIdInput) doctorIdInput.value = '';
        doctorContainer.innerHTML = '';

        if (!specialization) {
            doctorContainer.innerHTML = '<div class="empty-state">Select a specialization to view available doctors.</div>';
            return;
        }

        doctorContainer.innerHTML = '<div class="empty-state">Loading available specialists...</div>';

        fetch('/medicarehub/patient/get_doctors.php?specialization=' + encodeURIComponent(specialization))
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.json();
            })
            .then(doctors => {
                if (!doctors || doctors.length === 0) {
                    doctorContainer.innerHTML = '<div class="empty-state">No doctors currently registered under this specialization.</div>';
                    return;
                }

                let html = '<div class="table-container"><table class="table">';
                html += '<thead><tr><th>Doctor name</th><th>Specialization</th><th>Phone</th><th class="text-end">Action</th></tr></thead>';
                html += '<tbody>';

                doctors.forEach(doc => {
                    html += `<tr id="doctor-row-${doc.id}">
                        <td class="fw-medium">${escapeHtml(doc.name)}</td>
                        <td>${escapeHtml(doc.specialization)}</td>
                        <td class="tabular-nums">${escapeHtml(doc.phone)}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-secondary btn-sm select-btn" data-id="${doc.id}" data-name="${escapeHtml(doc.name)}">Select</button>
                        </td>
                    </tr>`;
                });

                html += '</tbody></table></div>';
                doctorContainer.innerHTML = html;

                // Bind selection click
                const buttons = doctorContainer.querySelectorAll('.select-btn');
                buttons.forEach(btn => {
                    btn.addEventListener('click', function () {
                        const id = this.getAttribute('data-id');
                        doctorIdInput.value = id;

                        // Reset all rows & buttons
                        doctorContainer.querySelectorAll('tbody tr').forEach(r => r.classList.remove('table-selected'));
                        buttons.forEach(b => {
                            b.textContent = 'Select';
                            b.classList.remove('btn-primary');
                            b.classList.add('btn-secondary');
                        });

                        // Highlight selected
                        const row = document.getElementById('doctor-row-' + id);
                        if (row) row.classList.add('table-selected');
                        this.textContent = 'Selected';
                        this.classList.remove('btn-secondary');
                        this.classList.add('btn-primary');
                    });
                });
            })
            .catch(err => {
                console.error(err);
                doctorContainer.innerHTML = '<div class="empty-state text-danger">Failed to load specialists. Please try again.</div>';
            });
    });

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
});
