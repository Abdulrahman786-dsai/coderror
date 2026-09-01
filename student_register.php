<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

require_once __DIR__ . '/config/db.php';

$error_msg = '';
$success_msg = '';
$generated_student_id = '';

$old = [
    'fullname' => '',
    'email' => '',
    'phone' => '',
    'dob' => '',
    'college' => '',
    'course' => '',
    'branch' => '',
    'current_year' => '',
    'passing_year' => '',
    'internship_program' => '',
    'duration' => '',
    'internship_start_date' => '',
    'internship_end_date' => '',
    'skills' => '',
    'motivation' => '',
    'linkedin' => '',
    'github' => ''
];

function clean_value($value)
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* =========================
       CSRF
    ========================= */

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {

        $error_msg = 'Invalid security request. Please refresh the page and try again.';

    } else {

        /* =========================
           GET FORM DATA
        ========================= */

        foreach ($old as $key => $value) {
            $old[$key] = trim($_POST[$key] ?? '');
        }

        /* Online only */
        $internship_mode = 'Online';

        /* =========================
           VALIDATION
        ========================= */

        if (
            $old['fullname'] === '' ||
            $old['email'] === '' ||
            $old['phone'] === '' ||
            $old['college'] === '' ||
            $old['course'] === '' ||
            $old['current_year'] === '' ||
            $old['passing_year'] === '' ||
            $old['internship_program'] === '' ||
            $old['duration'] === '' ||
            $old['motivation'] === ''
        ) {

            $error_msg = 'Please fill all required fields.';

        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {

            $error_msg = 'Please enter a valid email address.';

        } elseif (!preg_match('/^[0-9]{10}$/', $old['phone'])) {

            $error_msg = 'Please enter a valid 10-digit mobile number.';

        } elseif (strlen($old['motivation']) < 20) {

            $error_msg = 'Please write at least 20 characters in the motivation field.';

        } else {

            try {

                /* =========================
                   CHECK DUPLICATE
                ========================= */

                $check = $pdo->prepare("
                    SELECT id, student_id
                    FROM internship_students
                    WHERE email = :email
                       OR phone = :phone
                    LIMIT 1
                ");

                $check->execute([
                    ':email' => $old['email'],
                    ':phone' => $old['phone']
                ]);

                $existing = $check->fetch();

                if ($existing) {

                    $error_msg =
                        'An application already exists with this email or mobile number. Student ID: ' .
                        $existing['student_id'];                } else {
                    /* =========================
                       INTERNSHIP DATES
                       Start = form submission date
                       End = start + selected duration - 1 day
                    ========================= */

                    $internship_start_date = date('Y-m-d');

                    if (!preg_match('/^(1 Month|2 Months|3 Months|6 Months)$/', $old['duration'])) {
                        throw new Exception('Invalid internship duration selected.');
                    }

                    $duration_months = (int) filter_var(
                        $old['duration'],
                        FILTER_SANITIZE_NUMBER_INT
                    );

                    $startDateObj = new DateTime($internship_start_date);
                    $endDateObj = clone $startDateObj;
                    $endDateObj->modify('+' . $duration_months . ' months');
                    $endDateObj->modify('-1 day');
                    $internship_end_date = $endDateObj->format('Y-m-d');

                    /* =========================
                       START TRANSACTION
                    ========================= */

                    $pdo->beginTransaction();

                    /* =========================
                       TEMPORARY STUDENT ID
                    ========================= */

                    $temporary_id =
                        'TEMP-' . bin2hex(random_bytes(8));

                    /* =========================
                       INSERT APPLICATION
                    ========================= */

                    $stmt = $pdo->prepare("
                        INSERT INTO internship_students
                        (
                            student_id,
                            fullname,
                            email,
                            phone,
                            dob,
                            college,
                            course,
                            branch,
                            current_year,
                            passing_year,
                            internship_program,
                            duration,
                            internship_start_date,
                            internship_end_date,
                            skills,
                            motivation,
                            resume,
                            linkedin,
                            github,
                            internship_mode,
                            application_status
                        )
                        VALUES
                        (
                            :student_id,
                            :fullname,
                            :email,
                            :phone,
                            :dob,
                            :college,
                            :course,
                            :branch,
                            :current_year,
                            :passing_year,
                            :internship_program,
                            :duration,
                            :internship_start_date,
                            :internship_end_date,
                            :skills,
                            :motivation,
                            :resume,
                            :linkedin,
                            :github,
                            :internship_mode,
                            :application_status
                        )
                    ");

                    $stmt->execute([
                        ':student_id' => $temporary_id,

                        ':fullname' => $old['fullname'],
                        ':email' => $old['email'],
                        ':phone' => $old['phone'],

                        ':dob' =>
                            $old['dob'] !== ''
                                ? $old['dob']
                                : null,

                        ':college' => $old['college'],
                        ':course' => $old['course'],

                        ':branch' =>
                            $old['branch'] !== ''
                                ? $old['branch']
                                : null,

                        ':current_year' => $old['current_year'],
                        ':passing_year' => $old['passing_year'],

                        ':internship_program' =>
                            $old['internship_program'],

                        ':duration' => $old['duration'],

                        ':internship_start_date' => $internship_start_date,
                        ':internship_end_date' => $internship_end_date,

                        ':skills' =>
                            $old['skills'] !== ''
                                ? $old['skills']
                                : null,

                        ':motivation' => $old['motivation'],

                        ':resume' => null,

                        ':linkedin' =>
                            $old['linkedin'] !== ''
                                ? $old['linkedin']
                                : null,

                        ':github' =>
                            $old['github'] !== ''
                                ? $old['github']
                                : null,

                        ':internship_mode' => $internship_mode,

                        ':application_status' => 'Pending'
                    ]);

                    /* =========================
                       DATABASE ID
                    ========================= */

                    $database_id = $pdo->lastInsertId();

                    /* =========================
                       STUDENT ID
                    ========================= */

                    $generated_student_id =
                        'COD-' .
                        date('Y') .
                        '-' .
                        str_pad(
                            $database_id,
                            5,
                            '0',
                            STR_PAD_LEFT
                        );

                    /* =========================
                       RESUME
                    ========================= */

                    $resume_filename = null;

                    if (
                        isset($_FILES['resume']) &&
                        $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE
                    ) {

                        if (
                            $_FILES['resume']['error'] !== UPLOAD_ERR_OK
                        ) {
                            throw new Exception(
                                'Resume upload failed.'
                            );
                        }

                        /* 5 MB */
                        if ($_FILES['resume']['size'] > 5 * 1024 * 1024) {
                            throw new Exception(
                                'Resume must be less than 5 MB.'
                            );
                        }

                        $allowed_extensions = [
                            'pdf',
                            'doc',
                            'docx'
                        ];

                        $extension = strtolower(
                            pathinfo(
                                $_FILES['resume']['name'],
                                PATHINFO_EXTENSION
                            )
                        );

                        if (
                            !in_array(
                                $extension,
                                $allowed_extensions,
                                true
                            )
                        ) {
                            throw new Exception(
                                'Only PDF, DOC and DOCX files are allowed.'
                            );
                        }

                        /* =========================
                           MIME CHECK
                        ========================= */

                        $mime_valid = true;

                        if (class_exists('finfo')) {

                            $finfo = new finfo(
                                FILEINFO_MIME_TYPE
                            );

                            $mime =
                                $finfo->file(
                                    $_FILES['resume']['tmp_name']
                                );

                            $allowed_mimes = [
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                            ];

                            if (
                                !in_array(
                                    $mime,
                                    $allowed_mimes,
                                    true
                                )
                            ) {
                                $mime_valid = false;
                            }
                        }

                        if (!$mime_valid) {
                            throw new Exception(
                                'Invalid resume file type.'
                            );
                        }

                        /* =========================
                           UPLOAD DIRECTORY
                        ========================= */

                        $upload_dir =
                            __DIR__ . '/uploads/resumes/';

                        if (!is_dir($upload_dir)) {

                            if (
                                !mkdir(
                                    $upload_dir,
                                    0755,
                                    true
                                )
                            ) {
                                throw new Exception(
                                    'Unable to create resume directory.'
                                );
                            }
                        }

                        /* =========================
                           SAFE FILE NAME
                        ========================= */

                        $resume_filename =
                            $generated_student_id .
                            '_' .
                            bin2hex(random_bytes(6)) .
                            '.' .
                            $extension;

                        $resume_path =
                            $upload_dir .
                            $resume_filename;

                        if (
                            !move_uploaded_file(
                                $_FILES['resume']['tmp_name'],
                                $resume_path
                            )
                        ) {
                            throw new Exception(
                                'Unable to save resume.'
                            );
                        }
                    }

                    /* =========================
                       UPDATE STUDENT ID
                    ========================= */

                    $update = $pdo->prepare("
                        UPDATE internship_students
                        SET
                            student_id = :student_id,
                            resume = :resume
                        WHERE id = :id
                    ");

                    $update->execute([
                        ':student_id' =>
                            $generated_student_id,

                        ':resume' =>
                            $resume_filename,

                        ':id' =>
                            $database_id
                    ]);

                    /* =========================
                       COMMIT
                    ========================= */

                    $pdo->commit();

                    $success_msg =
                        'Your internship application has been submitted successfully. Internship: ' .
                        date('d M Y', strtotime($internship_start_date)) .
                        ' to ' .
                        date('d M Y', strtotime($internship_end_date)) .
                        ' (' . $duration_months . ' Month' .
                        ($duration_months > 1 ? 's' : '') . ')';

                    /* Clear form */
                    foreach ($old as $key => $value) {
                        $old[$key] = '';
                    }

                }
            }

            catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (
                    isset($resume_path) &&
                    file_exists($resume_path)
                ) {
                    @unlink($resume_path);
                }

                error_log(
                    'Student Registration Error: ' .
                    $e->getMessage()
                );

                $error_msg =
                    $e->getMessage();
            }

            catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Student Registration DB Error: ' .
                    $e->getMessage()
                );

                $error_msg =
                    'Database error. Please try again later.';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Coderror | Internship Application</title>

<link
    href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    background:
        radial-gradient(
            circle at top,
            rgba(212,175,55,.12),
            transparent 35%
        ),
        #050505;
    color: #fff;
    font-family: Poppins, sans-serif;
}

.page {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 35px 15px;
}

.card {
    width: 100%;
    max-width: 900px;
    background: #111;
    border: 1px solid rgba(212,175,55,.35);
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 0 50px rgba(212,175,55,.10);
}

.logo {
    text-align: center;
    margin-bottom: 20px;
}

.logo h1 {
    margin: 0;
    font-family: Cinzel, serif;
    letter-spacing: 4px;
    font-size: 30px;
    color: #D4AF37;
}

.logo p {
    margin: 7px 0 0;
    color: #aaa;
    font-size: 12px;
    letter-spacing: 1.5px;
}

.online-badge {
    width: fit-content;
    margin: 0 auto 25px;
    padding: 8px 18px;
    border: 1px solid rgba(212,175,55,.45);
    border-radius: 50px;
    color: #D4AF37;
    font-size: 12px;
    letter-spacing: 1px;
}

.online-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    margin-right: 7px;
    border-radius: 50%;
    background: #D4AF37;
    box-shadow: 0 0 8px #D4AF37;
}

.alert {
    padding: 14px;
    margin-bottom: 20px;
    border-radius: 10px;
    text-align: center;
    font-size: 13px;
}

.alert-error {
    color: #ff9090;
    background: rgba(255,0,0,.08);
    border: 1px solid rgba(255,0,0,.25);
}

.alert-success {
    color: #F5D76E;
    background: rgba(212,175,55,.08);
    border: 1px solid rgba(212,175,55,.35);
}

.student-result {
    text-align: center;
    padding: 25px;
    margin-bottom: 25px;
    border-radius: 12px;
    background: rgba(212,175,55,.08);
    border: 1px solid rgba(212,175,55,.4);
}

.student-result small {
    display: block;
    color: #aaa;
    margin-bottom: 8px;
    letter-spacing: 2px;
}

.student-result strong {
    color: #F5D76E;
    font-family: Cinzel, serif;
    font-size: 28px;
    letter-spacing: 3px;
}

.student-result p {
    color: #aaa;
    font-size: 12px;
}

.section {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 30px 0 17px;
    color: #D4AF37;
    font-family: Cinzel, serif;
}

.section::after {
    content: "";
    flex: 1;
    height: 1px;
    background: rgba(212,175,55,.3);
}

.grid {
    display: grid;
    grid-template-columns: repeat(2,1fr);
    gap: 16px;
}

.full {
    grid-column: 1/-1;
}

.field {
    position: relative;
}

.field > i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #D4AF37;
    z-index: 2;
}

.field textarea + i {
    top: 20px;
    transform: none;
}

input,
select,
textarea {
    width: 100%;
    border: 1px solid rgba(212,175,55,.25);
    background: #070707;
    color: #F5D76E;
    border-radius: 9px;
    padding: 13px 15px 13px 43px;
    outline: none;
    font-family: Poppins, sans-serif;
    font-size: 13px;
}

textarea {
    min-height: 110px;
    resize: vertical;
}

input::placeholder,
textarea::placeholder {
    color: rgba(212,175,55,.45);
}

input:focus,
select:focus,
textarea:focus {
    border-color: #D4AF37;
    box-shadow: 0 0 12px rgba(212,175,55,.12);
}

select option {
    background: #111;
}

.file-note {
    margin-top: 6px;
    color: #777;
    font-size: 10px;
}

.internship-date-preview {
    border: 1px solid rgba(212,175,55,.28);
    background: rgba(212,175,55,.06);
    padding: 14px 15px 14px 43px;
    border-radius: 9px;
}

.internship-date-preview > i {
    top: 22px;
    transform: none;
}

.date-preview-inner {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.date-preview-inner span {
    display: block;
    color: #888;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .7px;
    margin-bottom: 3px;
}

.date-preview-inner strong {
    color: #F5D76E;
    font-size: 13px;
}

.submit-btn {
    width: 100%;
    margin-top: 28px;
    padding: 15px;
    border: 0;
    border-radius: 10px;
    background: linear-gradient(
        135deg,
        #F5D76E,
        #D4AF37,
        #B8860B
    );
    color: #050505;
    font-weight: 700;
    letter-spacing: 1.5px;
    cursor: pointer;
    transition: .3s;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(212,175,55,.25);
}

.footer-text {
    margin-top: 20px;
    text-align: center;
    color: #666;
    font-size: 11px;
}

.footer-text span {
    color: #D4AF37;
}

@media(max-width:700px) {

    .card {
        padding: 25px 17px;
    }

    .grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }

    .logo h1 {
        font-size: 23px;
    }

    .student-result strong {
        font-size: 21px;
    }
}

</style>

</head>

<body>

<div class="page">

<div class="card">

<div class="logo">

<h1>CODERROR</h1>

<p>STUDENT INTERNSHIP APPLICATION</p>

</div>

<div class="online-badge">
<span class="online-dot"></span>
ONLINE INTERNSHIP ONLY
</div>

<?php if ($error_msg): ?>

<div class="alert alert-error">

<i class="fas fa-circle-exclamation"></i>

&nbsp;

<?php echo clean_value($error_msg); ?>

</div>

<?php endif; ?>


<?php if ($success_msg): ?>

<div class="alert alert-success">

<i class="fas fa-circle-check"></i>

&nbsp;

<?php echo clean_value($success_msg); ?>

</div>

<div class="student-result">

<small>YOUR STUDENT ID</small>

<strong>
<?php echo clean_value($generated_student_id); ?>
</strong>

<p>
Please save this Student ID for future communication.
</p>

</div>

<?php endif; ?>


<?php if (!$success_msg): ?>

<form
    method="POST"
    action="student_register.php"
    enctype="multipart/form-data"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?php echo e(csrf_token()); ?>"
>


<div class="section">

<i class="fas fa-user"></i>

Personal Information

</div>


<div class="grid">

<div class="field">

<i class="fas fa-user"></i>

<input
    type="text"
    name="fullname"
    placeholder="Full Name *"
    value="<?php echo clean_value($old['fullname']); ?>"
    required
>

</div>


<div class="field">

<i class="fas fa-envelope"></i>

<input
    type="email"
    name="email"
    placeholder="Email Address *"
    value="<?php echo clean_value($old['email']); ?>"
    required
>

</div>


<div class="field">

<i class="fas fa-phone"></i>

<input
    type="tel"
    name="phone"
    placeholder="10-Digit Mobile Number *"
    maxlength="10"
    pattern="[0-9]{10}"
    value="<?php echo clean_value($old['phone']); ?>"
    required
>

</div>


<div class="field">

<i class="fas fa-calendar"></i>

<input
    type="date"
    name="dob"
    value="<?php echo clean_value($old['dob']); ?>"
>

</div>

</div>


<div class="section">

<i class="fas fa-graduation-cap"></i>

Education Details

</div>


<div class="grid">

<div class="field full">

<i class="fas fa-university"></i>

<input
    type="text"
    name="college"
    placeholder="College / University *"
    value="<?php echo clean_value($old['college']); ?>"
    required
>

</div>


<div class="field">

<i class="fas fa-book"></i>

<select name="course" required>

<option value="">Select Course *</option>

<option>B.Tech</option>
<option>BCA</option>
<option>MCA</option>
<option>B.Sc</option>
<option>M.Sc</option>
<option>BBA</option>
<option>MBA</option>
<option>Diploma</option>
<option>Other</option>

</select>

</div>


<div class="field">

<i class="fas fa-code"></i>

<input
    type="text"
    name="branch"
    placeholder="Branch / Specialization"
    value="<?php echo clean_value($old['branch']); ?>"
>

</div>


<div class="field">

<i class="fas fa-layer-group"></i>

<select name="current_year" required>

<option value="">Current Year *</option>

<option>1st Year</option>
<option>2nd Year</option>
<option>3rd Year</option>
<option>4th Year</option>
<option>Final Year</option>
<option>Passed Out</option>

</select>

</div>


<div class="field">

<i class="fas fa-calendar-check"></i>

<select name="passing_year" required>

<option value="">Expected Passing Year *</option>

<option>2026</option>
<option>2027</option>
<option>2028</option>
<option>2029</option>
<option>2030</option>
<option>2031</option>

</select>

</div>

</div>


<div class="section">

<i class="fas fa-laptop-code"></i>

Internship Information

</div>


<div class="grid">

<div class="field">

<i class="fas fa-briefcase"></i>

<select name="internship_program" required>

<option value="">Select Internship Program *</option>

<option>Artificial Intelligence</option>
<option>Android Development</option>
<option>App Development</option>
<option>Backend Development</option>
<option>Business Analytics</option>
<option>Cloud Computing</option>
<option>Computer Vision</option>
<option>Cyber Security</option>
<option>Data Analytics</option>
<option>Data Science</option>
<option>Database Management</option>
<option>Deep Learning</option>
<option>DevOps</option>
<option>Frontend Development</option>
<option>Full Stack Development</option>
<option>Generative AI</option>
<option>Graphic Design</option>
<option>Java Development</option>
<option>Machine Learning</option>
<option>PHP Development</option>
<option>Power BI</option>
<option>Python Development</option>
<option>Web Development</option>
<option>.NET Development</option>

</select>

</div>


<div class="field">

<i class="fas fa-clock"></i>

<select name="duration" id="duration" required>

<option value="">Select Duration *</option>

<option>1 Month</option>
<option>2 Months</option>
<option>3 Months</option>
<option>6 Months</option>

</select>

</div>


<div class="field full internship-date-preview" id="internshipDatePreview" style="display:none;">

<i class="fas fa-calendar-days"></i>

<div class="date-preview-inner">

<div>
<span>Internship Start Date</span>
<strong id="startDateText">-</strong>
</div>

<div>
<span>Internship End Date</span>
<strong id="endDateText">-</strong>
</div>

</div>

</div>


<div class="field full">

<i class="fas fa-tools"></i>

<textarea
    name="skills"
    placeholder="Your Skills"
><?php echo clean_value($old['skills']); ?></textarea>

</div>


<div class="field full">

<i class="fas fa-comment-dots"></i>

<textarea
    name="motivation"
    placeholder="Why do you want to join this internship? *"
    required
><?php echo clean_value($old['motivation']); ?></textarea>

</div>

</div>


<div class="section">

<i class="fas fa-link"></i>

Professional Profiles

</div>


<div class="grid">

<div class="field">

<i class="fab fa-linkedin"></i>

<input
    type="url"
    name="linkedin"
    placeholder="LinkedIn Profile URL"
    value="<?php echo clean_value($old['linkedin']); ?>"
>

</div>


<div class="field">

<i class="fab fa-github"></i>

<input
    type="url"
    name="github"
    placeholder="GitHub Profile URL"
    value="<?php echo clean_value($old['github']); ?>"
>

</div>


<div class="field full">

<i class="fas fa-file"></i>

<input
    type="file"
    name="resume"
    accept=".pdf,.doc,.docx"
>

<div class="file-note">
Maximum 5 MB — PDF, DOC or DOCX only.
</div>

</div>

</div>


<div class="section">

<i class="fas fa-globe"></i>

Internship Mode

</div>

<div class="online-badge" style="margin:0 0 10px 0;">

<span class="online-dot"></span>

ONLINE INTERNSHIP

</div>


<button
    type="submit"
    class="submit-btn"
>

<i class="fas fa-paper-plane"></i>

&nbsp;

SUBMIT INTERNSHIP APPLICATION

</button>

</form>

<?php endif; ?>


<div class="footer-text">

© <?php echo date('Y'); ?>

<span>Coderror</span>

&nbsp;•&nbsp;

Online Internship Program

</div>

</div>

</div>


<script>

const phone =
    document.querySelector(
        'input[name="phone"]'
    );

if (phone) {

    phone.addEventListener(
        'input',
        function () {

            this.value =
                this.value.replace(
                    /[^0-9]/g,
                    ''
                );

        }
    );
}

</script>

<script>
(function () {
    const duration = document.getElementById('duration');
    const preview = document.getElementById('internshipDatePreview');
    const startText = document.getElementById('startDateText');
    const endText = document.getElementById('endDateText');
    if (!duration) return;
    function calculateDates() {
        const match = duration.value.match(/\d+/);
        if (!match) { preview.style.display = 'none'; return; }
        const months = parseInt(match[0], 10);
        const start = new Date();
        start.setHours(0,0,0,0);
        const end = new Date(start);
        end.setMonth(end.getMonth() + months);
        end.setDate(end.getDate() - 1);
        const options = { day: '2-digit', month: 'long', year: 'numeric' };
        startText.textContent = start.toLocaleDateString('en-GB', options);
        endText.textContent = end.toLocaleDateString('en-GB', options);
        preview.style.display = 'block';
    }
    duration.addEventListener('change', calculateDates);
})();
</script>

</body>
</html>