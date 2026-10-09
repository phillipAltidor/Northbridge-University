# Northbridge University Portal

PHP and MySQL portal currently scoped to use cases 1-5 and 52:

- Successful Login
- Unsuccessful Login
- Password Reset
- Unsuccessful Password Reset
- View Student Information
- Create User

## Current Status

Login and account lockout are implemented. All roles now open Home, displaying
the logged-in person's name and ID. Students can select Student Information
to view their own personal User data and Login email. Academic information is
hidden for now; the page does not require an academic Student record.
Detailed admin information remains hidden for now.
Update User Information (use case 53) is deferred;
its page has been removed, while the existing database logs are preserved.
Create User is admin-only and follows the User fields for Student, Faculty,
Admin, and StatStaff. It assigns a unique name-based university email and a
random initial password, storing only the password hash in Login. The assigned
ID and credentials are shown once after creation. User and Login are saved
together; role-specific records are deferred and are not created by this form.

Password reset demonstrates the email and invalid-code screens only. It does
not generate or deliver codes, change passwords, or unlock accounts. Use cases
3 and 4 are not fully implemented.

All roles share the signed-in Home page; admins also have Create User.
Unrelated academic management, registration, schedules, transcripts, reports,
and public self-registration pages have been removed.

## Local Setup

1. Create a local database named northbridge_university.
2. Import database/northbridge_test.sql into that database.
3. Copy .env.example to .env and configure your local database credentials.
4. Run `php -S 127.0.0.1:8000` from the project folder.
5. Open http://127.0.0.1:8000.

The complete original SQL export, schema.sql, demo_seed.sql, and generated-data
files in database/data/ are retained as database source material. Use
northbridge_test.sql for the local setup above; do not import every seed file
again into an already populated database. No tables or data were dropped from
the running database.
Keep .env private and use separate database settings on AWS.

## Project Structure

- admin/create-user.php: Admin-only User and Login creation for all four roles
- admin/information.php: Compatibility redirect to Home
- student/information.php: Read-only personal data for the logged-in student
- index.php: Login form
- login.php: Credential verification and account lockout
- reset-password.php: Partial reset interface
- home.php: Shared login destination with the person's name and ID
- logout.php: End the authenticated session
- includes/: Shared authentication, layout, and query helpers
- config/: Database configuration
- assets/css/: Shared styles
- database/: Original schema and sample data
