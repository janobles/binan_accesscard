<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Binan Access Card MIS</title>
</head>
<body>

    <!-- Sidebar Navigation -->
    <aside>
        <div>
            <h2>Binan Access Card MIS</h2>
        </div>
        <nav>
            <ul>
                <li><a href="<?= base_url('admin/dashboard') ?>">Dashboard</a></li>
                <li><a href="#">Manage Employees</a></li>
                <li><a href="#">Manage Familys</a></li>
                <li><a href="#">Sectors</a></li>
                <li><a href="#">Services & Assistance</a></li>
                <li><a href="#">Audit Trails</a></li>
                <li><a href="#">System Settings</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <main>
        <!-- Top Header -->
        <header>
            <div>
                <input type="text" placeholder="Search families, members...">
                <button type="button">Search</button>
            </div>
            <div>
                <span>Welcome, Administrator</span>
                <a href="<?= base_url('logout') ?>">Logout</a>
            </div>
        </header>

        <!-- Dashboard Content -->
        <section>
            <header>
                <h1>Dashboard Overview</h1>
                <p>Summary of system metrics and recent activities.</p>
            </header>
            
            <!-- Statistics Cards -->
            <div>
                <div>
                    <h3>Total Families</h3>
                    <p>0</p>
                </div>
                <div>
                    <h3>Registered Members</h3>
                    <p>0</p>
                </div>
                <div>
                    <h3>Active Sectors</h3>
                    <p>0</p>
                </div>
                <div>
                    <h3>Pending Assistance</h3>
                    <p>0</p>
                </div>
            </div>

            <br><hr><br>

            <!-- Recent Activity Panel -->
            <div>
                <header>
                    <h2>Recent Audit Trails</h2>
                    <a href="#">View All</a>
                </header>
                <div>
                    <table border="1" cellpadding="10" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action Performed</th>
                                <th>Module</th>
                                <th>Timestamp</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Placeholder for empty state -->
                            <tr>
                                <td colspan="5" align="center">No recent activity found.</td>
                            </tr>
                            <!-- Example populated row (Uncomment when dynamic data is ready)
                            <tr>
                                <td><strong>Admin1</strong></td>
                                <td>Created new family profile (Head: Juan Dela Cruz)</td>
                                <td>Families</td>
                                <td>2026-05-13 10:45 AM</td>
                                <td>Success</td>
                            </tr>
                            <tr>
                                <td><strong>Staff_Maria</strong></td>
                                <td>Updated member assistance record</td>
                                <td>Services</td>
                                <td>2026-05-13 09:30 AM</td>
                                <td>Success</td>
                            </tr>
                            <tr>
                                <td><strong>Admin1</strong></td>
                                <td>Attempted unauthorized deletion</td>
                                <td>Members</td>
                                <td>2026-05-12 04:15 PM</td>
                                <td>Failed</td>
                            </tr>
                            -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

</body>
</html>