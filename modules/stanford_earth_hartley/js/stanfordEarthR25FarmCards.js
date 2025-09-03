// my_script.js
    (function ($, Drupal) {
      Drupal.behaviors.myModuleBehavior = {
        attach: function (context, settings) {

        // Example usage: equalize all divs with the class 'equal-height-div'
        //document.addEventListener('DOMContentLoaded', () => {
          equalizeDivHeights('.su-card');
        //});

        // Optional: Re-equalize on window resize to handle responsiveness
        window.addEventListener('resize', () => {
          // Reset heights to 'auto' before finding max height for accurate recalculation
          document.querySelectorAll('.su-card').forEach(div => {
            div.style.height = 'auto';
          });
          equalizeDivHeights('.su-card');
         });

         console.log('JavaScript attached to Your Content Type page!');
        }
      };

      function equalizeDivHeights(selector) {
        // Your JavaScript code here, e.g., targeting elements within a specific content type.
        const divs = document.querySelectorAll(selector);
        let maxHeight = 0;

        // Find the maximum height
        divs.forEach(div => {
          if (div.offsetHeight > maxHeight) {
            maxHeight = div.offsetHeight;
          }
        });

        // Apply the maximum height to all divs
        divs.forEach(div => {
          div.style.height = `${maxHeight}px`;
        });
      }

    }(jQuery, Drupal));
