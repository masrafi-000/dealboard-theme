/* DealBoard / American Alley — Main JS */
(function($) {
  'use strict';

  // ===== CURRENCY CONVERSION =====
  // Replace 'YOUR_API_KEY' with your key from exchangerate-api.com
  var API_KEY = 'e245dcbfcc976985e28b632c';
  var BASE_CURRENCY = 'USD';
  var currentCurrency = localStorage.getItem('dealboard_currency') || 'USD';
  var currentKey = localStorage.getItem('dealboard_currency_key') || 'us';
  var exchangeRates = {};

  var currencySymbols = {
    'USD':'$','EUR':'€','GBP':'£','CAD':'C$','AUD':'A$',
    'JPY':'¥','CNY':'¥','INR':'₹','BRL':'R$','MXN':'$',
    'CHF':'CHF','SEK':'kr','NOK':'kr','DKK':'kr','NZD':'NZ$',
    'SGD':'S$','HKD':'HK$','KRW':'₩','AED':'د.إ','SAR':'SR',
    'ZAR':'R','TRY':'₺','PLN':'zł','CZK':'Kč','HUF':'Ft',
    'ILS':'₪','PHP':'₱','THB':'฿','IDR':'Rp','MYR':'RM',
    'PKR':'Rs','EGP':'E£','NGN':'₦','KES':'KSh','GHS':'₵',
    'BDT':'৳','OMR':'ر.ع','BHD':'BD','KWD':'KD','QAR':'QR','MAD':'MAD'
  };

  // Fetch exchange rates from API
  function fetchRates(targetCurrency) {
    if (API_KEY === 'YOUR_API_KEY') {
      // Demo mode — no API key yet
      console.log('Currency conversion: Add your API key to main.js');
      return;
    }

    var cached = localStorage.getItem('db_rates_' + targetCurrency);
    var cachedTime = localStorage.getItem('db_rates_time_' + targetCurrency);
    var now = Date.now();

    // Use cached rates if less than 1 hour old
    if (cached && cachedTime && (now - parseInt(cachedTime)) < 3600000) {
      exchangeRates = JSON.parse(cached);
      convertAllPrices(targetCurrency);
      return;
    }

    // Fetch fresh rates
    fetch('https://v6.exchangerate-api.com/v6/' + API_KEY + '/latest/' + BASE_CURRENCY)
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.result === 'success') {
          exchangeRates = data.conversion_rates;
          localStorage.setItem('db_rates_' + targetCurrency, JSON.stringify(exchangeRates));
          localStorage.setItem('db_rates_time_' + targetCurrency, now.toString());
          convertAllPrices(targetCurrency);
        }
      })
      .catch(function(e) { console.log('Exchange rate fetch failed:', e); });
  }

  // Convert all prices on page
function convertAllPrices(targetCurrency) {
  if (targetCurrency === 'USD') {
    document.querySelectorAll('[data-original-price]').forEach(function(el) {
      el.textContent = el.dataset.originalPrice;
    });
    return;
  }

  var toRate = exchangeRates[targetCurrency];
  if (!toRate) return;

  var symbol = currencySymbols[targetCurrency] || targetCurrency;

  document.querySelectorAll('.listing-price, .sl-price, .sl-contact-price').forEach(function(el) {
    var text = el.textContent.trim();
    if (!text || text === 'Exchange' || text === '🔄 Exchange' || text === 'Free') return;

    // Store original once
    if (!el.dataset.originalPrice) {
      el.dataset.originalPrice = text;
      el.dataset.originalCurrency = el.dataset.currency || 'USD';
    }

    // Extract number
    var match = el.dataset.originalPrice.match(/[\d,]+\.?\d*/);
    if (!match) return;

    var amount = parseFloat(match[0].replace(/,/g, ''));
    if (isNaN(amount) || amount === 0) return;

    var fromCurrency = el.dataset.originalCurrency;

    // Same currency — no conversion
    if (fromCurrency === targetCurrency) {
      el.textContent = el.dataset.originalPrice;
      return;
    }

    // Convert: fromCurrency → USD → targetCurrency
    var fromRate = exchangeRates[fromCurrency] || 1;
    var inUSD    = amount / fromRate;
    var converted = inUSD * toRate;

    // Format
    var formatted;
    if (converted >= 1000000) {
      formatted = (converted / 1000000).toFixed(2) + 'M';
    } else if (converted >= 1000) {
      formatted = Math.round(converted).toLocaleString();
    } else {
      formatted = converted.toFixed(2);
    }

    el.textContent = symbol + formatted;
  });
}
  // ===== CURRENCY SWITCHER UI =====
  const currencyBtn = document.getElementById('currency-switcher');
  const currencyDrop = document.getElementById('currency-dropdown');
  const currencySearch = document.getElementById('currency-search');
  const currentCurrencyEl = document.getElementById('current-currency');

  if (currencyBtn && currencyDrop) {
    currencyBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      const isOpen = currencyDrop.style.display !== 'none';
      currencyDrop.style.display = isOpen ? 'none' : 'block';
    });

    document.addEventListener('click', function() {
      if (currencyDrop) currencyDrop.style.display = 'none';
    });

    currencyDrop.addEventListener('click', function(e) { e.stopPropagation(); });

    // Search currencies
    if (currencySearch) {
      currencySearch.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.currency-option').forEach(opt => {
          opt.style.display = opt.textContent.toLowerCase().includes(q) ? 'flex' : 'none';
        });
      });
    }

    // Select currency
    document.querySelectorAll('.currency-option').forEach(opt => {
      opt.addEventListener('click', function() {
        const code = this.dataset.code;
        const key = this.dataset.key;

        currentCurrency = code;
        currentKey = key;

        if (currentCurrencyEl) currentCurrencyEl.textContent = key.toUpperCase() + ' ' + code;
        localStorage.setItem('dealboard_currency', code);
        localStorage.setItem('dealboard_currency_key', key);
        currencyDrop.style.display = 'none';

        document.querySelectorAll('.currency-option').forEach(o => o.style.fontWeight = 'normal');
        this.style.fontWeight = '700';

        // Convert prices
        fetchRates(code);
      });
    });

    // Restore saved currency on load
    if (currentCurrencyEl) {
      currentCurrencyEl.textContent = currentKey.toUpperCase() + ' ' + currentCurrency;
    }

    // Auto-convert if non-USD saved
    if (currentCurrency && currentCurrency !== 'USD') {
      fetchRates(currentCurrency);
    }
  }

  // ===== MOBILE MENU =====
  const mobileToggle = document.getElementById('mobile-toggle');
  const mobileMenu = document.getElementById('mobile-menu');

  if (mobileToggle && mobileMenu) {
    mobileToggle.addEventListener('click', function() {
      const isOpen = mobileMenu.style.display !== 'none';
      mobileMenu.style.display = isOpen ? 'none' : 'block';
      const spans = this.querySelectorAll('span');
      if (!isOpen) {
        spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
        spans[1].style.opacity = '0';
        spans[2].style.transform = 'rotate(-45deg) translate(5px, -5px)';
      } else {
        spans[0].style.transform = '';
        spans[1].style.opacity = '';
        spans[2].style.transform = '';
      }
    });
  }

  // ===== LAZY LOAD =====
  if ('IntersectionObserver' in window) {
    const lazyImages = document.querySelectorAll('img[loading="lazy"]');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => { if (entry.isIntersecting) observer.unobserve(entry.target); });
    });
    lazyImages.forEach(img => observer.observe(img));
  }

  // ===== TOAST =====
  window.showToast = function(msg, type = 'success') {
    const toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;background:' +
      (type==='success'?'#C8102E':'#EF4444') +
      ';color:white;padding:12px 20px;border-radius:8px;font-size:14px;font-weight:600;box-shadow:0 10px 25px rgba(0,0,0,.15)';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => { document.body.removeChild(toast); }, 3000);
  };

})(jQuery);