<?php

session_start();

require_once __DIR__ . '/config/db.php';


/* =========================================================
   HELPER
========================================================= */

if (!function_exists('e')) {

    function e($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/* =========================================================
   LOGIN SECURITY
========================================================= */

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    empty($_SESSION['user_id'])
) {

    header("Location: login.php");

    exit();
}


/* =========================================================
   GET LOGGED-IN USER
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        fullname,
        login_id,
        role,
        status
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $_SESSION['user_id']
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$user) {

    session_unset();
    session_destroy();

    header("Location: login.php");

    exit();
}


/* =========================================================
   GET STUDENT APPLICATION
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        *
    FROM internship_students
    WHERE email = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([
    $user['login_id']
]);

$application = $stmt->fetch(PDO::FETCH_ASSOC);


/* =========================================================
   APPLICATION CHECK
========================================================= */

if (!$application) {

    http_response_code(404);

    die(
        'No internship application found.'
    );
}


/* =========================================================
   APPROVAL CHECK
========================================================= */

if (
    strtolower(
        trim(
            $application['application_status'] ?? ''
        )
    ) !== 'approved'
) {

    http_response_code(403);

    die(
        'Your internship application is not approved.'
    );
}


/* =========================================================
   TASK STATUS CHECK
========================================================= */

if (
    strtolower(
        trim(
            $application['task_status'] ?? ''
        )
    ) !== 'assigned'
) {

    http_response_code(403);

    die(
        'Your internship task has not been assigned yet.'
    );
}


/* =========================================================
   TASK FILE
========================================================= */

$task_file = trim(
    $application['task_file'] ?? ''
);


/*
 * Prevent directory traversal.
 */

$task_file = basename($task_file);


if ($task_file === '') {

    http_response_code(404);

    die(
        'Task file not found.'
    );
}


/* =========================================================
   TASK DIRECTORY
========================================================= */

$task_directory =
    realpath(
        __DIR__ . '/tasks'
    );


if ($task_directory === false) {

    http_response_code(500);

    die(
        'Task directory not found.'
    );
}


/* =========================================================
   TASK PATH
========================================================= */

$task_path =
    realpath(
        $task_directory .
        DIRECTORY_SEPARATOR .
        $task_file
    );


/* =========================================================
   SECURITY CHECK
========================================================= */

if (
    $task_path === false ||
    strpos(
        $task_path,
        $task_directory . DIRECTORY_SEPARATOR
    ) !== 0
) {

    http_response_code(404);

    die(
        'Task file not found.'
    );
}


/* =========================================================
   FILE EXISTS
========================================================= */

if (!is_file($task_path)) {

    http_response_code(404);

    die(
        'Task file is unavailable.'
    );
}


/* =========================================================
   PDF CHECK
========================================================= */

$extension =
    strtolower(
        pathinfo(
            $task_path,
            PATHINFO_EXTENSION
        )
    );


if ($extension !== 'pdf') {

    http_response_code(403);

    die(
        'Invalid task file.'
    );
}


/* =========================================================
   DOWNLOAD
========================================================= */

header(
    'Content-Type: application/pdf'
);

header(
    'Content-Disposition: attachment; filename="' .
    basename($task_path) .
    '"'
);

header(
    'Content-Length: ' .
    filesize($task_path)
);

header(
    'Cache-Control: private, no-store, no-cache, must-revalidate'
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);


/* =========================================================
   OUTPUT FILE
========================================================= */

readfile($task_path);

exit();

?>