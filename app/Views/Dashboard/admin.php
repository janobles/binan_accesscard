<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Binan Access Card MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/mis.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div>
            <div class="brand">
                <img src="/assets/image/binan.png" alt="City of Binan Logo">
                <div>
                    <strong>Bi&ntilde;an Access Card MIS</strong>
                    <small>Admin Console</small>
                </div>
            </div>
            <nav class="nav flex-column mt-3">
                <a class="nav-link active" href="/admin/dashboard">Dashboard</a>
                <a class="nav-link" href="/admin/accounts">Account Management</a>
                <a class="nav-link" href="/admin/manage-family">Manage Family</a>
                <a class="nav-link" href="/admin/audit-trails">Audit Trails</a>
            </nav>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-user">Administrator &middot; Admin</div>
            <a href="<?= site_url('logout') ?>" class="btn btn-outline-light btn-sm w-100">Logout</a>
        </div>
    </aside>

    <main class="content">
        <div class="topbar">
            <div>
                <div class="fw-bold">Dashboard</div>
                <small class="text-muted">Bi&ntilde;an Access Card MIS</small>
            </div>
        </div>

        <div class="container-fluid py-4">
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="panel"><small>Total Families</small><div class="stat-value">0</div></div></div>
                <div class="col-md-3"><div class="panel"><small>Registered Members</small><div class="stat-value">0</div></div></div>
                <div class="col-md-3"><div class="panel"><small>Active Sectors</small><div class="stat-value">0</div></div></div>
                <div class="col-md-3"><div class="panel"><small>Member Services</small><div class="stat-value">0</div></div></div>
            </div>

            <div class="panel mb-3">
                <div class="section-title mt-0">
                    <span>Recent Families</span>
                    <a class="btn btn-primary btn-sm" href="/admin/manage-family">Manage Family</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Head</th><th>Barangay</th><th>Sector</th><th>Date</th></tr></thead>
                        <tbody>
                            <tr><td colspan="4" class="text-center text-muted">No family records yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel mb-3">
                <div class="section-title mt-0"><span>Account Management</span></div>
                <div class="row g-3 mb-3">
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100 bg-light">
                            <h6 class="mb-3">Create Admin Account</h6>
                            <form class="account-form">
                                <input type="hidden" name="role" value="Admin">
                                <div>
                                    <label class="form-label">Username</label>
                                    <input class="form-control" name="username" placeholder="admin_maria01" required minlength="4">
                                </div>
                                <div>
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" required minlength="8">
                                </div>
                                <div class="account-action">
                                    <button class="btn btn-primary w-100" type="button">Create</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100 bg-light">
                            <h6 class="mb-3">Create Employee Account</h6>
                            <form class="account-form account-form-employee">
                                <input type="hidden" name="role" value="User">
                                <div>
                                    <label class="form-label">Username</label>
                                    <input class="form-control" name="username" placeholder="emp_juan01" required minlength="4">
                                </div>
                                <div>
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" required minlength="8">
                                </div>
                                <div class="account-action">
                                    <button class="btn btn-primary w-100" type="button">Create</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel mb-3">
                <div class="section-title mt-0"><span>Family / Member Data Entry</span></div>
                <p class="text-muted mb-0">Family form UI is available in Dashboard/form.php.</p>
            </div>

            <div class="panel">
                <div class="section-title mt-0"><span>Audit Trails</span></div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>User</th><th>Action</th><th>Description</th><th>Date</th></tr></thead>
                        <tbody>
                            <tr><td colspan="4" class="text-center text-muted">No audit logs yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/mis.js"></script>
</body>
</html>
