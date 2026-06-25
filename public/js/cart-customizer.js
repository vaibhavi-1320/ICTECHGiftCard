(function() {
  function customizeCart() {
    fetch('/cart.js')
      .then(function(r) { return r.json(); })
      .then(function(cart) {
        var items = cart.items || [];
        
        // Find all line item DOM elements
        var cartItemEls = [];
        var allEls = document.querySelectorAll('tr, li, div');
        allEls.forEach(function(el) {
          var cls = (el.className || '').toLowerCase();
          var tag = el.tagName;
          if (
            (tag === 'TR' && cls.indexOf('cart-item') !== -1) ||
            (tag === 'LI' && cls.indexOf('cart-item') !== -1) ||
            (cls.indexOf('cart-item') !== -1 && cls.indexOf('cart-items') === -1 && cls.indexOf('wrapper') === -1 && cls.indexOf('container') === -1)
          ) {
            cartItemEls.push(el);
          }
        });

        // Fallback: if we didn't find any elements with class 'cart-item',
        // look for elements that look like cart item rows
        if (cartItemEls.length === 0) {
          var rows = document.querySelectorAll('tr');
          rows.forEach(function(tr) {
            if (tr.querySelector('img') && (tr.querySelector('input[type="number"]') || tr.querySelector('input[name="updates[]"]'))) {
              cartItemEls.push(tr);
            }
          });
        }

        // Loop through each item in the cart JSON and swap image if it is a customized gift card
        items.forEach(function(item, index) {
          if (!item.properties || !item.properties['Template Image']) {
            return;
          }

          var templateUrl = item.properties['Template Image'];
          var cartEl = cartItemEls[index];
          if (!cartEl) {
            return;
          }

          // Swap image
          var images = cartEl.querySelectorAll('img');
          images.forEach(function(img) {
            // Verify it's a product thumbnail image
            if (img.src.indexOf('/products/') !== -1 || img.src.indexOf('/no-image') !== -1 || img.width > 30 || img.className.indexOf('image') !== -1) {
              img.src = templateUrl;
              img.srcset = templateUrl;
              img.removeAttribute('srcset'); // Remove srcset so it doesn't override the src
            }
          });

          // Hide any "Template Image" text property in case the theme did render it
          var textEls = cartEl.querySelectorAll('p, span, div, dt, dd, li, a');
          textEls.forEach(function(el) {
            if (el.textContent && el.textContent.indexOf('Template Image') !== -1) {
              var depth = 0;
              var propItem = el;
              while (propItem && depth < 4) {
                var hasClass = propItem.classList && propItem.classList.contains('product-option');
                if (hasClass || propItem.tagName === 'DT' || propItem.tagName === 'DD' || propItem.tagName === 'LI') {
                  propItem.style.setProperty('display', 'none', 'important');
                  if (propItem.tagName === 'DT') {
                    var next = propItem.nextElementSibling;
                    if (next && next.tagName === 'DD') next.style.setProperty('display', 'none', 'important');
                  }
                  if (propItem.tagName === 'DD') {
                    var prev = propItem.previousElementSibling;
                    if (prev && prev.tagName === 'DT') prev.style.setProperty('display', 'none', 'important');
                  }
                  if (hasClass) break;
                }
                propItem = propItem.parentElement;
                depth++;
              }
            }
          });
        });
      })
      .catch(function(err) {
        console.error('Cart customizer error:', err);
      });
  }

  // Run on load and periodically in case of dynamic cart drawers
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    customizeCart();
  } else {
    document.addEventListener('DOMContentLoaded', customizeCart);
  }
  
  // Also run when fetch/ajax cart updates
  window.addEventListener('load', function() {
    customizeCart();
    // Observe dynamic changes in cart drawers
    var observer = new MutationObserver(function() {
      customizeCart();
    });
    observer.observe(document.body, { childList: true, subtree: true });
  });
})();
