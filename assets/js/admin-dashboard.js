/**
 * Business Report Dashboard Scripts
 */
jQuery(document).ready(function($) {
    
    // Ensure Chart.js and data exist
    if (typeof Chart === 'undefined' || typeof br_dashboard_charts === 'undefined') {
        return;
    }

    // 1. Orders Chart (Bar)
    const orderCtx = document.getElementById('br-orders-chart');
    if (orderCtx) {
        new Chart(orderCtx, {
            type: 'bar',
            data: {
                labels: br_dashboard_charts.orders.labels,
                datasets: [{
                    label: 'Orders',
                    data: br_dashboard_charts.orders.data,
                    backgroundColor: '#059669', // Greenish teal
                    borderRadius: 4,
                    barThickness: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        grid: { display: true, borderDash: [2, 4], color: '#F3F4F6' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // 2. Profit vs Ad Cost Chart (Line)
    const profitCtx = document.getElementById('br-profit-ad-chart');
    if (profitCtx) {
        new Chart(profitCtx, {
            type: 'line',
            data: {
                labels: br_dashboard_charts.profit_ad.labels,
                datasets: [
                    {
                        label: 'Net Profit',
                        data: br_dashboard_charts.profit_ad.profit,
                        borderColor: '#10B981', // Green
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 0
                    },
                    {
                        label: 'Ad Cost',
                        data: br_dashboard_charts.profit_ad.ads,
                        borderColor: '#EF4444', // Red
                        borderWidth: 2,
                        borderDash: [5, 5],
                        tension: 0.4,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { boxWidth: 10, usePointStyle: true } }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        grid: { display: true, borderDash: [2, 4], color: '#F3F4F6' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
});