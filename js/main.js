

$(document).ready(function () {

  // Ensure units fields only accept whole numbers
  $('input[name="units"], input[id="units"], input[name="collected_units"], input[id="collected_units"], input[name="target_units"], input[id="target_units"]').on('input', function() {
    var value = Number.parseFloat(this.value);
    if (!Number.isNaN(value) && value % 1 !== 0) {
      this.value = Math.round(value);
    }
  });

  // Prevent decimal input on units fields
  $('input[name="units"], input[id="units"], input[name="collected_units"], input[id="collected_units"], input[name="target_units"], input[id="target_units"]').on('keypress', function(e) {
    if (e.which === 46) { // Prevent decimal point
      e.preventDefault();
    }
  });

  var mySwiper = new Swiper('.swiper-container', {
    slidesPerView: 3,
    loop: true,
    effect: 'coverflow',
    autoplay: true,
    grabCursor: true,
    centeredSlides: true,
    coverflowEffect: {
      rotate: 50,
      stretch: 0,
      depth: 100,
      modifier: 1,
      slideShadows: true,
    },
    navigation: {
      nextEl: '.swiper-button-next',
      prevEl: '.swiper-button-prev',
    },
    breakpoints: {
      1024: {
        slidesPerView: 3,
      },
      768: {
        slidesPerView: 2,
      },
      640: {
        slidesPerView: 1,
      },
      320: {
        slidesPerView: 1,
      }
    }
  });

  ///////////////////////// WOW Animation ////////////////////////////////


  var wow = new WOW(
    {
      boxClass: 'wow',      // default
      animateClass: 'animated', // default
      offset: 0,          // default
      mobile: false,      // default
    }
  )
  wow.init();


});






/**
 * Blood Bank Portal - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss Bootstrap alerts after 30 seconds
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length) {
        setTimeout(() => {
            alerts.forEach(a => {
                // Avoid lingering duplicate alerts on refresh
                a.classList.add('fade');
                a.style.transition = 'opacity 0.5s';
                a.style.opacity = '0';
                setTimeout(() => a.remove(), 600);
            });
        }, 5000);
    }
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    // Prevent double form submissions and double button clicks
    const formsOnce = document.querySelectorAll('form');
    formsOnce.forEach(f => {
        f.addEventListener('submit', function(e) {
            const submitter = e.submitter;
            if (submitter && !submitter.dataset.submitted) {
                submitter.dataset.submitted = '1';
                submitter.setAttribute('disabled', 'disabled');
                setTimeout(() => submitter.removeAttribute('disabled'), 5000);
            }
        });
    });

    document.querySelectorAll('button, a.btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (btn.dataset.blockOnce === '1') {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            btn.dataset.blockOnce = '1';
            setTimeout(() => { delete btn.dataset.blockOnce; }, 1500);
        }, { capture: true });
    });

    // Title-case and single-spacing formatter for clean inputs
    // Usage: add data-titlecase="1" on any input/textarea to enforce formatting
    const formatToTitleCase = (str) => {
        // Collapse all whitespace to single spaces and trim
        let s = (str || '').replaceAll(/\s+/g, ' ').trim();
        // Title case words consisting of letters; keep other chars untouched
        return s.split(' ').map(w => {
            const m = w.match(/^([A-Za-z])(.*)$/);
            if (!m) return w;
            return m[1].toUpperCase() + m[2].toLowerCase();
        }).join(' ');
    };

    const attachTitlecase = (el) => {
        if (!el || el.dataset.titlecaseBound === '1') return;
        const handler = () => { el.value = formatToTitleCase(el.value); };
        el.addEventListener('input', handler);
        el.addEventListener('blur', handler);
        el.dataset.titlecaseBound = '1';
        // Initial normalize if prefilled
        if (el.value) el.value = formatToTitleCase(el.value);
    };

    // Bind to any fields explicitly marked
    document.querySelectorAll('input[data-titlecase="1"], textarea[data-titlecase="1"]').forEach(attachTitlecase);

    // Heuristic: auto-apply to common name/address-like fields (excluding email/password)
    const autoSelectors = [
        // Generic text inputs and textareas
        'input[type="text"]',
        'input[type="search"]',
        'textarea',
        // Common semantic names
        'input[name*="name" i]',
        'input[name*="first_name" i]',
        'input[name*="last_name" i]',
        'input[name*="middle_name" i]',
        'input[name*="city" i]',
        'input[name*="province" i]',
        'input[name*="barangay" i]',
        'input[name*="hospital" i]',
        'input[name*="doctor" i]',
        'input[name*="address" i]',
        'textarea[name*="address" i]'
    ];

    const shouldSkip = (el) => {
        const t = (el.getAttribute('type') || '').toLowerCase();
        const n = (el.getAttribute('name') || '').toLowerCase();
        if (el.hasAttribute('data-titlecase') && el.getAttribute('data-titlecase') === '0') return true;
        // Skip fields where title-casing is harmful
        const skipTypes = ['email','password','number','date','datetime-local','time','month','week','url','tel'];
        if (skipTypes.includes(t)) return true;
        return n.includes('email');
    };

    document.querySelectorAll(autoSelectors.join(',')).forEach(el => {
        if (!shouldSkip(el) && !el.hasAttribute('data-titlecase')) {
            el.setAttribute('data-titlecase', '1');
            attachTitlecase(el);
        }
    });

    // Also observe DOM for dynamically loaded forms
    const obs = new MutationObserver((mutations) => {
        mutations.forEach(m => {
            m.addedNodes && m.addedNodes.forEach(n => {
                if (n.nodeType === 1) {
                    if (n.matches && (n.matches('input[data-titlecase="1"], textarea[data-titlecase="1"]'))) {
                        attachTitlecase(n);
                    }
                    n.querySelectorAll && n.querySelectorAll('input[data-titlecase="1"], textarea[data-titlecase="1"]').forEach(attachTitlecase);
                }
            });
        });
    });
    obs.observe(document.documentElement, { childList: true, subtree: true });
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Counter animation
    const counters = document.querySelectorAll('.counter');
    if (counters.length > 0) {
        counters.forEach(counter => {
            const target = Number.parseInt(counter.textContent, 10);
            const duration = 1500;
            const step = Math.ceil(target / (duration / 16)); // 60fps

            let current = 0;
            const counterInterval = setInterval(() => {
                current += step;
                if (current >= target) {
                    counter.textContent = target;
                    clearInterval(counterInterval);
                } else {
                    counter.textContent = current;
                }
            }, 16);
        });
    }

    // Mobile sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.dashboard-content');

            sidebar.classList.toggle('show');
            content.classList.toggle('sidebar-open');
        });
    }

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    if (forms.length > 0) {
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                form.classList.add('was-validated');
            }, false);
        });
    }

    // Blood type selection in registration form
    const bloodTypeSelect = document.getElementById('blood_type');
    if (bloodTypeSelect) {
        bloodTypeSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const bloodTypeBadge = document.querySelector('.blood-type-preview');

            if (bloodTypeBadge && selectedOption.value) {
                bloodTypeBadge.textContent = selectedOption.value;
                bloodTypeBadge.style.display = 'flex';
            }
        });
    }

    // Appointment date picker
    const appointmentDate = document.getElementById('appointment_date');
    if (appointmentDate) {
        // Set min date to today
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');

        appointmentDate.min = `${yyyy}-${mm}-${dd}`;

        // Set max date to 3 months from now
        const maxDate = new Date();
        maxDate.setMonth(maxDate.getMonth() + 3);
        const maxYyyy = maxDate.getFullYear();
        const maxMm = String(maxDate.getMonth() + 1).padStart(2, '0');
        const maxDd = String(maxDate.getDate()).padStart(2, '0');

        appointmentDate.max = `${maxYyyy}-${maxMm}-${maxDd}`;
    }

    // Notification badge animation
    const notificationBadges = document.querySelectorAll('.badge');
    if (notificationBadges.length > 0) {
        notificationBadges.forEach(badge => {
            if (Number.parseInt(badge.textContent, 10) > 0) {
                badge.classList.add('animate__animated', 'animate__pulse', 'animate__infinite');
            }
        });
    }
});
