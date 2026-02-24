/**
 * Admin Portal JavaScript
 * Frontend dashboard functionality for CIG Admin Portal
 *
 * @package CIG
 * @since 4.5.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Tab switching functionality
        initTabSwitching();
        
        // Initialize lazy loading for Statistics tab
        initLazyLoading();
    });

    /**
     * Initialize tab switching
     */
    function initTabSwitching() {
        $('.cig-portal-card').on('click', function() {
            var tab = $(this).data('tab');
            
            // Update active card
            $('.cig-portal-card').removeClass('active');
            $(this).addClass('active');
            
            // Show selected tab content
            $('.cig-portal-tab').hide();
            $('#cig-portal-tab-' + tab).fadeIn(300);
            
            // Trigger resize event for charts
            if (tab === 'statistics') {
                $(window).trigger('resize');
            }
        });
    }

    /**
     * Initialize lazy loading for content that requires initialization
     */
    function initLazyLoading() {
        // Statistics tab may need re-initialization when shown
        var statisticsInitialized = false;
        
        $(document).on('click', '.cig-portal-card[data-tab="statistics"]', function() {
            if (!statisticsInitialized && typeof cigStats !== 'undefined') {
                // Trigger resize to properly size charts
                setTimeout(function() {
                    $(window).trigger('resize');
                }, 500);
                statisticsInitialized = true;
            }
        });
    }

})(jQuery);
