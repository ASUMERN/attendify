<?php
/**
 * config.php
 * Central DB connection + schema bootstrap for the Biometric Attendance System.
 * Uses SQLite so the whole project runs with zero external setup.
 */

session_start();
date_default_timezone_set('Africa/Kampala');

define('DB_PATH', __DIR__ . '/../data/attendance.sqlite');

function app_base_path(): string {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptDir === '/' || $scriptDir === '.') {
        return '';
    }

    if (preg_match('~/api$~', $scriptDir)) {
        $scriptDir = dirname($scriptDir);
    }

    return rtrim($scriptDir, '/');
}

function app_url(string $path = ''): string {
    $basePath = app_base_path();
    $cleanPath = ltrim($path, '/');

    if ($cleanPath === '') {
        return $basePath === '' ? '/' : $basePath;
    }

    return ($basePath === '' ? '' : $basePath) . '/' . $cleanPath;
}

function base64url_encode_string(string $binary): string {
    return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
}

function base64url_decode_string(string $base64url): string {
    $padded = strtr($base64url, '-_', '+/');
    return base64_decode($padded . str_repeat('=', (4 - strlen($padded) % 4) % 4)) ?: '';
}

function biometric_session_key(string $mode): string {
    return 'biometric_' . $mode . '_challenge';
}

function set_biometric_challenge(string $mode, string $challenge): void {
    $_SESSION[biometric_session_key($mode)] = $challenge;
}

function get_biometric_challenge(string $mode): ?string {
    return $_SESSION[biometric_session_key($mode)] ?? null;
}

function clear_biometric_challenge(string $mode): void {
    unset($_SESSION[biometric_session_key($mode)]);
}

function create_biometric_challenge(string $mode): string {
    $challenge = base64url_encode_string(random_bytes(32));
    set_biometric_challenge($mode, $challenge);
    return $challenge;
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $isNew = !file_exists(DB_PATH);
        if (!is_dir(dirname(DB_PATH))) {
            mkdir(dirname(DB_PATH), 0777, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        if ($isNew) {
            initSchema($pdo);
            seedData($pdo);
        }
        ensureBiometricSchema($pdo);
    }
    return $pdo;
}

function initSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            role TEXT NOT NULL CHECK(role IN ('student','lecturer','admin')),
            reg_no TEXT UNIQUE,               -- students only
            staff_no TEXT UNIQUE,             -- lecturers only
            full_name TEXT NOT NULL,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            course TEXT,
            biometric_registered INTEGER DEFAULT 0, -- has fingerprint/face on file
            created_at TEXT DEFAULT (datetime('now'))
        );
    ");

    $pdo->exec("
        CREATE TABLE class_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course TEXT NOT NULL,
            title TEXT NOT NULL,
            lecturer_id INTEGER NOT NULL,
            session_date TEXT NOT NULL,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL,
            door_status TEXT DEFAULT 'closed', -- closed | open
            FOREIGN KEY (lecturer_id) REFERENCES users(id)
        );
    ");

    $pdo->exec("
        CREATE TABLE attendance_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            scan_method TEXT NOT NULL,          -- fingerprint | face | eye
            scan_result TEXT NOT NULL,          -- verified | unverified
            retry_count INTEGER DEFAULT 0,
            door_opened INTEGER DEFAULT 0,      -- 1/0
            entered_classroom INTEGER DEFAULT NULL, -- 1 entered / 0 did not / NULL not applicable
            attendance_status TEXT DEFAULT NULL,    -- verified | unverified
            logged_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (session_id) REFERENCES class_sessions(id),
            FOREIGN KEY (student_id) REFERENCES users(id)
        );
    ");

    $pdo->exec("
        CREATE TABLE webauthn_credentials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE,
            credential_id TEXT NOT NULL,
            label TEXT DEFAULT 'This device',
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");

    $pdo->exec("
        CREATE TABLE face_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE,
            descriptor_json TEXT NOT NULL,
            sample_count INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
}

function ensureBiometricSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS webauthn_credentials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE,
            credential_id TEXT NOT NULL,
            label TEXT DEFAULT 'This device',
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS face_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE,
            descriptor_json TEXT NOT NULL,
            sample_count INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
}

function seedData(PDO $pdo): void {
    // Demo lecturer
    $pdo->prepare("INSERT INTO users (role, staff_no, full_name, username, password, course)
                    VALUES ('lecturer','L001','Dr. Amina Okello','lecturer','" . password_hash('lecturer123', PASSWORD_DEFAULT) . "','BIT 2201')")
        ->execute();

    // Demo students
    $students = [
        ['S001','Brian Mugisha','student1','BIT 2201'],
        ['S002','Grace Nabatanzi','student2','BIT 2201'],
        ['S003','Kevin Ssemwogerere','student3','BIT 2201'],
        ['S004','Faith Achieng','student4','BIT 2201'],
    ];
    $stmt = $pdo->prepare("INSERT INTO users (role, reg_no, full_name, username, password, course)
                            VALUES ('student', ?, ?, ?, ?, ?)");
    foreach ($students as $s) {
        $stmt->execute([$s[0], $s[1], $s[2], password_hash('pass123', PASSWORD_DEFAULT), $s[3]]);
    }

    // Demo admin
    $pdo->prepare("INSERT INTO users (role, full_name, username, password)
                    VALUES ('admin','System Admin','admin','" . password_hash('admin123', PASSWORD_DEFAULT) . "')")
        ->execute();

    // A live class session for today, created by the lecturer
    $lecturerId = $pdo->lastInsertId(); // note: not reliable after multiple inserts, refetch instead
    $lecturer = $pdo->query("SELECT id FROM users WHERE role='lecturer' LIMIT 1")->fetch();
    $pdo->prepare("INSERT INTO class_sessions (course, title, lecturer_id, session_date, start_time, end_time)
                    VALUES ('BIT 2201','Database Systems II', ?, date('now'), '08:00', '10:00')")
        ->execute([$lecturer['id']]);
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function requireRole(string $role): void {
    $u = currentUser();
    if (!$u || $u['role'] !== $role) {
        header('Location: ' . app_url('login.php'));
        exit;
    }
}

function requireLogin(): void {
    if (!currentUser()) {
        header('Location: ' . app_url('login.php'));
        exit;
    }
}
