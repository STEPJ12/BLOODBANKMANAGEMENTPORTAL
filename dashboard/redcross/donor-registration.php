<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/api_keys.php';

// Initialize variables
$success_message = '';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_donor'])) {
    // Get form data
    $first_name = normalize_input($_POST['first_name'] ?? '', true);
    $last_name = normalize_input($_POST['last_name'] ?? '', true);
    $email = normalize_input($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone']);
    $dob = sanitize($_POST['dob']);
    $gender = sanitize($_POST['gender']);
    $blood_type = sanitize($_POST['blood_type']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $state = sanitize($_POST['state']);
    $zip = sanitize($_POST['zip']);
    $weight = (float)$_POST['weight'];
    $height = (float)$_POST['height'];
    $has_donated_before = isset($_POST['has_donated_before']) ? 1 : 0;
    $last_donation_date = !empty($_POST['last_donation_date']) ? sanitize($_POST['last_donation_date']) : null;
    $medical_conditions = sanitize($_POST['medical_conditions']);
    $medications = sanitize($_POST['medications']);
    $allergies = sanitize($_POST['allergies']);
    $is_eligible = isset($_POST['is_eligible']) ? 1 : 0;
    $notes = sanitize($_POST['notes']);

    // Validate inputs
    if (empty($first_name)) {
        $errors[] = "First name is required";
    } elseif (!preg_match('/^[A-Z][a-z]*(?: [A-Z][a-z]*)*$/', $first_name)) {
        $errors[] = "First name must start with capital letters with single spacing.";
    }

    if (empty($last_name)) {
        $errors[] = "Last name is required";
    } elseif (!preg_match('/^[A-Z][a-z]*(?: [A-Z][a-z]*)*$/', $last_name)) {
        $errors[] = "Last name must start with capital letters with single spacing.";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists
        $check_email_sql = "SELECT id FROM donor_users WHERE email = ?";
        $existing_donor = getRow($check_email_sql, [$email]);

        if ($existing_donor) {
            $errors[] = "Email already registered. Please use a different email.";
        }
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }

    if (empty($dob)) {
        $errors[] = "Date of birth is required";
    } else {
        // Calculate age
        $birthdate = new DateTime($dob);
        $today = new DateTime();
        $age = $birthdate->diff($today)->y;

        // Check if donor is at least 18 years old
        if ($age < 18) {
            $errors[] = "Donor must be at least 18 years old";
        }
    }

    if (empty($blood_type)) {
        $errors[] = "Blood type is required";
    }

    if ($weight < 50) {
        $errors[] = "Donor must weigh at least 50 kg";
    }

    // If no errors, insert donor into database
    if (empty($errors)) {
        $sql = "INSERT INTO donor_users (
                first_name, last_name, email, phone, dob, gender, blood_type,
                address, city, state, zip, weight, height,
                has_donated_before, last_donation_date, medical_conditions,
                medications, allergies, is_eligible, notes,
                created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                NOW(), NOW()
            )";

        $params = [
            $first_name, $last_name, $email, $phone, $dob, $gender, $blood_type,
            $address, $city, $state, $zip, $weight, $height,
            $has_donated_before, $last_donation_date, $medical_conditions,
            $medications, $allergies, $is_eligible, $notes
        ];

        $donor_id = insertRow($sql, $params);

        if ($donor_id) {
            $success_message = "Donor registered successfully! Donor ID: $donor_id";

            // Log the registration
            $log_sql = "INSERT INTO audit_logs (user_role, user_id, action, details, ip_address)
                       VALUES (?, ?, ?, ?, ?)";
            $log_params = [
                'redcross', // Assuming user role
                1, // Assuming user ID
                'Register Donor',
                "Registered new donor: $first_name $last_name (ID: $donor_id)",
                $_SERVER['REMOTE_ADDR']
            ];

            insertRow($log_sql, $log_params);

            // Clear form data after successful submission
            $_POST = [];
        } else {
            $errors[] = "Error registering donor. Please try again.";
        }
    }
}

$pageTitle = "Donor Registration";
include_once '../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Blood Bank Portal'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">


    <!-- Custom CSS -->
    <?php
    // Determine the correct path for CSS files
    $basePath = '';
    if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) {
        $basePath = '../../';
        echo '<link rel="stylesheet" href="' . $basePath . 'assets/css/dashboard.css">';
    }
    ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar is included from sidebar.php -->
    
    <!-- Main content -->
    <div class="dashboard-content">
        <div class="dashboard-header">
            <div class="d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Donor Registration</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../../dashboard/index.php" style="color: #dc3545; font-weight: 500;">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page" style="color: #000;">Donor Registration</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <div class="dashboard-main">
            <div class="container-fluid px-0">

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" id="error-message">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success" id="success-message">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Donor Registration Form</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" class="needs-validation" novalidate>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2">Personal Information</h5>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo isset($first_name) ? htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') : (isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name'], ENT_QUOTES, 'UTF-8') : ''); ?>" data-titlecase="1" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo isset($last_name) ? htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8') : (isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name'], ENT_QUOTES, 'UTF-8') : ''); ?>" data-titlecase="1" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="dob" name="dob" value="<?php echo isset($dob) ? htmlspecialchars($dob, ENT_QUOTES, 'UTF-8') : (isset($_POST['dob']) ? htmlspecialchars($_POST['dob'], ENT_QUOTES, 'UTF-8') : ''); ?>" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <?php
                                        $currentGender = isset($gender) ? $gender : (isset($_POST['gender']) ? htmlspecialchars($_POST['gender'], ENT_QUOTES, 'UTF-8') : '');
                                        ?>
                                        <option value="Male" <?php echo $currentGender === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $currentGender === 'Female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo $currentGender === 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="blood_type" class="form-label">Blood Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="blood_type" name="blood_type" required>
                                        <option value="">Select Blood Type</option>
                                        <?php
                                        $currentBloodType = isset($blood_type) ? $blood_type : (isset($_POST['blood_type']) ? htmlspecialchars($_POST['blood_type'], ENT_QUOTES, 'UTF-8') : '');
                                        ?>
                                        <option value="A+" <?php echo $currentBloodType === 'A+' ? 'selected' : ''; ?>>A+</option>
                                        <option value="A-" <?php echo $currentBloodType === 'A-' ? 'selected' : ''; ?>>A-</option>
                                        <option value="B+" <?php echo $currentBloodType === 'B+' ? 'selected' : ''; ?>>B+</option>
                                        <option value="B-" <?php echo $currentBloodType === 'B-' ? 'selected' : ''; ?>>B-</option>
                                        <option value="AB+" <?php echo $currentBloodType === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                        <option value="AB-" <?php echo $currentBloodType === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                        <option value="O+" <?php echo $currentBloodType === 'O+' ? 'selected' : ''; ?>>O+</option>
                                        <option value="O-" <?php echo $currentBloodType === 'O-' ? 'selected' : ''; ?>>O-</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2">Contact Information</h5>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : (isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''); ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($phone) ? htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : (isset($_POST['phone']) ? htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8') : ''); ?>" required>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="address" name="address" value="<?php echo isset($address) ? htmlspecialchars($address, ENT_QUOTES, 'UTF-8') : (isset($_POST['address']) ? htmlspecialchars($_POST['address'], ENT_QUOTES, 'UTF-8') : ''); ?>" placeholder="Start typing your address..." data-titlecase="1">
                                    <div class="form-text">Type your address and select from the dropdown suggestions</div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" value="<?php echo isset($city) ? htmlspecialchars($city, ENT_QUOTES, 'UTF-8') : (isset($_POST['city']) ? htmlspecialchars($_POST['city'], ENT_QUOTES, 'UTF-8') : ''); ?>" data-titlecase="1" readonly>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="state" class="form-label">State</label>
                                    <input type="text" class="form-control" id="state" name="state" value="<?php echo isset($state) ? htmlspecialchars($state, ENT_QUOTES, 'UTF-8') : (isset($_POST['state']) ? htmlspecialchars($_POST['state'], ENT_QUOTES, 'UTF-8') : ''); ?>" data-titlecase="1" readonly>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="zip" class="form-label">ZIP Code</label>
                                    <input type="text" class="form-control" id="zip" name="zip" value="<?php echo isset($zip) ? htmlspecialchars($zip, ENT_QUOTES, 'UTF-8') : (isset($_POST['zip']) ? htmlspecialchars($_POST['zip'], ENT_QUOTES, 'UTF-8') : ''); ?>" readonly>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2">Medical Information</h5>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="weight" class="form-label">Weight (kg) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="weight" name="weight" value="<?php echo isset($weight) ? htmlspecialchars($weight, ENT_QUOTES, 'UTF-8') : (isset($_POST['weight']) ? htmlspecialchars($_POST['weight'], ENT_QUOTES, 'UTF-8') : ''); ?>" required>
                                    <div class="form-text">Donor must weigh at least 50 kg to be eligible.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="height" class="form-label">Height (cm)</label>
                                    <input type="number" step="0.01" class="form-control" id="height" name="height" value="<?php echo isset($height) ? htmlspecialchars($height, ENT_QUOTES, 'UTF-8') : (isset($_POST['height']) ? htmlspecialchars($_POST['height'], ENT_QUOTES, 'UTF-8') : ''); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="has_donated_before" name="has_donated_before" <?php echo (isset($has_donated_before) && $has_donated_before) || (isset($_POST['has_donated_before'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="has_donated_before">
                                            Has donated blood before
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3" id="last_donation_date_container">
                                    <label for="last_donation_date" class="form-label">Last Donation Date</label>
                                    <input type="date" class="form-control" id="last_donation_date" name="last_donation_date" value="<?php echo isset($last_donation_date) ? htmlspecialchars($last_donation_date, ENT_QUOTES, 'UTF-8') : (isset($_POST['last_donation_date']) ? htmlspecialchars($_POST['last_donation_date'], ENT_QUOTES, 'UTF-8') : ''); ?>">
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="medical_conditions" class="form-label">Medical Conditions</label>
                                    <textarea class="form-control" id="medical_conditions" name="medical_conditions" rows="2"><?php echo isset($medical_conditions) ? htmlspecialchars($medical_conditions, ENT_QUOTES, 'UTF-8') : (isset($_POST['medical_conditions']) ? htmlspecialchars($_POST['medical_conditions'], ENT_QUOTES, 'UTF-8') : ''); ?></textarea>
                                    <div class="form-text">List any medical conditions that may affect blood donation.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="medications" class="form-label">Current Medications</label>
                                    <textarea class="form-control" id="medications" name="medications" rows="2"><?php echo isset($medications) ? htmlspecialchars($medications, ENT_QUOTES, 'UTF-8') : (isset($_POST['medications']) ? htmlspecialchars($_POST['medications'], ENT_QUOTES, 'UTF-8') : ''); ?></textarea>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="allergies" class="form-label">Allergies</label>
                                    <textarea class="form-control" id="allergies" name="allergies" rows="2"><?php echo isset($allergies) ? htmlspecialchars($allergies, ENT_QUOTES, 'UTF-8') : (isset($_POST['allergies']) ? htmlspecialchars($_POST['allergies'], ENT_QUOTES, 'UTF-8') : ''); ?></textarea>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2">Eligibility</h5>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <div class="form-check">
                                        <?php
                                        $isEligibleChecked = '';
                                        if (isset($_POST['register_donor'])) {
                                            // After form submission, check POST value
                                            $isEligibleChecked = isset($_POST['is_eligible']) ? 'checked' : '';
                                        } else {
                                            // Before form submission, default to checked
                                            $isEligibleChecked = 'checked';
                                        }
                                        ?>
                                        <input class="form-check-input" type="checkbox" id="is_eligible" name="is_eligible" <?php echo $isEligibleChecked; ?>>
                                        <label class="form-check-label" for="is_eligible">
                                            Donor is eligible for blood donation
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" data-titlecase="1"><?php echo isset($notes) ? htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') : (isset($_POST['notes']) ? htmlspecialchars($_POST['notes'], ENT_QUOTES, 'UTF-8') : ''); ?></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" name="register_donor" class="btn btn-danger">Register Donor</button>
                                    <button type="reset" class="btn btn-outline-secondary">Reset Form</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Google Places API -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_PLACES_API_KEY; ?>&libraries=places"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Google Places Autocomplete
        try {
            const addressInput = document.getElementById('address');
            const cityInput = document.getElementById('city');
            const stateInput = document.getElementById('state');
            const zipInput = document.getElementById('zip');

            if (!addressInput) {
                console.error('Address input field not found');
                return;
            }

            const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                componentRestrictions: { country: "us" }, // Restrict to US addresses
                fields: ["address_components", "formatted_address"],
                types: ["address"]
            });

            // Handle place selection
            autocomplete.addListener('place_changed', function() {
                try {
                    const place = autocomplete.getPlace();
                    
                    // Clear existing values
                    cityInput.value = '';
                    stateInput.value = '';
                    zipInput.value = '';

                    // Extract address components
                    if (place.address_components) {
                        for (const component of place.address_components) {
                            const componentType = component.types[0];

                            switch (componentType) {
                                case "locality":
                                    cityInput.value = component.long_name;
                                    break;
                                case "administrative_area_level_1":
                                    stateInput.value = component.short_name;
                                    break;
                                case "postal_code":
                                    zipInput.value = component.long_name;
                                    break;
                            }
                        }
                    }

                    // Set the formatted address
                    addressInput.value = place.formatted_address;
                } catch (error) {
                    console.error('Error handling place selection:', error);
                }
            });
        } catch (error) {
            console.error('Error initializing Google Places Autocomplete:', error);
        }

        // Show/hide last donation date field based on checkbox
        const hasDonatedCheckbox = document.getElementById('has_donated_before');
        const lastDonationContainer = document.getElementById('last_donation_date_container');

        function toggleLastDonationDate() {
            if (hasDonatedCheckbox.checked) {
                lastDonationContainer.style.display = 'block';
            } else {
                lastDonationContainer.style.display = 'none';
                document.getElementById('last_donation_date').value = '';
            }
        }

        // Initial toggle
        toggleLastDonationDate();

        // Toggle on checkbox change
        hasDonatedCheckbox.addEventListener('change', toggleLastDonationDate);

        // Form validation
        const form = document.querySelector('.needs-validation');

        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            form.classList.add('was-validated');
        });

        // Hide feedback messages after 30 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert, .feedback-message, .notification, .status-message').forEach(function(el) {
                el.style.display = 'none';
            });
        }, 5000);
    });
</script>
<script src="../../assets/js/titlecase-formatter.js"></script>

</body>
</html>
<style>
    .dashboard-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
        height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0;
        margin-left: 300px; /* Sidebar width */
        position: relative;
        transition: margin-left 0.3s ease;
    }

    .dashboard-header {
        position: sticky;
        top: 0;
        z-index: 1020;
        background-color: #fff;
        border-bottom: 1px solid #dc3545;
        padding: 1rem 1.5rem;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.1);
    }

    .dashboard-header h4 {
        color: #dc3545;
        margin: 0;
    }

    .breadcrumb {
        margin: 0;
        padding: 0;
        background: transparent;
        font-size: 0.9rem;
    }

    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        font-size: 1.2rem;
        line-height: 1;
        vertical-align: middle;
        color: #dc3545;
    }

    .breadcrumb-item a {
        color: #dc3545 !important;
        font-weight: 500;
        text-decoration: none;
    }

    .breadcrumb-item a:hover {
        color: #b02a37 !important;
        text-decoration: none;
    }

    .breadcrumb-item.active {
        color: #6c757d;
    }

    .dashboard-main {
        padding: 1.5rem;
        flex: 1;
    }
    .dashboard-header .breadcrumb {
        margin-left: 35rem;
    }

    /* Form specific styles */
    .form-label {
        color: #495057;
        font-weight: 500;
    }

    .text-danger {
        color: #dc3545 !important;
    }

    .form-control:focus {
        border-color: rgba(220, 53, 69, 0.25);
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
    }

    .form-check-input:checked {
        background-color: #dc3545;
        border-color: #dc3545;
    }

    @media (max-width: 991.98px) {
        .dashboard-content {
            margin-left: 0;
        }
    }
</style>

<?php include_once '../../includes/footer.php'; ?>
