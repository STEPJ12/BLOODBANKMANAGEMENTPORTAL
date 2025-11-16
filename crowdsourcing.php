<?php
include_once 'includes/header.php';
include_once 'config/db_connect.php';

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['submit_request'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $blood_type = mysqli_real_escape_string($conn, $_POST['blood_type']);
        $units = mysqli_real_escape_string($conn, $_POST['units']);
        $urgency = mysqli_real_escape_string($conn, $_POST['urgency']);
        $contact = mysqli_real_escape_string($conn, $_POST['contact']);
        $location = mysqli_real_escape_string($conn, $_POST['location']);
        $details = mysqli_real_escape_string($conn, $_POST['details']);
        
        $sql = "INSERT INTO blood_requests (name, blood_type, units, urgency, contact, location, details, status) 
                VALUES ('$name', '$blood_type', '$units', '$urgency', '$contact', '$location', '$details', 'pending')";
        mysqli_query($conn, $sql);
    }
    
    if (isset($_POST['submit_event'])) {
        $event_name = mysqli_real_escape_string($conn, $_POST['event_name']);
        $event_date = mysqli_real_escape_string($conn, $_POST['event_date']);
        $event_location = mysqli_real_escape_string($conn, $_POST['event_location']);
        $organizer = mysqli_real_escape_string($conn, $_POST['organizer']);
        $contact_info = mysqli_real_escape_string($conn, $_POST['contact_info']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        $sql = "INSERT INTO blood_drive_events (event_name, event_date, location, organizer, contact_info, description, status) 
                VALUES ('$event_name', '$event_date', '$event_location', '$organizer', '$contact_info', '$description', 'pending')";
        mysqli_query($conn, $sql);
    }
    
    if (isset($_POST['volunteer_signup'])) {
        $volunteer_name = mysqli_real_escape_string($conn, $_POST['volunteer_name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $availability = mysqli_real_escape_string($conn, $_POST['availability']);
        $skills = mysqli_real_escape_string($conn, $_POST['skills']);
        
        $sql = "INSERT INTO volunteers (name, email, phone, availability, skills, status) 
                VALUES ('$volunteer_name', '$email', '$phone', '$availability', '$skills', 'pending')";
        mysqli_query($conn, $sql);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Blood Bank Portal</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container my-5">
        <h1 class="text-center mb-5">Community Blood Bank Portal</h1>
        
        <!-- Tabs for different forms -->
        <ul class="nav nav-tabs mb-4" id="myTab">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="request-tab" data-bs-toggle="tab" data-bs-target="#request" type="button">
                    Submit Blood Request
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="event-tab" data-bs-toggle="tab" data-bs-target="#event" type="button">
                    Share Blood Drive Event
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="volunteer-tab" data-bs-toggle="tab" data-bs-target="#volunteer" type="button">
                    Volunteer Sign-up
                </button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Blood Request Form -->
            <div class="tab-pane fade show active" id="request">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Submit Blood Request</h3>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label" for="name">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="blood_type">Blood Type Needed</label>
                                <select class="form-select" id="blood_type" name="blood_type" required>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="units">Units Required</label>
                                <input type="number" class="form-control" id="units" name="units" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="urgency">Urgency Level</label>
                                <select class="form-select" id="urgency" name="urgency" required>
                                    <option value="urgent">Urgent</option>
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="contact">Contact Number</label>
                                <input type="tel" class="form-control" id="contact" name="contact" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="location">Location</label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="details">Additional Details</label>
                                <textarea class="form-control" id="details" name="details" rows="3"></textarea>
                            </div>
                            <button type="submit" name="submit_request" class="btn btn-primary">Submit Request</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Blood Drive Event Form -->
            <div class="tab-pane fade" id="event">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Share Blood Drive Event</h3>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label" for="event_name">Event Name</label>
                                <input type="text" class="form-control" id="event_name" name="event_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="event_date">Event Date</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="event_location">Location</label>
                                <input type="text" class="form-control" id="event_location" name="event_location" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="organizer">Organizer</label>
                                <input type="text" class="form-control" id="organizer" name="organizer" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="contact_info">Contact Information</label>
                                <input type="text" class="form-control" id="contact_info" name="contact_info" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="description">Event Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <button type="submit" name="submit_event" class="btn btn-primary">Submit Event</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Volunteer Sign-up Form -->
            <div class="tab-pane fade" id="volunteer">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Volunteer Sign-up</h3>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label" for="volunteer_name">Full Name</label>
                                <input type="text" class="form-control" id="volunteer_name" name="volunteer_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="phone">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="availability">Availability</label>
                                <select class="form-select" id="availability" name="availability" required>
                                    <option value="weekdays">Weekdays</option>
                                    <option value="weekends">Weekends</option>
                                    <option value="both">Both</option>
                                    <option value="flexible">Flexible</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="skills">Skills/Experience</label>
                                <textarea class="form-control" id="skills" name="skills" rows="3"></textarea>
                            </div>
                            <button type="submit" name="volunteer_signup" class="btn btn-primary">Sign Up</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 