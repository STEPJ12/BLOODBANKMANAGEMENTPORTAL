/**
 * Blood Bank Portal - Charts JavaScript
 *
 * This file contains the chart configurations for the dashboard.
 */

// Chart.js global configuration
Chart.defaults.font.family = "'Segoe UI', 'Helvetica Neue', 'Arial', sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#6c757d';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0, 0, 0, 0.7)';
Chart.defaults.plugins.tooltip.titleFont = { weight: 'bold' };
Chart.defaults.plugins.tooltip.bodyFont = { size: 12 };
Chart.defaults.plugins.tooltip.displayColors = true;
Chart.defaults.plugins.tooltip.usePointStyle = true;
Chart.defaults.plugins.tooltip.boxPadding = 5;

/**
 * Initialize Blood Inventory Chart
 *
 * @param {string} canvasId - Canvas element ID
 * @param {object} data - Chart data
 * @param {string} role - User role (for color scheme)
 */
function initBloodInventoryChart(canvasId, data, role = 'redcross') {
    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (!ctx) return;

    // Set colors based on role
    const primaryColor = role === 'negrosfirst' ? 'rgba(40, 167, 69, 0.8)' : 'rgba(220, 53, 69, 0.8)';
    const secondaryColor = role === 'negrosfirst' ? 'rgba(23, 162, 184, 1)' : 'rgba(40, 167, 69, 1)';

    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Current units',
                data: data.current,
                backgroundColor: primaryColor,
                borderColor: primaryColor.replace('0.8', '1'),
                borderWidth: 1,
                borderRadius: 4,
                barPercentage: 0.6,
                categoryPercentage: 0.7
            }, {
                label: 'Target units',
                data: data.target,
                type: 'line',
                fill: false,
                borderColor: secondaryColor,
                borderWidth: 2,
                pointBackgroundColor: secondaryColor,
                pointRadius: 4,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'units'
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += context.parsed.y + ' units';
                            return label;
                        }
                    }
                }
            }
        }
    });

    return chart;
}

/**
 * Initialize Donation History Chart
 *
 * @param {string} canvasId - Canvas element ID
 * @param {object} data - Chart data
 */
function initDonationHistoryChart(canvasId, data) {
    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (!ctx) return;

    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'units Donated',
                data: data.values,
                backgroundColor: 'rgba(220, 53, 69, 0.2)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 2,
                borderRadius: 5,
                borderSkipped: false,
                barPercentage: 0.6,
                categoryPercentage: 0.7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        title: function(tooltipItems) {
                            return tooltipItems[0].label;
                        },
                        label: function(context) {
                            let value = context.parsed.y;
                            return value + (value === 1 ? ' unit' : ' units') + ' donated';
                        }
                    }
                }
            }
        }
    });

    return chart;
}

/**
 * Initialize Donation Trends Chart
 *
 * @param {string} canvasId - Canvas element ID
 * @param {object} data - Chart data
 * @param {string} role - User role (for color scheme)
 */
function initDonationTrendsChart(canvasId, data, role = 'redcross') {
    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (!ctx) return;

    // Set colors based on role
    const primaryColor = role === 'negrosfirst' ? 'rgba(40, 167, 69, 0.8)' : 'rgba(220, 53, 69, 0.8)';

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Donations',
                data: data.values,
                fill: true,
                backgroundColor: primaryColor.replace('0.8', '0.1'),
                borderColor: primaryColor.replace('0.8', '1'),
                borderWidth: 2,
                tension: 0.4,
                pointBackgroundColor: primaryColor.replace('0.8', '1'),
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    return chart;
}

/**
 * Initialize Blood Type Distribution Chart
 *
 * @param {string} canvasId - Canvas element ID
 * @param {object} data - Chart data
 */
function initBloodTypeDistributionChart(canvasId, data) {
    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (!ctx) return;

    const chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: [
                    'rgba(220, 53, 69, 0.8)',
                    'rgba(220, 53, 69, 0.7)',
                    'rgba(220, 53, 69, 0.6)',
                    'rgba(220, 53, 69, 0.5)',
                    'rgba(220, 53, 69, 0.4)',
                    'rgba(220, 53, 69, 0.3)',
                    'rgba(220, 53, 69, 0.2)',
                    'rgba(220, 53, 69, 0.1)'
                ],
                borderColor: 'white',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 15,
                        padding: 15
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            let value = context.parsed || 0;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} units (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });

    return chart;
}
