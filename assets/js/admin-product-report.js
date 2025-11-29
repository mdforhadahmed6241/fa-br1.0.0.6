/**
 * Business Report - Product Report Admin JS
 *
 * Handles date filter modal for all product report tabs.
 */
jQuery(function($) {
    'use strict';

    const modals = {
        customRange: $('#br-product-custom-range-filter-modal')
    };

    function openModal(modal) {
        // Ensure datepicker is initialized
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
            // Check if this modal is the product report modal
            if ($(this).is('#br-product-custom-range-filter-modal')) {
                 closeModal($(this));
            }
        }
    });
    
    // Dropdown Toggle
    wrapper.on('click', '.br-dropdown-toggle', function(e) {
        e.preventDefault();
        $(this).next('.br-dropdown-menu').fadeToggle(100);
    });

    // Open Custom Range Filter Modal
    wrapper.on('click', '#br-product-custom-range-trigger', function(e) {
        e.preventDefault();
        
        // Find the active tab in the *product report* page
        const activeTabLink = $('.nav-tab-wrapper a[href*="page=br-product-report"].nav-tab-active').attr('href');
        let currentTab = 'summary'; // Default
        
        if (activeTabLink) {
            const urlParams = new URLSearchParams(activeTabLink.split('?')[1]);
            if (urlParams.has('tab')) {
                currentTab = urlParams.get('tab');
            }
        }
        
        // Set the hidden tab input value in the modal form
        modals.customRange.find('input[name="tab"]').val(currentTab);
        
        // Close dropdown
        $(this).closest('.br-dropdown-menu').fadeOut(100); 
        
        // Open the modal
        openModal(modals.customRange);
    });

});
