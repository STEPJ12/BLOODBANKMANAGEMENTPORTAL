<?php
// Include security middleware (handles session, authentication, timeout, and activity tracking)
require_once '../../includes/negrosfirst_auth.php';

// Include database connection
require_once '../../config/db.php';

$isDashboard = false;
$pageTitle = "Donor Registration - Negros First";
include_once '../../includes/header.php';
?>
<div class="dashboard-container">
    <?php include_once '../../includes/sidebar.php'; ?>
    <div class="dashboard-content">
        <div class="dashboard-header p-3">
            <h2 class="h4 mb-0">Donor Registration</h2>
        </div>
        <div class="dashboard-main p-3">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Register a New Donor</h5>
                    <form>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="full_name">Full Name</label>
                                <input type="text" class="form-control" id="full_name" placeholder="Enter donor name" disabled>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="blood_type">Blood Type</label>
                                <select class="form-select" id="blood_type" disabled>
                                    <option selected>Choose...</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="contact">Contact</label>
                                <input type="text" class="form-control" id="contact" placeholder="Phone number" disabled>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-danger" type="button" disabled>Register Donor</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Registered Donors</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Blood Type</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="4" class="text-center text-muted">Donor list will appear here.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include_once '../../includes/footer.php'; ?> 