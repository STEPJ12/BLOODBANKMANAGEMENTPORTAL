<?php
// Charts Component
function renderLineChart() {
  ?>
  <div class="line-chart-container" id="lineChart">
    <!-- Chart will be rendered here by JavaScript -->
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Sample data for the line chart
      const months = ["Oct", "Nov", "Dec", "Jan", "Feb", "Mar"];
      const bloodTypes = [
        { type: "A+", color: "#ef4444", data: [52, 55, 68, 60, 54, 50] },
        { type: "O+", color: "#f97316", data: [70, 75, 85, 78, 72, 68] },
        { type: "B+", color: "#8b5cf6", data: [38, 42, 45, 40, 38, 36] },
        { type: "AB+", color: "#3b82f6", data: [15, 16, 20, 18, 16, 15] },
        { type: "O-", color: "#10b981", data: [18, 20, 25, 22, 20, 18] },
      ];

      // Create the line chart using Chart.js or any other library
      // This is a placeholder - in a real implementation, you would use a charting library
      const lineChartElement = document.getElementById('lineChart');
      
      // For demonstration purposes, we'll just show a message
      lineChartElement.innerHTML = '<div class="p-4 bg-gray-100 rounded text-center">Line Chart: Monthly Blood Demand Prediction<br>(JavaScript chart would render here)</div>';
      
      // In a real implementation, you would initialize your chart library here
      // Example with Chart.js (if included in your project):
      /*
      new Chart(lineChartElement, {
        type: 'line',
        data: {
          labels: months,
          datasets: bloodTypes.map(type => ({
            label: type.type,
            data: type.data,
            borderColor: type.color,
            backgroundColor: type.color + '20',
            tension: 0.1
          }))
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: 'top',
            },
            title: {
              display: true,
              text: 'Monthly Blood Demand Prediction (Units)'
            }
          }
        }
      });
      */
    });
  </script>
  <?php
}

function renderBarChart() {
  ?>
  <div class="bar-chart-container" id="barChart">
    <!-- Chart will be rendered here by JavaScript -->
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Sample data for the bar chart
      const bloodTypes = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];
      const organizations = [
        { name: "Red Cross", color: "#ef4444", data: [45, 12, 35, 8, 15, 5, 65, 18] },
        { name: "Negros First", color: "#3b82f6", data: [38, 10, 28, 6, 12, 4, 55, 15] },
      ];

      // Create the bar chart using Chart.js or any other library
      // This is a placeholder - in a real implementation, you would use a charting library
      const barChartElement = document.getElementById('barChart');
      
      // For demonstration purposes, we'll just show a message
      barChartElement.innerHTML = '<div class="p-4 bg-gray-100 rounded text-center">Bar Chart: Current Blood Inventory by Type<br>(JavaScript chart would render here)</div>';
      
      // In a real implementation, you would initialize your chart library here
      // Example with Chart.js (if included in your project):
      /*
      new Chart(barChartElement, {
        type: 'bar',
        data: {
          labels: bloodTypes,
          datasets: organizations.map(org => ({
            label: org.name,
            data: org.data,
            backgroundColor: org.color,
            borderColor: org.color,
            borderWidth: 1
          }))
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: 'top',
            },
            title: {
              display: true,
              text: 'Current Blood Inventory by Type'
            }
          },
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
      */
    });
  </script>
  <?php
}
?>

