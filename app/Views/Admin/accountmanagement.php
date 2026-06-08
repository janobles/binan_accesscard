<link rel="stylesheet" href="<?= base_url('css/accountmanagement.css') ?>?v=<?= filemtime(FCPATH . 'css/accountmanagement.css') ?>">

<section class="account-management-panel" aria-labelledby="account-management-title">
    <div class="account-management-inner">
        <h2 id="account-management-title" class="account-management-title">Account Management</h2>
        <div class="account-management-divider" aria-hidden="true"></div>

        <div class="account-filter-bar" aria-label="Account filters">
            <input
                class="form-control"
                type="search"
                aria-label="Search accounts"
                placeholder="Search accounts by username, role, or status"
            >

            <select class="form-select" aria-label="Filter by role">
                <option selected>Roles</option>
            </select>

            <select class="form-select" aria-label="Filter by status">
                <option selected>Status</option>
            </select>
    
            <button class="btn btn-success px-4" type="button">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span>Search</span>
            </button>
        </div>

        <div class="account-grid">
            <section class="account-card" aria-labelledby="create-admin-account-title">
                <h3 id="create-admin-account-title" class="account-card-title">Create Admin Account</h3>

                <div class="account-create-grid">
                    <div>
                        <label class="form-label" for="admin-account-username">Username</label>
                        <input class="form-control" type="text" id="admin-account-username">
                    </div>

                    <div>
                        <label class="form-label" for="admin-account-password">Password</label>
                        <input class="form-control" type="password" id="admin-account-password">
                    </div>

                    <button class="btn btn-success px-4" type="button">
                        <i class="bi bi-person-plus" aria-hidden="true"></i>
                        <span>Create</span>
                    </button>
                </div>
            </section>

            <section class="account-card" aria-labelledby="create-employee-account-title">
                <h3 id="create-employee-account-title" class="account-card-title">Create Employee Account</h3>

                <div class="account-create-grid">
                    <div>
                        <label class="form-label" for="employee-account-username">Username</label>
                        <input class="form-control" type="text" id="employee-account-username">
                    </div>

                    <div>
                        <label class="form-label" for="employee-account-password">Password</label>
                        <input class="form-control" type="password" id="employee-account-password">
                    </div>

                    <button class="btn btn-success px-4" type="button">
                        <i class="bi bi-person-plus" aria-hidden="true"></i>
                        <span>Create</span>
                    </button>
                </div>
            </section>
        </div>

        <div class="account-grid">
            <section class="account-card account-table-card" aria-labelledby="admin-accounts-title">
                <h3 id="admin-accounts-title" class="account-card-title">Admin Accounts</h3>
                <div class="account-management-divider" aria-hidden="true"></div>

                <div class="table-responsive">
                    <table class="table account-table align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Username</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                                <th scope="col">Time</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="account-empty-state" colspan="5">No admin accounts yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="account-card account-table-card" aria-labelledby="employee-accounts-title">
                <h3 id="employee-accounts-title" class="account-card-title">Employee Accounts</h3>
                <div class="account-management-divider" aria-hidden="true"></div>

                <div class="table-responsive">
                    <table class="table account-table align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Username</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                                <th scope="col">Time</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="account-empty-state" colspan="5">No employee accounts yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</section>
