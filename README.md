# AttendX — Biometric Student Attendance System

A PHP + Bootstrap + JavaScript implementation of the attendance flow:

```
[ STUDENT PORTAL ]                      [ LECTURER TERMINAL ]
       │                                         │
 1. Log in to Portal                       1. Create Session
 2. Scan Face via Webcam                   2. Launch Verification Terminal
 3. Generate 128-d Vector                        │
 4. Store in SQLite Database ────────────────────┼────────────────┐
                                                 │                │
                                                 ▼                │
                                     [ CAMERA DETECTS FACE ]     │
                                                 │                │
                                                 ▼                │
                                       Query DB Descriptors ──────┘
                                                 │
                                                 ├──► No Match ──► [ Fail Alert ]
                                                 │
                                                 ▼
                                           Match Found!
                                                 │
                        ┌────────────────────────┴────────────────────────┐
                        │                                                 │
                 First Scan Today?                                 Second Scan Today?
                        │                                                 │
                        ▼                                                 ▼
              [ RECORD CHECK-IN ]                                [ RECORD CHECK-OUT ]
             Log Timestamp (t_in)                              Log Timestamp (t_out)
                                                                          │
                                                                          ▼
                                                             Calculate Elapsed Time:
                                                            T_attended = t_out - t_in
                                                                          │
                                                ┌─────────────────────────┴────────────────────────┐
                                                │                                                  │
                                   T_attended ≥ 0.70 × T_total                        T_attended < 0.70 × T_total
                                                │                                                  │
                                                ▼                                                  ▼
                                       [ Status: PRESENT ]                       [ Status: INCOMPLETE]
```

## Requirements

- PHP 8.1+ with the `pdo_sqlite` extension (no MySQL/Apache setup needed - SQLite file is created automatically on first run)

## Run it

```bash
cd attendance-system
php -S localhost:8000
```

Then open **http://localhost:8000** in your browser.

## Demo accounts

| Role     | Username | Password    |
| -------- | -------- | ----------- |
| Student  | student1 | pass123     |
| Student  | student2 | pass123     |
| Lecturer | lecturer | lecturer123 |
| Admin    | admin    | admin123    |

## How it maps to the flowchart

| Flowchart step                        | Implementation                                                                                                                              |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Face ID                               | `scan.php` + `assets/js/scan.js` use WebAuthn so the browser can prompt Windows Hello, Touch ID, Face ID, or another platform authenticator |
| Face recognition                      | `scan.php` + `assets/js/scan.js` use the webcam with `face-api.js` and store a local face template in SQLite                                |
| Iris scan                             | Not supported in a standard browser; would need a native desktop SDK or bridge app                                                          |
| Verified? / re-enter input on failure | `api/verify.php` accepts WebAuthn assertions or camera-face matches and still supports the legacy simulated fallback                        |
| Door of the class opens               | Animated door in `scan.php` once `verify.php` returns `verified: true`                                                                      |
| Student enters / doesn't enter        | Doorway sensor is simulated with two buttons; result posted to `api/mark_attendance.php`                                                    |
| Attendance verified / unverified      | Stored per-attempt in the `attendance_logs` table                                                                                           |
| Data storage                          | SQLite database at `data/attendance.sqlite`                                                                                                 |
| Student portal                        | `student_portal.php` — student's own attendance log                                                                                         |
| Lecturer's portal                     | `lecturer_portal.php` — all students' attendance per class session, with counts                                                             |

## Project structure

```
attendance-system/
├── index.php               # redirects to login or dashboard
├── login.php / logout.php  # authentication
├── scan.php                 # biometric scan + door + entry flow (student)
├── student_portal.php       # student's attendance history
├── lecturer_portal.php      # lecturer/admin view of all attendance
├── api/
│   ├── biometric_status.php # enrolled biometric status for the current student
│   ├── face_template.php    # store/retrieve the student's face descriptor
│   ├── webauthn_challenge.php # challenge payload for platform biometrics
│   ├── webauthn_enroll.php  # enroll the device as a passkey authenticator
│   ├── verify.php           # biometric verification + attendance logging
│   └── mark_attendance.php  # finalizes attendance based on entry
├── includes/
│   ├── config.php           # DB connection, schema, auth helpers
│   ├── header.php / footer.php
├── assets/
│   ├── css/style.css
│   └── js/scan.js
└── data/attendance.sqlite   # auto-created SQLite database
```

## Notes on biometrics

- Face ID now use WebAuthn, so the browser can invoke the computer's built-in biometric prompt.
- Face recognition uses the webcam in the browser and a stored face descriptor for comparison.
- Iris scanning is still a native-hardware problem; the browser does not expose a standard iris API.
- The legacy simulated flow remains as a fallback for unsupported cases.

## Going to production

- Swap SQLite for MySQL/PostgreSQL by changing the DSN in `includes/config.php`
  (schema uses standard SQL, only minor syntax tweaks needed e.g. `AUTOINCREMENT` → `AUTO_INCREMENT`).
- Add HTTPS, CSRF tokens on forms, and rate-limiting on `api/verify.php`.
- Replace demo password seeding with a proper admin-managed registration flow.
