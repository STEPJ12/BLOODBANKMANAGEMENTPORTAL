<?php
// Include database connection
require_once 'config/db.php';

// Get upcoming blood drives
$upcomingDrives = executeQuery("
    SELECT bd.*, bu.name as barangay_name,
    CASE
        WHEN bd.organization_type = 'redcross' THEN 'Red Cross'
        WHEN bd.organization_type = 'negrosfirst' THEN 'Negros First'
        ELSE bd.organization_type
    END as organization_name,
    CASE
        WHEN bd.organization_type = 'redcross' THEN rc.name
        WHEN bd.organization_type = 'negrosfirst' THEN nf.name
        ELSE NULL
    END as venue_name,
    CASE
        WHEN bd.organization_type = 'redcross' THEN rc.address
        WHEN bd.organization_type = 'negrosfirst' THEN nf.address
        ELSE NULL
    END as venue_address
    FROM blood_drives bd
    JOIN barangay_users bu ON bd.barangay_id = bu.id
    LEFT JOIN blood_banks rc ON bd.organization_type = 'redcross' AND bd.organization_id = rc.id
    LEFT JOIN blood_banks nf ON bd.organization_type = 'negrosfirst' AND bd.organization_id = nf.id
    WHERE bd.date >= CURDATE() 
    AND bd.status = 'Scheduled'
    ORDER BY bd.date ASC
");

// Display the results
if ($upcomingDrives && count($upcomingDrives) > 0) {
    echo "<h2>Upcoming Blood Drives</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>
            <th>Title</th>
            <th>Organization</th>
            <th>Venue</th>
            <th>Date</th>
            <th>Time</th>
            <th>Location</th>
            <th>Target Donors</th>
          </tr>";
    
    foreach ($upcomingDrives as $drive) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($drive['title']) . "</td>";
        echo "<td>" . htmlspecialchars($drive['organization_name']) . "</td>";
        echo "<td>" . htmlspecialchars($drive['venue_name']) . "</td>";
        echo "<td>" . date('F d, Y', strtotime($drive['date'])) . "</td>";
        echo "<td>" . date('h:i A', strtotime($drive['start_time'])) . " - " . date('h:i A', strtotime($drive['end_time'])) . "</td>";
        echo "<td>" . htmlspecialchars($drive['barangay_name']) . "</td>";
        echo "<td>" . htmlspecialchars($drive['target_donors']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No upcoming blood drives found.</p>";
}
?> 