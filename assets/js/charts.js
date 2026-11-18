/**
 * MediCare Hub - Dashboard Charts (Chart.js)
 * Reads data from window.chartData set by the dashboard PHP.
 */
document.addEventListener('DOMContentLoaded', function () {

    const chartData = window.chartData;
    if (!chartData) return;

    // Theme color palette for the bar chart
    const themePalette = [
        '#0F6CBD', '#16A34A', '#9333EA', '#EA580C',
        '#0891B2', '#D97706', '#DC2626', '#4F46E5',
        '#059669', '#BE185D', '#7C3AED', '#2563EB'
    ];

    const fontFamily = "'Inter', sans-serif";

    // ──────────────────────────────────────────────
    // 1. Bar Chart – Appointments per Doctor
    // ──────────────────────────────────────────────
    const appointmentsCtx = document.getElementById('appointmentsChart');
    if (appointmentsCtx) {
        const doctorLabels = chartData.doctors || [];
        const doctorCounts = chartData.doctorCounts || [];

        // Assign colors from palette, cycling if more doctors than colors
        const barColors = doctorLabels.map(function (_, i) {
            return themePalette[i % themePalette.length];
        });

        new Chart(appointmentsCtx, {
            type: 'bar',
            data: {
                labels: doctorLabels,
                datasets: [{
                    label: 'Appointments',
                    data: doctorCounts,
                    backgroundColor: barColors,
                    borderColor: 'transparent',
                    borderWidth: 0,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        titleFont: { family: fontFamily },
                        bodyFont: { family: fontFamily }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            font: { family: fontFamily, size: 12 }
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: { family: fontFamily, size: 12 },
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.06)'
                        }
                    }
                }
            }
        });
    }

    // ──────────────────────────────────────────────
    // 2. Doughnut Chart – Appointments by Status
    // ──────────────────────────────────────────────
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        const statusLabels = ['Scheduled', 'Completed', 'Cancelled'];
        const statusCounts = chartData.statusCounts || [0, 0, 0];
        const statusColors = ['#0F6CBD', '#16A34A', '#DC2626'];

        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: statusColors,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: {
                                family: fontFamily,
                                size: 13
                            }
                        }
                    },
                    tooltip: {
                        titleFont: { family: fontFamily },
                        bodyFont: { family: fontFamily }
                    }
                },
                scales: {} // No grid lines for doughnut
            }
        });
    }

});
