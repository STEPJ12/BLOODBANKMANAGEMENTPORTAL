import {
  Chart,
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  ChartLegend,
  ChartLegendContent,
  ChartStyle
} from "@/components/ui/chart"
import * as bootstrap from "bootstrap" // Import Bootstrap

/**
 * Blood Bank Portal JavaScript
 */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any components that need JavaScript
    initializeTabs();
    initializeDropdowns();
    initializeModals();
    
    // Add event listeners for forms
    setupFormValidation();
});

/**
 * Initialize tab functionality
 */
function initializeTabs() {
    const tabButtons = document.querySelectorAll('[data-tab-target]');
    const tabContents = document.querySelectorAll('[data-tab-content]');
    
    if (tabButtons.length === 0) return;
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const target = document.querySelector(button.dataset.tabTarget);
            
            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all buttons
            tabButtons.forEach(btn => {
                btn.classList.remove('active', 'bg-red-700', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-700');
            });
            
            // Show the selected tab content
            target.classList.remove('hidden');
            
            // Add active class to clicked button
            button.classList.add('active', 'bg-red-700', 'text-white');
            button.classList.remove('bg-gray-100', 'text-gray-700');
        });
    });
    
    // Activate the first tab by default if none is active
    if (!document.querySelector('[data-tab-target].active')) {
        tabButtons[0]?.click();
    }
}

/**
 * Initialize dropdown functionality
 */
function initializeDropdowns() {
    const dropdownButtons = document.querySelectorAll('[data-dropdown-toggle]');
    
    if (dropdownButtons.length === 0) return;
    
    dropdownButtons.forEach(button => {
        const targetId = button.dataset.dropdownToggle;
        const target = document.getElementById(targetId);
        
        if (!target) return;
        
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            target.classList.toggle('hidden');
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', () => {
        document.querySelectorAll('[data-dropdown-content]').forEach(dropdown => {
            dropdown.classList.add('hidden');
        });
    });
}

/**
 * Initialize modal functionality
 */
function initializeModals() {
    const modalTriggers = document.querySelectorAll('[data-modal-target]');
    const closeButtons = document.querySelectorAll('[data-modal-close]');
    
    if (modalTriggers.length === 0) return;
    
    modalTriggers.forEach(trigger => {
        const targetId = trigger.dataset.modalTarget;
        const target = document.getElementById(targetId);
        
        if (!target) return;
        
        trigger.addEventListener('click', () => {
            target.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        });
    });
    
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('[data-modal]');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        });
    });
    
    // Close modal when clicking on backdrop
    document.querySelectorAll('[data-modal-backdrop]').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                backdrop.closest('[data-modal]').classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        });
    });
}

// Initialize all charts when the DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  // Initialize charts if they exist on the page
  initializeCharts()

  // Initialize tabs
  const tabLinks = document.querySelectorAll(".nav-link")
  tabLinks.forEach((tabLink) => {
    tabLink.addEventListener("click", function (e) {
      e.preventDefault()

      // Get the target tab content
      const targetId = this.getAttribute("data-bs-target")

      // Hide all tab contents
      document.querySelectorAll(".tab-pane").forEach((tabContent) => {
        tabContent.classList.remove("show", "active")
      })

      // Show the target tab content
      document.querySelector(targetId).classList.add("show", "active")

      // Update active tab
      tabLinks.forEach((link) => {
        link.classList.remove("active")
      })
      this.classList.add("active")
    })
  })
})

/**
 * Initialize charts if Chart.js is available
 */
function initializeCharts() {
    if (typeof Chart === 'undefined') return;
    
    // Initialize line charts
    document.querySelectorAll('.line-chart-container canvas').forEach(canvas => {
        const ctx = canvas.getContext('2d');
        const datasetId = canvas.dataset.chartId;
        const chartData = window.chartData?.[datasetId];
        
        if (!chartData) return;
        
        const lineChart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    });
    
    // Initialize bar charts
    document.querySelectorAll('.bar-chart-container canvas').forEach(canvas => {
        const ctx = canvas.getContext('2d');
        const datasetId = canvas.dataset.chartId;
        const chartData = window.chartData?.[datasetId];
        
        if (!chartData) return;
        
        const barChart = new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    });
}

/**
 * Setup form validation
 */
function setupFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    if (forms.length === 0) return;
    
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            let isValid = true;
            
            // Check required fields
            form.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('border-red-500');
                    
                    // Add error message if it doesn't exist
                    let errorMsg = field.nextElementSibling;
                    if (!errorMsg || !errorMsg.classList.contains('error-message')) {
                        errorMsg = document.createElement('p');
                        errorMsg.classList.add('text-red-500', 'text-xs', 'mt-1', 'error-message');
                        errorMsg.textContent = 'This field is required';
                        field.parentNode.insertBefore(errorMsg, field.nextSibling);
                    }
                } else {
                    field.classList.remove('border-red-500');
                    
                    // Remove error message if it exists
                    const errorMsg = field.nextElementSibling;
                    if (errorMsg && errorMsg.classList.contains('error-message')) {
                        errorMsg.remove();
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
}

// Function to toggle tabs
function openTab(evt, tabName) {
  // Hide all tab content
  const tabContent = document.getElementsByClassName("tab-content")
  for (let i = 0; i < tabContent.length; i++) {
    tabContent[i].style.display = "none"
  }

  // Remove active class from all tab buttons
  const tabLinks = document.getElementsByClassName("tab-link")
  for (let i = 0; i < tabLinks.length; i++) {
    tabLinks[i].className = tabLinks[i].className.replace(" active", "")
  }

  // Show the current tab and add active class to the button
  document.getElementById(tabName).style.display = "block"
  evt.currentTarget.className += " active"
}

// Function to handle form submissions
function submitForm(formId, successMessage) {
  const form = document.getElementById(formId)
  if (form) {
    form.addEventListener("submit", (e) => {
      e.preventDefault()

      // In a real application, you would send the form data to the server here
      // For demo purposes, just show a success message
      alert(successMessage)

      // Reset the form
      form.reset()
    })
  }
}

// Initialize form submissions
submitForm("donorRegistrationForm", "Donor registration successful!")
submitForm("patientRequestForm", "Blood request submitted successfully!")
submitForm("loginForm", "Login successful!")
submitForm("registerForm", "Registration successful!")

// Custom JavaScript for Blood Bank Portal

// Function to initialize tooltips
function initTooltips() {
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl))
}

// Function to initialize popovers
function initPopovers() {
  const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
  popoverTriggerList.map((popoverTriggerEl) => new bootstrap.Popover(popoverTriggerEl))
}

// Function to handle form validation
function validateForm(formId) {
  const form = document.getElementById(formId)
  if (!form) return

  form.addEventListener(
    "submit",
    (event) => {
      if (!form.checkValidity()) {
        event.preventDefault()
        event.stopPropagation()
      }
      form.classList.add("was-validated")
    },
    false,
  )
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  // Initialize Bootstrap components
  initTooltips()
  initPopovers()

  // Initialize form validation for common forms
  validateForm("loginForm")
  validateForm("registerForm")
  validateForm("donorForm")
  validateForm("requestForm")

  // Add smooth scrolling for anchor links
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault()

      const targetId = this.getAttribute("href").substring(1)
      if (!targetId) return

      const targetElement = document.getElementById(targetId)
      if (targetElement) {
        targetElement.scrollIntoView({
          behavior: "smooth",
        })
      }
    })
  })
})

// Function to confirm deletion
function confirmDelete(message) {
  return confirm(message || "Are you sure you want to delete this item?")
}

// Function to preview image before upload
function previewImage(input, previewId) {
  if (input.files && input.files[0]) {
    const reader = new FileReader()

    reader.onload = (e) => {
      const preview = document.getElementById(previewId)
      if (preview) {
        preview.src = e.target.result
      }
    }

    reader.readAsDataURL(input.files[0])
  }
}

/**
 * Toggle organization in blood bank dashboard
 */
function toggleOrganization(org) {
    const redCrossElements = document.querySelectorAll('.red-cross-data');
    const negrosFirstElements = document.querySelectorAll('.negros-first-data');
    
    if (org === 'all') {
        redCrossElements.forEach(el => el.classList.remove('hidden'));
        negrosFirstElements.forEach(el => el.classList.remove('hidden'));
    } else if (org === 'red_cross') {
        redCrossElements.forEach(el => el.classList.remove('hidden'));
        negrosFirstElements.forEach(el => el.classList.add('hidden'));
    } else if (org === 'negros_first') {
        redCrossElements.forEach(el => el.classList.add('hidden'));
        negrosFirstElements.forEach(el => el.classList.remove('hidden'));
    }
}

/**
 * Print current page
 */
function printPage() {
    window.print();
}

/**
 * Export table to CSV
 */
function exportTableToCSV(tableId, filename = 'data.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Replace any commas in the cell text to avoid CSV issues
            let text = cols[j].innerText.replaceAll(/,/g, ' ');
            // Wrap in quotes to handle any special characters
            row.push('"' + text + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    downloadCSV(csv.join('\n'), filename);
}

/**
 * Download CSV helper function
 */
function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
