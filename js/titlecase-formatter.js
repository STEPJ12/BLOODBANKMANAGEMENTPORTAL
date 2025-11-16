/**
 * Universal Title Case Formatter for Input Fields
 * Applies title case formatting and single spacing to text input fields
 * Usage: Add data-titlecase="1" attribute to any input/textarea field
 */

(function() {
    'use strict';

    const formatToTitleCase = (str) => {
        if (!str) return '';
        // Collapse all whitespace to single spaces and trim
        let s = (str || '').replace(/\s+/g, ' ').trim();
        // Title case words consisting of letters; keep other chars untouched
        return s.split(' ').map(w => {
            if (!w) return '';
            const m = w.match(/^([A-Za-z])(.*)$/);
            if (!m) return w;
            return m[1].toUpperCase() + m[2].toLowerCase();
        }).join(' ');
    };

    const attachTitlecase = (el) => {
        if (!el || el.dataset.titlecaseBound === '1') return;
        
        let lastValue = el.value;
        
        el.addEventListener('input', function(e) {
            const cursorPos = this.selectionStart;
            let value = this.value;
            
            // Prevent multiple consecutive spaces
            value = value.replace(/\s{2,}/g, ' ');
            
            // Apply title case formatting while typing
            const words = value.split(' ');
            const formattedWords = words.map((word) => {
                if (!word) return '';
                // Only format if word has at least one letter
                const match = word.match(/^([A-Za-z])(.*)$/);
                if (match) {
                    return match[1].toUpperCase() + match[2].toLowerCase();
                }
                return word;
            });
            
            value = formattedWords.join(' ');
            
            // Update value if changed
            if (value !== this.value) {
                const oldValue = this.value;
                this.value = value;
                // Try to maintain cursor position
                const diff = value.length - oldValue.length;
                const newPos = Math.min(Math.max(0, cursorPos + diff), this.value.length);
                this.setSelectionRange(newPos, newPos);
                this.classList.add('formatting');
                setTimeout(() => {
                    this.classList.remove('formatting');
                }, 300);
            }
            
            lastValue = this.value;
        });
        
        // Apply final formatting on blur to ensure consistency
        el.addEventListener('blur', function() {
            if (this.value.trim()) {
                const cursorPos = this.value.length;
                this.value = formatToTitleCase(this.value);
                this.setSelectionRange(cursorPos, cursorPos);
            }
        });
        
        el.dataset.titlecaseBound = '1';
        // Initial normalize if prefilled
        if (el.value) el.value = formatToTitleCase(el.value);
    };

    // Auto-apply to fields with data-titlecase="1" attribute
    document.addEventListener('DOMContentLoaded', function() {
        // Bind to fields explicitly marked with data-titlecase="1"
        document.querySelectorAll('input[data-titlecase="1"], textarea[data-titlecase="1"]').forEach(attachTitlecase);
        
        // Auto-apply to common name/address-like fields (excluding email/password/number/tel/url/search)
        const autoSelectors = [
            'input[type="text"][name*="name" i]',
            'input[type="text"][name*="first_name" i]',
            'input[type="text"][name*="last_name" i]',
            'input[type="text"][name*="middle_name" i]',
            'input[type="text"][name*="city" i]',
            'input[type="text"][name*="province" i]',
            'input[type="text"][name*="state" i]',
            'input[type="text"][name*="barangay" i]',
            'input[type="text"][name*="hospital" i]',
            'input[type="text"][name*="doctor" i]',
            'input[type="text"][name*="location" i]',
            'input[type="text"][name*="address" i]',
            'input[type="text"][name*="title" i]',
            'textarea[name*="address" i]'
        ];

        const shouldSkip = (el) => {
            const t = (el.getAttribute('type') || '').toLowerCase();
            const n = (el.getAttribute('name') || '').toLowerCase();
            const id = (el.getAttribute('id') || '').toLowerCase();
            // Skip email, password, number, tel, url, search, hidden, file
            return ['email', 'password', 'number', 'tel', 'url', 'search', 'hidden', 'file'].includes(t) ||
                   n.includes('email') || n.includes('password') || n.includes('phone') ||
                   id.includes('email') || id.includes('password') || id.includes('phone');
        };

        autoSelectors.forEach(selector => {
            document.querySelectorAll(selector).forEach(el => {
                if (!shouldSkip(el) && !el.dataset.titlecaseBound) {
                    attachTitlecase(el);
                }
            });
        });
    });
})();

