<?php
declare(strict_types=1);

/**
 * Save/upsert results for a single assessment.
 * $marksByStudent = [studentId => rawMarkString]
 * Returns ['saved'=>N, 'errors'=>[...]]
 */
function saveResultMarks(int $assessmentId, array $marksByStudent, int $enteredBy): array {
    $pdo = db();
    $errors = []; $saved = 0;

    $stmt = $pdo->prepare("
        SELECT a.*, s.academic_year_id
        FROM assessments a
        JOIN semesters s ON s.id = a.semester_id
        WHERE a.id = ?
    ");
    $stmt->execute([$assessmentId]);
    $a = $stmt->fetch();
    if (!$a) return ['saved'=>0, 'errors'=>['Assessment not found.']];

    $maxMarks = (float)$a['max_marks'];
    $upsert = $pdo->prepare("
        INSERT INTO results
          (student_id, subject_id, assessment_id, semester_id, academic_year_id,
           marks, max_marks, percentage, grade, grade_point, entered_by, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,'published')
        ON DUPLICATE KEY UPDATE
          marks       = VALUES(marks),
          max_marks   = VALUES(max_marks),
          percentage  = VALUES(percentage),
          grade       = VALUES(grade),
          grade_point = VALUES(grade_point),
          entered_by  = VALUES(entered_by)
    ");

    $pdo->beginTransaction();
    try {
        foreach ($marksByStudent as $studentId => $raw) {
            $studentId = (int)$studentId;
            $raw = trim((string)$raw);
            if ($raw === '') continue;   // blank = skip
            if (!is_numeric($raw)) { $errors[] = "Invalid mark for student #$studentId."; continue; }
            $m = (float)$raw;
            if ($m < 0 || $m > $maxMarks) {
                $errors[] = "Mark $m out of range for student #$studentId (max $maxMarks).";
                continue;
            }
            $pct = round($m / $maxMarks * 100, 2);
            $grade = calculateGrade($pct);
            $gp = calculateGradePoint($grade);
            $upsert->execute([
                $studentId, $a['subject_id'], $assessmentId, $a['semester_id'],
                $a['academic_year_id'], $m, $maxMarks, $pct, $grade, $gp, $enteredBy
            ]);
            $saved++;
        }
        if ($errors) { $pdo->rollBack(); $saved = 0; }
        else         { $pdo->commit(); }
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[APTARS saveResultMarks] ' . $t->getMessage());
        return ['saved'=>0, 'errors'=>['Could not save marks. Please try again.']];
    }
    return ['saved'=>$saved, 'errors'=>$errors];
}

/**
 * Save/upsert attendance for a class session.
 * $statusByStudent = [studentId => status]
 */
function saveAttendanceRecords(int $subjectId, int $semesterId, string $date,
                               array $statusByStudent, ?int $lecturerId): array {
    $pdo = db();
    $errors = []; $saved = 0;
    $valid = ['Present','Absent','Late','Excused'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return ['saved'=>0, 'errors'=>['Invalid date.']];

    $upsert = $pdo->prepare("
        INSERT INTO attendance (student_id, subject_id, lecturer_id, semester_id, attendance_date, status)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), lecturer_id = VALUES(lecturer_id)
    ");

    $pdo->beginTransaction();
    try {
        foreach ($statusByStudent as $studentId => $status) {
            $studentId = (int)$studentId;
            if (!in_array($status, $valid, true)) continue;
            $upsert->execute([$studentId, $subjectId, $lecturerId, $semesterId, $date, $status]);
            $saved++;
        }
        $pdo->commit();
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[APTARS saveAttendanceRecords] ' . $t->getMessage());
        return ['saved'=>0, 'errors'=>['Could not save attendance. Please try again.']];
    }
    return ['saved'=>$saved, 'errors'=>$errors];
}

/**
 * Save/upsert a factor rating for one factor across many students.
 * $ratingByStudent = [studentId => numericRating]
 */
function saveFactorRatings(int $factorId, int $semesterId, array $ratingByStudent,
                           int $recordedBy): array {
    $pdo = db();
    $errors = []; $saved = 0;

    $stmt = $pdo->prepare("SELECT * FROM influencing_factors WHERE id=? AND status='active'");
    $stmt->execute([$factorId]);
    $f = $stmt->fetch();
    if (!$f) return ['saved'=>0, 'errors'=>['Factor not found.']];

    $yearStmt = $pdo->prepare("SELECT academic_year_id FROM semesters WHERE id=?");
    $yearStmt->execute([$semesterId]);
    $yearId = (int)$yearStmt->fetchColumn();
    if (!$yearId) return ['saved'=>0, 'errors'=>['Invalid semester.']];

    $min = (int)$f['scale_min']; $max = (int)$f['scale_max'];

    $upsert = $pdo->prepare("
        INSERT INTO student_factors
          (student_id, factor_id, semester_id, academic_year_id, rating, recorded_by)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), recorded_by = VALUES(recorded_by)
    ");

    $pdo->beginTransaction();
    try {
        foreach ($ratingByStudent as $studentId => $raw) {
            $studentId = (int)$studentId;
            $raw = trim((string)$raw);
            if ($raw === '') continue;
            if (!is_numeric($raw)) { $errors[] = "Invalid rating for student #$studentId."; continue; }
            $r = (int)$raw;
            if ($r < $min || $r > $max) { $errors[] = "Rating $r out of range ($min-$max) for student #$studentId."; continue; }
            $upsert->execute([$studentId, $factorId, $semesterId, $yearId, $r, $recordedBy]);
            $saved++;
        }
        if ($errors) { $pdo->rollBack(); $saved = 0; }
        else         { $pdo->commit(); }
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[APTARS saveFactorRatings] ' . $t->getMessage());
        return ['saved'=>0, 'errors'=>['Could not save ratings. Please try again.']];
    }
    return ['saved'=>$saved, 'errors'=>$errors];
}

/**
 * Lecturer may only access subjects assigned to them.
 * Returns true if allowed.
 */
function lecturerCanAccessSubject(int $lecturerId, int $subjectId, int $semesterId): bool {
    $stmt = db()->prepare('SELECT 1 FROM lecturer_subjects WHERE lecturer_id=? AND subject_id=? AND semester_id=? LIMIT 1');
    $stmt->execute([$lecturerId, $subjectId, $semesterId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Progress of a single assessment: {students, graded, avg, pass_rate}
 */
function assessmentProgress(int $assessmentId): array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT subject_id, semester_id FROM assessments WHERE id=?");
    $stmt->execute([$assessmentId]);
    $a = $stmt->fetch();
    if (!$a) return ['students'=>0,'graded'=>0,'avg'=>0.0,'pass_rate'=>0.0];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_subjects WHERE subject_id=? AND semester_id=? AND status='registered'");
    $stmt->execute([$a['subject_id'], $a['semester_id']]);
    $students = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS c,
               COALESCE(AVG(percentage),0) AS avg,
               COALESCE(SUM(CASE WHEN percentage>=40 THEN 1 ELSE 0 END)/NULLIF(COUNT(*),0)*100,0) AS pass_rate
        FROM results WHERE assessment_id=?
    ");
    $stmt->execute([$assessmentId]);
    $r = $stmt->fetch() ?: ['c'=>0,'avg'=>0,'pass_rate'=>0];

    return [
        'students'  => $students,
        'graded'    => (int)$r['c'],
        'avg'       => (float)$r['avg'],
        'pass_rate' => (float)$r['pass_rate'],
    ];
}