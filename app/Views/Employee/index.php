<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Workspace - Binan Access Card MIS</title>
</head>
<body>

    <!-- Sidebar Navigation -->
    <aside>
        <div>
            <h2>Binan Access Card MIS (Employee)</h2>
        </div>
        <nav>
            <ul>
                <li><a href="<?= base_url('employee/workspace') ?>">Workspace</a></li>
                <li><a href="#">Register Family</a></li>
                <li><a href="#">Manage Members</a></li>
                <li><a href="#">Process Assistance</a></li>
                <li><a href="#">My Recent Activity</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <main>
        <!-- Top Header -->
        <header>
            <div>
                <input type="text" placeholder="Search families, members by ID...">
                <button type="button">Search</button>
            </div>
            <div>
                <span>Welcome, Employee</span>
                <a href="<?= base_url('logout') ?>">Logout</a>
            </div>
        </header>

        <!-- Workspace Content -->
        <section>
            <header>
                <h1>Employee Workspace</h1>
                <p>Quick access to family registration and assistance processing.</p>
            </header>
            
            <!-- Quick Actions -->
            <div>
                <h2>Quick Actions</h2>
                <ul>
                    <li><button type="button">+ New Family Profile</button></li>
                    <li><button type="button">+ Add Family Member</button></li>
                    <li><button type="button">Process New Assistance</button></li>
                </ul>
            </div>

            <br><hr><br>

            <!-- Pending Tasks / Recent Processing -->
            <div>
                <header>
                    <h2>Recently Processed Assistance</h2>
                    <a href="#">View All My Logs</a>
                </header>
                <div>
                    <table border="1" cellpadding="10" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th>Reference ID</th>
                                <th>Beneficiary Name</th>
                                <th>Assistance Type</th>
                                <th>Date Processed</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Placeholder for empty state -->
                            <tr>
                                <td colspan="5" align="center">No recent records found.</td>
                            </tr>
                            <!-- Example populated row
                            <tr>
                                <td>REF-00123</td>
                                <td>Maria Clara (Senior)</td>
                                <td>Medical Assistance</td>
                                <td>2026-05-13 11:30 AM</td>
                                <td>Completed</td>
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