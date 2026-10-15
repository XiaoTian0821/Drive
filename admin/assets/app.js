/* Drivew Admin - re-uses global app behavior; admin-specific tweaks live in style.css */
(function(){
  'use strict';
  // Confirm dialogs on admin destructive forms (belt-and-braces for any form not using inline confirm)
  document.addEventListener('submit', function(e){
    var f = e.target;
    if (f.querySelector('[data-confirm]') !== null) {
      var msg = f.getAttribute('data-confirm') || 'Are you sure?';
      if (!confirm(msg)) { e.preventDefault(); }
    }
  });
})();
