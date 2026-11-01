<?php
declare(strict_types=1);

/**
 * Aggregate performance summary for a student in a semester.
 * Returns: ['avg','gpa','passed','failed','subjects','attendance_pct','trend_label','trend_delta']
 */
function studentPerformanceSummary(int $studentId, int $semesterId): array {
    $pdo = db();

    // Per-subject average (across all assessments of that subject)
    $stmt = $pdo->prepare("
        SELECT sub.id AS subject_id, sub.code, sub.name,
               AVG(r.percentage) AS avg_pct,
               COUNT(r.id) AS assessment_count
        FROM results r
        JOIN subjects sub ON sub.id = r.subject_id
        WHERE r.student_id = ? AND r.semester_id = ? AND r.status='published'
        GROUP BY sub.id, sub.code, sub.name
    ");
    $stmt->execute([$studentId, $semesterId]);
    $subjects = $stmt->fetchAll();

    $avg = 0.0; $gpa = 0.0; $passed = 0; $failed = 0; $totalCredits = 0;
    foreach ($subjects as $s) {
        $p = (float)$s['avg_pct'];
        $avg += $p;
        $g = calculateGrade($p);
        $gp = calculateGradePoint($g);
        $credits = (int)(db()->query("SELECT credit_hours FROM subjects WHERE id=".(int)$s['subject_id'])->fetchColumn() ?: 0);
        $gpa += $gp * max($credits, 1);
        $totalCredits += max($credits, 1);
        if ($p >= 40) $passed++; else $failed++;
    }
    $avg = $subjects ? $avg / count($subjects) : 0.0;
    $gpa = $totalCredits > 0 ? $gpa / $totalCredits : 0.0;

    // Attendance %
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END)/NULLIF(COUNT(*),0)*100, 0)
        FROM attendance WHERE student_id=? AND semester_id=?
    ");
    $stmt->execute([$studentId, $semesterId]);
    $attendance = (float)$stmt->fetchColumn();

    return [
        'avg' => $avg, 'gpa' => $gpa,
        'passed' => $passed, 'failed' => $failed,
        'subjects' => count($subjects),
        'attendance_pct' => $attendance,
        'per_subject' => $subjects,
    ];
}

/**
 * Class summary for a subject in a semester.
 * Returns: ['students','avg','highest','lowest','pass_rate','failure_rate','grade_distribution']
 */
function subjectPerformance(int $subjectId, int $semesterId): array {
    $pdo = db();

    // Average per student (across assessments)
    $stmt = $pdo->prepare("
        SELECT r.student_id, AVG(r.percentage) AS avg_pct
        FROM results r
        WHERE r.subject_id = ? AND r.semester_id = ? AND r.status='published'
        GROUP BY r.student_id
    ");
    $stmt->execute([$subjectId, $semesterId]);
    $rows = $stmt->fetchAll();

    $avgs = array_map(fn($r) => (float)$r['avg_pct'], $rows);
    $avg = $avgs ? array_sum($avgs) / count($avgs) : 0.0;
    $highest = $avgs ? max($avgs) : 0.0;
    $lowest  = $avgs ? min($avgs) : 0.0;
    $passCount = count(array_filter($avgs, fn($v) => $v >= 40));
    $passRate = $avgs ? $passCount / count($avgs) * 100 : 0.0;

    // Grade distribution (per individual result row)
    $stmt = $pdo->prepare("
        SELECT grade, COUNT(*) AS c FROM results
        WHERE subject_id=? AND semester_id=? AND status='published'
        GROUP BY grade ORDER BY grade
    ");
    $stmt->execute([$subjectId, $semesterId]);
    $gradeDist = [];
    foreach ($stmt->fetchAll() as $g) $gradeDist[$g['grade']] = (int)$g['c'];

    return [
        'students' => count($avgs),
        'avg' => $avg, 'highest' => $highest, 'lowest' => $lowest,
        'pass_rate' => $passRate, 'failure_rate' => 100 - $passRate,
        'grade_distribution' => $gradeDist,
    ];
}

/**
 * Compute overall average across a set of students (a class/programme).
 */
function classAverage(array $studentIds, int $semesterId): array {
    if (!$studentIds) return ['avg'=>0.0,'pass_rate'=>0.0,'students'=>0];
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $stmt = db()->prepare("
        SELECT r.student_id, AVG(r.percentage) AS avg_pct
        FROM results r
        WHERE r.student_id IN ($placeholders) AND r.semester_id = ? AND r.status='published'
        GROUP BY r.student_id
    ");
    $stmt->execute([...$studentIds, $semesterId]);
    $rows = $stmt->fetchAll();
    $avgs = array_map(fn($r) => (float)$r['avg_pct'], $rows);
    $avg = $avgs ? array_sum($avgs)/count($avgs) : 0.0;
    $pass = count(array_filter($avgs, fn($v) => $v >= 40));
    $passRate = $avgs ? $pass/count($avgs)*100 : 0.0;
    return ['avg'=>$avg, 'pass_rate'=>$passRate, 'students'=>count($avgs)];
}

/**
 * Attention status: 'Normal' | 'Monitor' | 'Requires Attention'
 * Uses configurable thresholds from settings.
 */
function attentionStatus(int $studentId, int $semesterId): array {
    $perfThr = (float)setting('performance_threshold', 50);
    $attThr  = (float)setting('attendance_threshold', 75);
    $failThr = (int)setting('failed_subjects_threshold', 2);

    $sum = studentPerformanceSummary($studentId, $semesterId);
    $avg = $sum['avg']; $att = $sum['attendance_pct']; $failed = $sum['failed'];

    $reason = [];
    $status = 'Normal';

    if ($failed >= $failThr) { $status = 'Requires Attention'; $reason[] = "$failed failed subject(s)"; }
    if ($att > 0 && $att < $attThr) { $status = 'Requires Attention'; $reason[] = 'Low attendance'; }
    if ($sum['subjects'] > 0 && $avg < $perfThr) { $status = 'Requires Attention'; $reason[] = 'Low average'; }

    if ($status === 'Normal') {
        // Monitor: borderline — either avg close to threshold or attendance close
        if ($sum['subjects'] > 0 && $avg < $perfThr + 5) { $status = 'Monitor'; $reason[] = 'Average near threshold'; }
        if ($att > 0 && $att < $attThr + 5) { $status = 'Monitor'; $reason[] = 'Attendance near threshold'; }
    }
    return ['status'=>$status, 'reason'=>$reason, 'avg'=>$avg, 'attendance'=>$att, 'failed'=>$failed];
}

/**
 * Trend across semesters for one student (chronological).
 * Returns [['semester_id','label','avg'],...]
 */
function studentTrend(int $studentId): array {
    $stmt = db()->prepare("
        SELECT r.semester_id, AVG(r.percentage) AS avg_pct,
               sem.semester_name, y.year_name, y.start_date
        FROM results r
        JOIN semesters sem ON sem.id = r.semester_id
        JOIN academic_years y ON y.id = sem.academic_year_id
        WHERE r.student_id = ? AND r.status='published'
        GROUP BY r.semester_id, sem.semester_name, y.year_name, y.start_date
        ORDER BY y.start_date, sem.start_date
    ");
    $stmt->execute([$studentId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['avg'] = round((float)$r['avg_pct'], 2);
        $r['label'] = $r['year_name'] . ' / ' . $r['semester_name'];
    }
    return $rows;
}

/**
 * Pearson correlation between a factor rating and student average.
 * Returns ['r'=>float, 'n'=>int, 'points'=>[[rating,avg],...]]
 * This is a statistical association only — NOT causation.
 */
function factorAssociation(int $factorId, int $semesterId): array {
    $stmt = db()->prepare("
        SELECT sf.student_id, sf.rating,
               (SELECT AVG(percentage) FROM results r WHERE r.student_id = sf.student_id AND r.semester_id = sf.semester_id AND r.status='published') AS avg_pct
        FROM student_factors sf
        WHERE sf.factor_id = ? AND sf.semester_id = ?
    ");
    $stmt->execute([$factorId, $semesterId]);
    $rows = $stmt->fetchAll();

    $x = []; $y = []; $points = [];
    foreach ($rows as $r) {
        if ($r['avg_pct'] === null) continue;
        $x[] = (float)$r['rating'];
        $y[] = (float)$r['avg_pct'];
        $points[] = ['x'=>(float)$r['rating'], 'y'=>round((float)$r['avg_pct'],2)];
    }
    $n = count($x);
    if ($n < 3) return ['r'=>null, 'n'=>$n, 'points'=>$points];

    $mx = array_sum($x)/$n; $my = array_sum($y)/$n;
    $num=0; $dx=0; $dy=0;
    for ($i=0; $i<$n; $i++) {
        $a = $x[$i]-$mx; $b = $y[$i]-$my;
        $num += $a*$b; $dx += $a*$a; $dy += $b*$b;
    }
    $denom = sqrt($dx*$dy);
    $r = $denom == 0 ? 0 : $num/$denom;
    return ['r'=>round($r,3), 'n'=>$n, 'points'=>$points];
}

/** Interpret an r value in academic language. */
function describeCorrelation(?float $r): string {
    if ($r === null) return 'Not enough data';
    $a = abs($r);
    $dir = $r >= 0 ? 'positive' : 'negative';
    if ($a < 0.1) return 'Negligible association';
    if ($a < 0.3) return "Weak $dir association";
    if ($a < 0.5) return "Moderate $dir association";
    if ($a < 0.7) return "Strong $dir association";
    return "Very strong $dir association";
}

/** Attention badge colour. */
function attentionBadge(string $status): string {
    $cls = $status === 'Requires Attention' ? 'danger'
         : ($status === 'Monitor' ? 'warning text-dark' : 'success');
    return '<span class="badge bg-'.$cls.'">'.e($status).'</span>';
}