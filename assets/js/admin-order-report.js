/**
 * Business Report - Order Report Admin JS
 */
jQuery(function($) {
    'use strict';

    const modals = {
        customRange: $('#br-order-custom-range-filter-modal')
    };

    function openModal(modal) {
        const datepicker = modal.find('.br-datepicker');
        if (datepicker.length && !datepicker.hasClass('hasDatepicker')) {
            datepicker.datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
                onSelect: function(dateText) {
                    $(this).val(dateText);
                }
            });
        }
        modal.fadeIn(200);
    }

    function closeModal(modal) {
        modal.fadeOut(200);
    }

    // --- Event Delegation ---
    const wrapper = $('.br-wrap');

    // Close modal on background click or close button
    wrapper.on('click', '.br-modal', function(e) {
        if ($(e.target).is('.br-modal') || $(e.target).is('.br-modal-close') || $(e.target).is('.br-modal-cancel')) {
            closeModal($(this));
        }
    });
    
    // Dropdown Toggle
    wrapper.on('click', '.br-dropdown-toggle', function(e) {
        e.preventDefault();
        $(this).next('.br-dropdown-menu').fadeToggle(100);
    });

    // Open Custom Range Filter Modal
    wrapper.on('click', '#br-order-custom-range-trigger', function(e) {
        e.preventDefault();
        const activeTabLink = $('.nav-tab-wrapper .nav-tab-active').attr('href');
        let currentTab = 'summary'; // Default
        if (activeTabLink) {
            // FIX: Changed activeTLink to activeTabLink
            const urlParams = new URLSearchParams(activeTabLink.split('?')[1]);
            if (urlParams.has('tab')) {
                currentTab = urlParams.get('tab');
            }
        }
        modals.customRange.find('input[name="tab"]').val(currentTab);
        $('.br-dropdown-menu').fadeOut(100); // Close dropdown
        openModal(modals.customRange);
    });

    // NEW: Render Summary Bar Chart
    function renderSummaryChart() {
        const ctx = document.getElementById('br-summary-chart-canvas');
        if (!ctx || typeof Chart === 'undefined' || typeof br_summary_chart_data === 'undefined') {
            return;
        }

        new Chart(ctx, {
            type: 'bar',
            data: br_summary_chart_data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false // Legend is handled in HTML
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    // Format as currency (using BDT, you can change this)
                                    // Using 'en-US' locale to ensure Intl works, but with 'BDT' currency code.
                                    // A more robust solution might need a currency symbol passed from PHP.
                                    label += '৳' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false // Remove X-axis grid lines
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f3f4f6' // Light grid lines for Y-axis
                        },
                        ticks: {
                            callback: function(value, index, ticks) {
                                // Shorten large numbers (e.g., 10000 -> 10k)
                                if (value >= 1000000) {
                                    return (value / 1000000) + 'm';
                                }
                                if (value >= 1000) {
                                    return (value / 1000) + 'k';
                                }
                                return value;
                            }
                        }
                    }
                }
            }
        });
    }

    // Run on page load
    renderSummaryChart();

});

