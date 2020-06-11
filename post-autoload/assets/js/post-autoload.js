(function() {
  'use strict';

  if (!jQuery(autoload.main_element).length) {
    alert('Post Autolad - unable to locate main entry - check your settings');
  } else {
    
    let height = jQuery(autoload.main_element).prop('scrollHeight'); //Main document wrapper height
    let callInitiated = false; //Ajax is initiated
    let sections = new Array(); //All dynamically added articles holder

    let main = [
      jQuery(autoload.main_element).offset(),
      jQuery(autoload.main_element).offset().top+jQuery(autoload.main_element).outerHeight( true ),
      -1,
      window.location.href,
      jQuery(document).find("title").text()
    ];

    sections.push(main); // Add the main document to the array

    jQuery(window).scroll(function scrollHandler() {


      let scrollPosition = document.documentElement.scrollTop || document.body.scrollTop;

      if (!callInitiated && scrollPosition > (height-300)) {
        // The point is reached - detach event and make the call
        jQuery(window).off("scroll", scrollHandler);
          callInitiated = true;
          jQuery.get( autoload.ajax_url+'?action=load_next_post&post='+autoload.post_id, 
            function( data ) {
              let response = jQuery.parseJSON( data );

              if (response.error) {
                alert(response.error);
                return;
              }

              if (!response.last) {
                jQuery(autoload.main_element).append( '<div class="section" style="clear:both">'+response.content+'</div>' );
                height = jQuery( autoload.main_element ).prop( 'scrollHeight' ); // Set new global height
                autoload.post_id = response.id;

                let lastSection = jQuery('.section').last();

                sections.push([
                  lastSection.offset(),
                  lastSection.offset().top+lastSection.outerHeight( true ),
                  lastSection.index(),
                  response.url,
                  response.title
                ]);

                jQuery('html, body').animate({
                    scrollTop: lastSection.offset().top+10
                }, 100, function() {
                  // Remove the flags and attach event
                  callInitiated = false;
                  jQuery(window).scroll(scrollHandler);
                });
              } else {
                // No more to load - set the flag and attach event
                callInitiated = true;
                jQuery(window).scroll(scrollHandler);
              }
            }
          );
      } else {
        let lastIndex = 0;
        for (let i in sections) {
          if (scrollPosition >= sections[i][0].top && scrollPosition <= sections[i][1]) {
            lastIndex = i;
          }
        }
        if (window.location.href != sections[lastIndex][3]) {
          window.history.pushState( null, sections[lastIndex][4], sections[lastIndex][3] );

          if ( typeof gtag === 'undefined' && typeof ga === 'undefined' ) {
            return;
          } else {

            // Remove the domain and protocol for GA to work
            let post_url = sections[lastIndex][3].replace(/https?:\/\/[^\/]+/i, '');

            // This uses Google's new gtag tracking method.
            if ( typeof gtag !== 'undefined' && gtag !== null ) {
              ga.getAll().forEach( (tracker) => {
                gtag('config', tracker.get('trackingId'), {'page_path': post_url});
              });
            } else { //Fall back to Google Analytics Universal Analytics tracking method.
              if ( typeof ga !== 'undefined' && ga !== null ) {
                ga( 'set', 'page', post_url );
                ga( 'send', 'pageview' );
              }
            }
          }
        }
      }
    });
  }
})();
