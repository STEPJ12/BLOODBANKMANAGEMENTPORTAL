<?php
// Public verification page for blood requests
// Shows request details for a scanned QR code

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

$requestId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$token = isset($_GET['t']) ? (string) $_GET['t'] : '';
$secret = getenv('APP_SECRET') ?: 'change_this_secret';
$expected = hash_hmac('sha256', (string)$requestId, $secret);
$tokenValid = hash_equals($expected, $token);

$req = null;
if ($requestId > 0 && $tokenValid) {
    $req = getRow(
        "SELECT br.*, pu.name AS patient_name
         FROM blood_requests br
         LEFT JOIN patient_users pu ON br.patient_id = pu.id
         WHERE br.id = ?",
        [$requestId]
    );
}

$statusBadge = function($status) {
    $s = strtolower((string)$status);
    switch ($s) {
        case 'approved': return 'success';
        case 'pending': return 'warning';
        case 'rejected': return 'danger';
        case 'completed': return 'primary';
        default: return 'secondary';
    }
};

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify Blood Request</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <style>
    body { background-color: #f8f9fa; }
    .container-narrow { max-width: 860px; }
    .receipt { background: #fff; border-radius: .5rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,.05); }
  </style>
</head>
<body>
  <div class="container container-narrow py-4">
    <div class="d-flex align-items-center mb-4">
      <i class="bi bi-qr-code fs-2 text-primary me-2"></i>
      <h1 class="h3 mb-0">Blood Request Verification</h1>
    </div>

    <?php if (!$tokenValid): ?>
      <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>Invalid verification link. Please scan the QR from the receipt again.</div>
      </div>
    <?php elseif (!$req): ?>
      <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>Request not found. Please check the QR code or request ID.</div>
      </div>
    <?php else: ?>
      <div class="receipt p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h2 class="h5 mb-1">Request #<?php echo htmlspecialchars((string)$req['id']); ?></h2>
            <div class="text-muted small">Submitted: <?php echo htmlspecialchars((string)$req['request_date']); ?></div>
          </div>
          <span class="badge bg-<?php echo $statusBadge($req['status']); ?>">
            <?php echo htmlspecialchars(ucfirst((string)$req['status'])); ?>
          </span>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <div class="card border-0 bg-light">
              <div class="card-body">
                <h6 class="text-uppercase text-muted mb-3">Patient</h6>
                <dl class="row mb-0 small">
                  <dt class="col-5">Name</dt>
                  <dd class="col-7"><?php echo htmlspecialchars($req['patient_name'] ?? ('Patient#'.$req['patient_id'])); ?></dd>
                  <dt class="col-5">Hospital</dt>
                  <dd class="col-7"><?php echo htmlspecialchars((string)$req['hospital']); ?></dd>
                  <dt class="col-5">Doctor</dt>
                  <dd class="col-7"><?php echo htmlspecialchars((string)$req['doctor_name']); ?></dd>
                </dl>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card border-0 bg-light">
              <div class="card-body">
                <h6 class="text-uppercase text-muted mb-3">Request Details</h6>
                <dl class="row mb-0 small">
                  <dt class="col-5">Blood Type</dt>
                  <dd class="col-7"><?php echo htmlspecialchars((string)$req['blood_type']); ?></dd>
                  <dt class="col-5">Units</dt>
                  <dd class="col-7"><?php echo (int)$req['units_requested']; ?></dd>
                  <dt class="col-5">Blood Bank</dt>
                  <dd class="col-7 text-capitalize"><?php echo htmlspecialchars((string)$req['organization_type']); ?></dd>
                  <dt class="col-5">Required By</dt>
                  <dd class="col-7"><?php echo htmlspecialchars((string)$req['required_date']); ?></dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <?php if (!empty($req['reason'])): ?>
        <div class="mt-3">
          <h6 class="text-uppercase text-muted mb-2">Reason</h6>
          <p class="mb-0 small"><?php echo nl2br(htmlspecialchars((string)$req['reason'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print
          </button>
          <a class="btn btn-primary" href="/blood/index.php">
            <i class="bi bi-house-door me-1"></i> Home
          </a>
        </div>
        <!-- Print Layout -->
        <div id="print-header" style="display:none;">
          <div style="text-align:center; margin-bottom:20px;">
            <img src="imgs/rc.png" alt="Red Cross Logo" style="height:80px; margin-bottom:10px;">
            <h2 style="margin:0; color:#dc3545;">Philippine Red Cross</h2>
            <h4 style="margin:0;">Blood Bank Portal Report</h4>
            <hr>
          </div>
        </div>
        <script>
        window.addEventListener('beforeprint', function() {
          document.getElementById('print-header').style.display = 'block';
        });
        window.addEventListener('afterprint', function() {
          document.getElementById('print-header').style.display = 'none';
        });
        </script>
        </div>
      </div>

      <div class="alert alert-info small" role="alert">
        This page is read-only and used for verification purposes by blood bank staff. If details are incorrect, please refer to your submitted request in the portal.
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
