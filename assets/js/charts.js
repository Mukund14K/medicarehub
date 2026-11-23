/**
 * MediCare Hub - Enterprise Clinical Charts (Chart.js)
 * Flat presentation, no animations, no gradients, clinical monochrome palette.
 */
document.addEventListener('DOMContentLoaded', function () {
    const chartData = window.chartData;
    if (!chartData) return;

    const fontFamily = "'IBM Plex Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    const accentColor = '#7A1C28';
    const borderColor = '#DADFE6';
    const textSecondary = '#5B6675';

    // ──────────────────────────────────────────────
    // 1. Bar Chart: Appointments by Specialist
    // Single color: --accent (#7A1C28 Maroon), flat, no animation
    // ──────────────────────────────────────────────
    const appointmentsCtx = document.getElementById('appointmentsChart');
    if (appointmentsCtx) {
        const doctorLabels = chartData.doctors || [];
        const doctorCounts = chartData.doctorCounts || [];

        new Chart(appointmentsCtx, {
            type: 'bar',
            data: {
                labels: doctorLabels,
                datasets: [{
                    label: 'Appointments',
                    data: doctorCounts,
                    backgroundColor: accentColor,
                    borderColor: 'transparent',
                    borderWidth: 0,
                    borderRadius: 4,
                    borderSkipped: false,
                    maxBarThickness: 32
                }]
            },
            options: {
                animation: false,
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        titleFont: { family: fontFamily, size: 12 },
                        bodyFont: { family: fontFamily, size: 12 },
                        cornerRadius: 4,
                        padding: 8
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            font: { family: fontFamily, size: 12 },
                            color: textSecondary
                        },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: { family: fontFamily, size: 12 },
                            color: textSecondary,
                            precision: 0
                        },
                        grid: {
                            color: borderColor,
                            drawBorder: false
                        }
                    }
                }
            }
        });
    }

    // ──────────────────────────────────────────────
    // 2. Status Chart: Horizontal Bar Chart
    // Colors: #7A1C28 (Scheduled), #2E6B4F (Completed), #A12C2C (Cancelled)
    // ──────────────────────────────────────────────
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        const statusLabels = ['Scheduled', 'Completed', 'Cancelled'];
        const statusCounts = chartData.statusCounts || [0, 0, 0];
        const statusColors = ['#7A1C28', '#2E6B4F', '#A12C2C'];

        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: statusLabels,
                datasets: [{
                    label: 'Count',
                    data: statusCounts,
                    backgroundColor: statusColors,
                    borderRadius: 4,
                    borderSkipped: false,
                    maxBarThickness: 24
                }]
            },
            options: {
                indexAxis: 'y',
                animation: false,
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        titleFont: { family: fontFamily, size: 12 },
                        bodyFont: { family: fontFamily, size: 12 },
                        cornerRadius: 4,
                        padding: 8
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            font: { family: fontFamily, size: 12 },
                            color: textSecondary,
                            precision: 0
                        },
                        grid: {
                            color: borderColor,
                            drawBorder: false
                        }
                    },
                    y: {
                        ticks: {
                            font: { family: fontFamily, size: 12 },
                            color: textSecondary
                        },
                        grid: { display: false }
                    }
                }
            }
        });
    }
});
