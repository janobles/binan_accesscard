# Project Structure

This project follows the normal CodeIgniter 4 layout. The database source of truth is `C:\Users\Acer\Downloads\accesscardV1.4.sql`; no app migrations are used.

## Backend

- `app/Controllers/Home.php`  
  Handles login redirects, dashboard data, account lists, stats, and page loading.

- `app/Controllers/AccountController.php`  
  Handles Developer account creation. Employee accounts are stored as `User` role to match the SQL dump.

- `app/Controllers/FamilyController.php`  
  Validates and saves head/member records to `member`, selected services to `member_services`, and audit logs to `audit_trails`.

- `app/Models/UserModel.php`  
  User login, password hashing, and account creation.

- `app/Models/MemberModel.php`  
  Head of family and family member records.

- `app/Models/FamilyFormOptionsModel.php`  
  Form dropdown options from `sector`, `services`, and exact allowed table values.

- `app/Models/AuditTrailsModel.php`  
  Audit trail inserts and audit list queries.

- `app/Models/SectorModel.php`, `ServiceModel.php`, `MemberServiceModel.php`  
  Models for the SQL dump's `sector`, `services`, and `member_services` tables.

## Views

- `app/Views/Login/login.php`  
  Login form.

- `app/Views/Dashboard/admin.php`  
  Developer/Admin pages.

- `app/Views/Employee/index.php`  
  Employee workspace pages.

- `app/Views/Shared/family_form.php`  
  Shared family/member form markup. Options are passed in from the controller.

## Database

- `C:\Users\Acer\Downloads\accesscardV1.4.sql`  
  Source database structure.

- `app/Database/Seeds/AccessCardSeeder.php`  
  Test login accounts only. It does not add tables or columns.

## Public Assets

- `public/assets/css/login.css`  
  Login page styling.

- `public/assets/css/mis.css`  
  Shared dashboard/workspace styling.

- `public/assets/js/mis.js`  
  Shared dashboard/workspace JavaScript.

- `public/assets/image/`  
  Biñan logo, CSWD logo, and background images.
