(function() {
  if (!document.getElementById('gc-cart-preview-styles')) {
    var style = document.createElement('style');
    style.id = 'gc-cart-preview-styles';
    style.innerHTML = `
      .gc-cart-preview-wrapper {
        position: relative;
        width: 100%;
        max-width: 150px;
        min-width: 100px;
        aspect-ratio: 300 / 192;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        color: #fff !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        text-shadow: 0 1px 2px rgba(0,0,0,0.6);
        display: inline-block;
        vertical-align: middle;
      }
      .gc-cart-preview-logo {
        position: absolute;
        top: 8%;
        left: 8%;
        font-size: 7px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #fff !important;
      }
      .gc-cart-preview-price {
        position: absolute;
        top: 6%;
        right: 8%;
        background: rgba(0, 0, 0, 0.5);
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 8px;
        font-weight: 700;
        color: #fff !important;
      }
      .gc-cart-preview-msg {
        position: absolute;
        top: 28%;
        left: 8%;
        right: 8%;
        font-size: 7.5px;
        font-style: italic;
        line-height: 1.2;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        opacity: 0.9;
        color: #fff !important;
      }
      .gc-cart-preview-footer {
        position: absolute;
        bottom: 8%;
        left: 8%;
        right: 8%;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        font-size: 7px;
        line-height: 1.1;
        color: #fff !important;
      }
      .gc-cart-preview-names {
        display: flex;
        flex-direction: column;
        color: #fff !important;
      }
      .gc-cart-preview-code {
        font-size: 5.5px;
        opacity: 0.7;
        color: #fff !important;
      }
    `;
    document.head.appendChild(style);
  }

  function customizeCart() {
    fetch('/cart.js')
      .then(function(r) { return r.json(); })
      .then(function(cart) {
        var items = cart.items || [];
        
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

        if (cartItemEls.length === 0) {
          var rows = document.querySelectorAll('tr');
          rows.forEach(function(tr) {
            if (tr.querySelector('img') && (tr.querySelector('input[type="number"]') || tr.querySelector('input[name="updates[]"]'))) {
              cartItemEls.push(tr);
            }
          });
        }

        items.forEach(function(item, index) {
          var templateUrl = item.properties ? (item.properties['Template Image'] || item.properties['_Template Image']) : null;
          if (!templateUrl) {
            return;
          }

          var cartEl = cartItemEls[index];
          if (!cartEl) {
            return;
          }

          var images = cartEl.querySelectorAll('img');
          images.forEach(function(img) {
            if (img.src.indexOf('/products/') !== -1 || img.src.indexOf('/no-image') !== -1 || img.width > 30 || img.className.indexOf('image') !== -1) {
              var parent = img.parentElement;
              if (parent && !parent.querySelector('.gc-cart-preview-wrapper')) {
                img.style.display = 'none';

                var wrapper = document.createElement('div');
                wrapper.className = 'gc-cart-preview-wrapper';
                wrapper.style.backgroundImage = 'linear-gradient(rgba(0,0,0,0.35),rgba(0,0,0,0.35)), url("' + templateUrl + '")';
                wrapper.style.backgroundSize = 'cover';
                wrapper.style.backgroundPosition = 'center';

                var logo = document.createElement('div');
                logo.className = 'gc-cart-preview-logo';
                logo.innerText = 'Gift Card';

                var priceBadge = document.createElement('div');
                priceBadge.className = 'gc-cart-preview-price';
                priceBadge.innerText = '$' + (item.price / 100).toFixed(2);

                var msg = document.createElement('div');
                msg.className = 'gc-cart-preview-msg';
                msg.innerText = item.properties['Message'] || 'Happy gifting!';

                var footer = document.createElement('div');
                footer.className = 'gc-cart-preview-footer';

                var names = document.createElement('div');
                names.className = 'gc-cart-preview-names';

                var toSpan = document.createElement('span');
                toSpan.innerText = 'To: ' + (item.properties['Recipient Name'] || '');

                var fromSpan = document.createElement('span');
                fromSpan.innerText = 'From: ' + (item.properties['Sender Name'] || '');

                names.appendChild(toSpan);
                names.appendChild(fromSpan);

                var code = document.createElement('div');
                code.className = 'gc-cart-preview-code';
                code.innerText = 'Code: XXXX-XXXX-XXXX-XXXX';

                footer.appendChild(names);
                footer.appendChild(code);

                wrapper.appendChild(logo);
                wrapper.appendChild(priceBadge);
                wrapper.appendChild(msg);
                wrapper.appendChild(footer);

                parent.appendChild(wrapper);
              }
            }
          });

          // Thoroughly hide the Template Image property in the DOM list
          var allChildren = cartEl.querySelectorAll('*');
          allChildren.forEach(function(child) {
            var text = (child.textContent || '').trim();
            if (
              text.indexOf('Template Image') !== -1 ||
              text.indexOf('_Template Image') !== -1 || 
              text.indexOf('Template_Image') !== -1 ||
              text.indexOf('_Template_Image') !== -1 ||
              (child.tagName === 'A' && child.href && child.href.indexOf('/storage/gift-card-templates/') !== -1)
            ) {
              if (child.children.length === 0 || (child.children.length > 0 && child.querySelector('a, span, p'))) {
                child.style.setProperty('display', 'none', 'important');
                
                var parent = child.parentElement;
                if (parent) {
                  var pClass = (parent.className || '').toLowerCase();
                  var pTag = parent.tagName;
                  if (pClass.indexOf('option') !== -1 || pTag === 'LI' || pTag === 'DIV' || pTag === 'DT' || pTag === 'DD') {
                    parent.style.setProperty('display', 'none', 'important');
                    if (pTag === 'DT' && parent.nextElementSibling) {
                      parent.nextElementSibling.style.setProperty('display', 'none', 'important');
                    }
                    if (pTag === 'DD' && parent.previousElementSibling) {
                      parent.previousElementSibling.style.setProperty('display', 'none', 'important');
                    }
                  }
                }
              }
            }
          });
        });
      })
      .catch(function(err) {
        console.error('Cart customizer error:', err);
      });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    customizeCart();
  } else {
    document.addEventListener('DOMContentLoaded', customizeCart);
  }
  
  window.addEventListener('load', function() {
    customizeCart();
    var observer = new MutationObserver(function() {
      customizeCart();
    });
    observer.observe(document.body, { childList: true, subtree: true });
  });
})();
