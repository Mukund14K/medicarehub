/**
 * MediCare Hub - Booking Page JavaScript
 * Handles specialization-based doctor fetch, doctor selection,
 * stepper navigation, date restrictions, and form validation.
 */
document.addEventListener('DOMContentLoaded', function () {

    const specializationSelect = document.getElementById('specialization');
    const doctorContainer = document.getElementById('doctorContainer');
    const doctorIdInput = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const bookingForm = document.getElementById('bookingForm');

    // ──────────────────────────────────────────────
    // 1. Fetch Doctors on Specialization Change
    // ──────────────────────────────────────────────
    if (specializationSelect) {
        specializationSelect.addEventListener('change', function () {
            const specialization = this.value;

            // Clear previous selection
            if (doctorContainer) {
                doctorContainer.innerHTML = '';
            }
            if (doctorIdInput) {
                doctorIdInput.value = '';
            }

            if (!specialization) return;

            // Show loading state
            if (doctorContainer) {
                doctorContainer.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading doctors...</p></div>';
            }

            fetch('/medicarehub/patient/get_doctors.php?specialization=' + encodeURIComponent(specialization))
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(function (doctors) {
                    if (doctorContainer) {
                        doctorContainer.innerHTML = '';
                    }

                    if (!doctors || doctors.length === 0) {
                        doctorContainer.innerHTML = '<div class="text-center py-4"><p class="text-muted">No doctors available for this specialization.</p></div>';
                        return;
                    }

                    doctors.forEach(function (doctor) {
                        const card = createDoctorCard(doctor);
                        doctorContainer.appendChild(card);
                    });
                })
                .catch(function (error) {
                    console.error('Error fetching doctors:', error);
                    if (doctorContainer) {
                        doctorContainer.innerHTML = '<div class="text-center py-4"><p class="text-danger">Failed to load doctors. Please try again.</p></div>';
                    }
                });
        });
    }

    // ──────────────────────────────────────────────
    // 2. Create Doctor Card Element
    // ──────────────────────────────────────────────
    function createDoctorCard(doctor) {
        const col = document.createElement('div');
        col.classList.add('col-md-6', 'col-lg-4', 'mb-3');

        const card = document.createElement('div');
        card.classList.add('card', 'doctor-card', 'h-100');
        card.setAttribute('data-doctor-id', doctor.id);

        card.innerHTML =
            '<div class="card-body text-center">' +
                '<div class="doctor-avatar mb-3">' +
                    '<i class="bi bi-person-badge fs-1 text-primary"></i>' +
                '</div>' +
                '<h5 class="card-title mb-1">' + escapeHtml(doctor.name) + '</h5>' +
                '<p class="card-text text-muted mb-3">' + escapeHtml(doctor.specialization) + '</p>' +
                '<button type="button" class="btn btn-outline-primary btn-sm select-doctor-btn">Select</button>' +
            '</div>';

        // Handle card click / Select button click
        card.addEventListener('click', function () {
            selectDoctor(doctor.id, card);
        });

        return col.appendChild(card) && col;
    }

    // ──────────────────────────────────────────────
    // 3. Select Doctor
    // ──────────────────────────────────────────────
    function selectDoctor(doctorId, cardElement) {
        // Remove highlight from all doctor cards
        const allCards = document.querySelectorAll('.doctor-card');
        allCards.forEach(function (c) {
            c.classList.remove('selected');
        });

        // Highlight the selected card
        cardElement.classList.add('selected');

        // Set hidden input value
        if (doctorIdInput) {
            doctorIdInput.value = doctorId;
        }

        // Advance stepper if applicable
        updateStepper(2);
    }

    // ──────────────────────────────────────────────
    // 4. Stepper Navigation
    // ──────────────────────────────────────────────
    function updateStepper(currentStep) {
        const steps = document.querySelectorAll('.stepper .step');
        steps.forEach(function (step, index) {
            const stepNumber = index + 1;
            step.classList.remove('active', 'completed');

            if (stepNumber < currentStep) {
                step.classList.add('completed');
            } else if (stepNumber === currentStep) {
                step.classList.add('active');
            }
        });
    }

    // Initialize stepper to step 1
    updateStepper(1);

    // ──────────────────────────────────────────────
    // 5. Date Input – Prevent Past Dates
    // ──────────────────────────────────────────────
    if (dateInput) {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const minDate = year + '-' + month + '-' + day;

        dateInput.setAttribute('min', minDate);

        dateInput.addEventListener('change', function () {
            if (this.value) {
                updateStepper(3);
            }
        });
    }

    // ──────────────────────────────────────────────
    // 6. Form Submit Validation
    // ──────────────────────────────────────────────
    if (bookingForm) {
        bookingForm.addEventListener('submit', function (event) {
            let isValid = true;
            const requiredFields = bookingForm.querySelectorAll('[required]');

            requiredFields.forEach(function (field) {
                if (!field.value || field.value.trim() === '') {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            // Check hidden doctor_id
            if (doctorIdInput && (!doctorIdInput.value || doctorIdInput.value.trim() === '')) {
                isValid = false;
                if (doctorContainer) {
                    doctorContainer.classList.add('border', 'border-danger', 'rounded', 'p-2');
                }
                alert('Please select a doctor before booking.');
            } else if (doctorContainer) {
                doctorContainer.classList.remove('border', 'border-danger', 'rounded', 'p-2');
            }

            if (!isValid) {
                event.preventDefault();
                event.stopPropagation();
            }

            bookingForm.classList.add('was-validated');
        });
    }

    // ──────────────────────────────────────────────
    // Utility: Escape HTML to prevent XSS
    // ──────────────────────────────────────────────
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

});
