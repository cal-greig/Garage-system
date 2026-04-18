<?php
// If logged in with temp password, force change_password page
if(isset($_SESSION["user"]) && !empty($_SESSION["must_change_password"])) {
    $current = basename($_SERVER["PHP_SELF"]);
    if(!in_array($current, ["change_password.php", "logout.php"])) {
        header("Location: change_password.php");
        exit();
    }
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
<div class="container-fluid">

<a class="navbar-brand" href="/PHP/dashboard.php">Garage System</a>

<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
<span class="navbar-toggler-icon"></span>
</button>

<div class="collapse navbar-collapse" id="nav">

<ul class="navbar-nav me-auto">

<li class="nav-item">
<a class="nav-link" href="/PHP/dashboard.php">Dashboard</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/PHP/calendar.php">Calendar</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/PHP/add_job.php">Add Job</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/PHP/deleted_jobs.php">Deleted Jobs</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/PHP/quotes.php">Quotes</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/PHP/inventory.php">Inventory</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/PHP/search.php">Search</a>
</li>

<li class="nav-item">
<a class="nav-link" href="/PHP/analytics.php">Analytics</a>
</li>

<?php if(($_SESSION["role"] ?? "admin") === "admin"): ?>
<li class="nav-item">
<a class="nav-link" href="/PHP/manage_users.php">Users</a>
</li>
<?php endif; ?>

<li class="nav-item">
<a class="nav-link" href="/PHP/settings.php">Settings</a>
</li>

</ul>

<span class="navbar-text me-3">
<?php if(isset($_SESSION["user"])): ?>
    <?php if(($_SESSION["role"] ?? "admin") === "admin"): ?>
        <span style="color:#f59e0b;font-size:0.75rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;margin-right:6px;">Admin</span>
    <?php endif; ?>
    <?php echo htmlspecialchars($_SESSION["user"]); ?>
<?php endif; ?>
</span>

<a class="btn btn-outline-light btn-sm" href="/PHP/logout.php">Logout</a>

</div>
</div>
</nav>
