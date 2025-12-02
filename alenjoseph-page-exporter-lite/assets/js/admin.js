/**
 * Page Exporter Lite Admin JavaScript
 */

jQuery(document).ready(function($) {
    
  // Handle export link clicks
  $('.pel-export-link').on('click', function(e) {
      var $link = $(this);
      var originalText = $link.text();
      
      // Add loading state
      $link.addClass('loading');
      $link.text(pel_ajax.exporting);
      
      // The actual export happens through the PHP redirect
      // We'll restore the link after a short delay in case of errors
      setTimeout(function() {
          $link.removeClass('loading');
          $link.text(originalText);
      }, 3000);
  });
  
  // Handle any AJAX responses if needed in future versions
  $(document).on('click', '.pel-export-link', function(e) {
      // Additional handling can be added here
      console.log('Page Exporter Lite: Export initiated');
  });
  
  // Add confirmation for bulk operations (future feature)
  $('.pel-bulk-export').on('click', function(e) {
      var selectedPages = $('.wp-list-table input[type="checkbox"]:checked').length;
      
      if (selectedPages === 0) {
          alert('Please select at least one page to export.');
          e.preventDefault();
          return false;
      }
      
      var message = selectedPages === 1 ? 
          'Are you sure you want to export this page?' : 
          'Are you sure you want to export these ' + selectedPages + ' pages?';
          
      if (!confirm(message)) {
          e.preventDefault();
          return false;
      }
  });
  
  // Keyboard accessibility
  $('.pel-export-link').on('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          $(this).trigger('click');
      }
  });
  
  // Visual feedback for successful exports
  function showExportSuccess(message) {
      var $notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
      $('.wp-header-end').after($notice);
      
      // Auto-hide after 5 seconds
      setTimeout(function() {
          $notice.fadeOut();
      }, 5000);
  }
  
  // Visual feedback for export errors
  function showExportError(message) {
      var $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
      $('.wp-header-end').after($notice);
      
      // Auto-hide after 10 seconds
      setTimeout(function() {
          $notice.fadeOut();
      }, 10000);
  }
  
  // Check for URL parameters to show feedback
  var urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('pel_export') === 'success') {
      showExportSuccess(pel_ajax.export_complete);
  } else if (urlParams.get('pel_export') === 'error') {
      showExportError(pel_ajax.export_error);
  }
  
  // Tooltip for export links
  $('.pel-export-link').attr('title', 'Export this page as XML file');
  
  // Add ARIA labels for accessibility
  $('.pel-export-link').attr('aria-label', function() {
      var pageTitle = $(this).closest('tr').find('.row-title').text();
      return 'Export page: ' + pageTitle;
  });
  
});