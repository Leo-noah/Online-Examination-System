# Online Examination System

My first solo project: an online examination system built with PHP and MySQL. I built it to practise authentication, database operations, role-based workflows, exam management, and grading.

**Project status:** Learning and portfolio prototype. The application has known security and reliability limitations and is not ready for real student examinations or public deployment.

## Features implemented

- Student registration and login, with separate admin, teacher, and student areas.
- User, department, and course management.
- Exam schedules, question ranges, and question entry/import.
- Multiple-choice, true/false, short-answer, and long-answer questions.
- Exam submission, a browser countdown, and question navigation.
- Automatic marking for objective questions and manual teacher grading.
- Results, teacher feedback, profile editing, and password-change requests.
- An optional AI question-formatting helper requiring your own API key.

These are implemented features, not a guarantee that every workflow is correct. See the limitations below.

## How the System Works

The online examination system follows this process:

```text
User Opens the Website
        ↓
Register / Login
        ↓
Role-Based Dashboard
        ↓
  Admin              Teacher               Student
    ↓                   ↓                     ↓
Manage Users        Create Exams        View Available Exams
Departments         Add Questions              ↓
and Courses         Set Schedule          Take an Exam
                        ↓                     ↓
                   Publish Exam          Submit Answers
                                              ↓
                                    Automatic Marking
                                  (MCQ and True/False)
                                              ↓
                                     Teacher Reviews
                                   Answers and Assigns
                                      Marks / Feedback
                                              ↓
                                    Save Final Results
                                              ↓
                                   Student Views Scores
                                   and Permitted Details
```

Teachers publish exams for students to take. Submitted objective answers receive initial automatic marks, while teachers review submissions and save final marks and feedback. Students can view their scores, with answer details available when the teacher enables them.

## Technologies

PHP, MySQL, PDO, MySQLi, HTML, CSS, JavaScript, Bootstrap, Tailwind CSS on selected pages, and Font Awesome. Several pages load libraries from CDNs and need an internet connection for those assets.

## Local setup

Use a local development environment such as XAMPP. This setup is for a private local demonstration.

### 1. Prepare the server

- Use PHP 8.x with PDO MySQL and MySQLi enabled. The optional AI helper also requires cURL.
- Start Apache and MySQL/MariaDB.
- Copy the project into `C:\xampp\htdocs\uit-php\online_exam_system` when using the default XAMPP location. Some asset URLs currently assume this directory structure.

### 2. Create the database

1. Open `http://localhost/phpmyadmin/`.
2. Create an **empty** database named `uit_exam_system`, using `utf8mb4_general_ci`.
3. Select that database, choose **Import**, and import `schema.sql`.

The schema contains table definitions only. It contains no passwords, user accounts, exam questions, submissions, or other personal data. Import it into an empty database; it is not a migration or replacement for an existing database.

The schema was prepared from the project's previous table definitions and checked against the current SQL references. A fresh database import and complete application walkthrough have not been run in this review environment.

### 3. Configure database access locally

`db.php` currently defaults to host `localhost`, database `uit_exam_system`, and user `root`. The three role header files also create MySQLi connections. Keep these four connection configurations consistent. For a dedicated database user, update the username in `db.php` and configure the same user for the headers.

The database password is read from the `DB_PASSWORD` environment variable. The headers also support `DB_HOST`, `DB_USER`, and `DB_NAME`.

For XAMPP with Apache, add the following to your **local Apache configuration**, for example `C:\xampp\apache\conf\httpd.conf`, then restart Apache:

```apache
SetEnv DB_HOST "localhost"
SetEnv DB_USER "root"
SetEnv DB_NAME "uit_exam_system"
SetEnv DB_PASSWORD "REPLACE_WITH_YOUR_LOCAL_DATABASE_PASSWORD"
```

Replace the password placeholder locally. Do not commit this Apache configuration or actual passwords. Merely creating an `.env` file will not work: this project does not include an `.env` loader. If your local MySQL account has no password, the current empty-password fallback will be used.

### 4. Open the project and create your own accounts

1. Open `http://localhost/uit-php/online_exam_system/register.php`.
2. Register an account. The current registration form accepts Gmail addresses only.
3. To make your own first account an administrator, use phpMyAdmin's SQL tab on the local database:

```sql
UPDATE users
SET role = 'admin', status = 'approved'
WHERE email = 'YOUR_REGISTERED_EMAIL';
```

Replace `YOUR_REGISTERED_EMAIL` with the email you registered, and confirm that exactly your intended account was updated. No shared default administrator password is provided.

4. Open `http://localhost/uit-php/online_exam_system/login.php` and log in.
5. Add departments and courses, register additional local test accounts, and assign teacher/student roles as needed. Use invented demonstration content rather than real student records.

Uploaded images are intentionally excluded from version control. Where needed, create the local directories `uploads`, `student/uploads`, `teacher/uploads`, `assets/uploads/profiles`, and `assets/uploads/questions`; allow the local server to write to them.

### 5. Optional AI helper

The system can be reviewed without configuring the AI helper. Without a key, the helper returns “AI assistance is not configured.”

If you enable it for local testing, configure `OPENAI_API_KEY` in the server environment with your own key. Never commit an actual key. The current helper lacks authorization and rate limits and disables TLS verification, so it must be hardened before any public exposure or real use.

## Known limitations and planned improvements

- Several admin routes lack authorization checks, and some operations run before access checks.
- Teacher ownership checks are incomplete for questions, results, and grading.
- CSRF protection is missing and some state changes use GET requests.
- Exam timing is browser-controlled; refreshes can restart the countdown. Server-side attempt, deadline, eligibility, and retake enforcement need improvement.
- Automatic grading does not consistently use configured question marks. Submitted manual marks need stronger validation.
- Upload validation, output sanitization, login throttling, and session handling need improvement.
- The optional AI endpoint needs authentication, rate limits, and enabled TLS certificate verification.
- Responsive layouts, shared styles, asset paths, and some links need cleanup. A shared stylesheet referenced by the current code is missing from this source folder.
- Creating and editing courses/questions and the full three-role workflow need further testing. Supplying the schema does not fix application-level bugs.

These limitations are part of the project's learning roadmap. Do not use this version for sensitive records or real examinations.

## Repository privacy

The repository is intended to contain source code and an empty schema only. Keep local credentials, uploaded personal files, and populated database backups out of Git. `.gitignore` does not remove files that were committed previously. Revoke any previously exposed API keys and change exposed passwords separately.

GitHub stores the source code. A running PHP/MySQL application needs a PHP-capable server and database; GitHub Pages cannot execute it.

## What I learned

This project helped me practise connecting a PHP application to a database, organising workflows for different users, processing forms, hashing passwords, handling exam answers, and identifying areas for improvement through code review.
