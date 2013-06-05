(function ($) {

/**
 * This behavior will call to Acquia Purge's AJAX path until its purging queue
 * is empty and everything is processed. Also works without on-screen reporting.
 */
Drupal.behaviors.AcquiaPurgeAjax = {
  attach: function (context) {
    $(document).ready(function() {

      // Declare the trigger path the script will call back home to.
      triggerPath = Drupal.settings.basePath + 'acquia_purge_ajax_processor';

      // Declare reference variables to the main container and its elements.
      apbox = '.acquia_purge_messages';
      apbox_errors = '#aperror';
      apbox_widget = '#apwidget';
      apbox_log = '#aplog';

      // Determine if on-screen reporting is enabled or not.
      function uiActivated() {
        if ($(apbox).length > 0) {
          return true;
        }
        return false;
      }

      // Prepare the container element with extra structures that we will need.
      function uiInitialize() {

        // Set a element reference to the apbox container.
        apbox = $(apbox);

        // Wrap the currently existing HTML string into apbox_widget.
        apbox.html("<div id='apwidget'>" + apbox.html() + "</div>");
        apbox_widget = apbox.find(apbox_widget);

        // Prepend the hidden errors container and reference it.
        apbox.prepend("<div id='aperror' style='display: none;' "
          + "class='messages error'>&nbsp;</div>");
        apbox_errors = apbox.find(apbox_errors);

        // Append the unordered purge log to the container and reference it.
        apbox.append("<div id='aplog' style='display: none;' "
          + "><ul></ul></div>");
        apbox_log = apbox.find(apbox_log);
      }

      // Set a visual error in the error container.
      function uiError(message) {
        message = typeof message !== 'undefined' ? message : false;
        message_old = apbox_errors.html();

        // If message wasn't passed we hide and delete the current message.
        if (!message) {
          apbox_errors.slideUp('slow');
          apbox_errors.html('&nbsp;');
          return;
        }

        // Don't do anything when the new and old messages are the same.
        if (message == message_old) {
          return;
        }

        // When the box is invisible: set the message and unfold.
        if (!apbox_errors.is(':visible')) {
          apbox_errors.html(message);
          apbox_errors.slideDown('slow');
          return;
        }

        // The only resulting case is a message replacement, handle nicely.
        apbox_errors.html("<div class='apo'>" + apbox_errors.html() + "</div>");
        apbox_errors.append("<div class='apn' style='display:none;'>"
          + message + "</div>");
        apbox_errors.find('.apo').slideUp('slow');
        apbox_errors.find('.apn').slideDown('slow', function() {
          apbox_errors.html(message);
        });
      }

      // Enable the throbber on the widget container.
      function uiThrobberOn() {
        throbber_path = Drupal.settings.basePath + 'misc/throbber.gif';
        throbber = apbox.find('#apthrobr');

        // Create the throbber when it doesn't exist.
        if (!($(throbber).length > 0)) {
          apbox_errors.after("<div id='apthrobr' style='display:"
            + "none;'>&nbsp;</div>");
          throbber = apbox.find('#apthrobr');
          throbber.css('background-image', 'url('+ throbber_path +')');
          throbber.css('background-color', 'transparent');
          throbber.css('background-position', '0px -18px');
          throbber.css('background-repeat', 'no-repeat');
          throbber.css('margin-top', '-21px');
          throbber.css('position', 'relative');
          throbber.css('z-index', '1');
          throbber.css('height', '18px;');
          throbber.css('width', '18px');
          throbber.css('top', '1.8em');
          throbber.css('left', '-2.2em');
        }

        // Then just make it visible.
        throbber.fadeIn(1000);
      }

      // Disable the throbber on the widget container.
      function uiThrobberOff() {
        throbber = apbox.find('#apthrobr');
        throbber.fadeOut(1000);
      }

      // Add new items to the purge log history widget.
      function uiLogHistory(purges) {
        list_items_limit = 10;
        list_items = apbox_log.find('ul');

        // Slowly slide the widget on screen once purges are logged.
        if (!apbox_log.is(':visible')) {
          apbox_log.show();
        }

        // Iterate each URL and append it to the list items.
        $.each(purges, function( key, url ) {

          // Check the existing list and add the item if its new to us.
          alreadyInList = false;
          list_items.find('li').each(function(index) {
            if ($(this).text() == url) {alreadyInList = true;}
          });

          // Addition logic when the item is indeed unique.
          if (!alreadyInList) {

            // List quota reached, skip effects.
            if (list_items.find('li').length == list_items_limit) {
              list_items.find('li').first().remove();
              list_items.append("<li>"+ url +'</li>');
              list_items.find('li').last().css('list-style', 'none');
            }
            else {
              list_items.append("<li style='display:none;'>"+ url +'</li>');
              list_items.find('li').last().css('list-style', 'none');
              list_items.find('li').last().slideDown(1000);
            }
          }
        });
      }

      // Build off the log history viewer and hide its items.
      function uiLogHistoryHide() {
        apbox_log.fadeTo(4000, 0).slideUp(2000);
      }

      // Tear the user interface down and hide it for the user.
      function uiTearDown() {
        uiThrobberOff();
        uiLogHistoryHide();
        uiError();

        // Hide ourselves after 10 seconds.
        setTimeout(function() {apbox.slideUp(3000);}, 10000);
      }

      // Make a request back home and trigger a couple of purges each run.
      function eventLoopRun() {

        // Initialize the throbber.
        if (uiActivated()) {
          uiThrobberOn();
        }

        // Start a recursive call and call ourselves upon success.
        $.ajax({
          url: triggerPath,
          cache: false,
          dataType: "json",
          context: document.body,
          success: function(data) {

            // Replace the inner text with the loaded widget.
            $(apbox_widget).html(data['widget']);

            // Disable the throbber since we're done again.
            if (uiActivated() && (!data['locked'])) {
              uiThrobberOff();
            }

            // Handle error conditions and remove errors when they are gone.
            if (uiActivated() && data['error']) {
              uiError(data['error']);
            }
            else if (uiActivated() && (!data['error'])) {
              uiError();
            }

            // Report successfully purged URLs to the GUI's logging widget.
            if (uiActivated() && (data['purgehistory'].length > 0)) {
              uiLogHistory(data['purgehistory']);
            }

            // Follow up a next request with a 2 seconds pause.
            if (data['running']) {
              if (data['locked']) {
                setTimeout(function() {eventLoopRun();}, 10000);
              }
              else {
                setTimeout(function() {eventLoopRun();}, 1000);
              }
            }

            // Start building off the interface since the work is done.
            else {
              if (uiActivated()) {
                uiTearDown();
              }
            }
          },
          error: function(request) {

            // Report the error occurred and tear the UI partly down.
            if (uiActivated()) {
              uiThrobberOff();
              uiLogHistoryHide();
              message = "Something went wrong while communicating with the "
                + "server. Last known response was '"+ request['statusText']
                +"' with HTTP code "+ request['status'] +".";
              uiError(message);
            }
          }
        });
      }

      // Initialize the UI when we have detected its base element.
      if (uiActivated()) {
        uiInitialize();
      }

      // Start the cascade of purge events until its marked finished.
      eventLoopRun();
    });
  }
};

})(jQuery);
