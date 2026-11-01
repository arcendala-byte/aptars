<?php
declare(strict_types=1);

/** Render the one-shot flash message set via setFlash(). */
function displayFlash(): void {
    $f = getFlash();
    if (!$f) return;
    echo '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">'
       . e($f['message'])
       . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

/** Coloured badge for a status enum value. */
function statusBadge(string $status): string {
    $map = [
        'active'     => 'success',
        'inactive'   => 'secondary',
        'closed'     => 'dark',
        'archived'   => 'secondary',
        'graduated'  => 'info',
        'suspended'  => 'danger',
        'registered' => 'primary',
        'completed'  => 'success',
        'dropped'    => 'warning',
        'published'  => 'success',
        'draft'      => 'secondary',
    ];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . e(ucfirst($status)) . '</span>';
}

/** Paginated query wrapper. */
function paginate(string $countSql, string $dataSql, array $params, int $page = 1, int $perPage = 15): array {
    $pdo = db();
    $page = max(1, $page);

    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $pages = max(1, (int)ceil($total / $perPage));
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare($dataSql . " LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    return ['rows'=>$rows, 'total'=>$total, 'pages'=>$pages, 'page'=>$page, 'perPage'=>$perPage];
}

function pageUrl(array $params, int $newPage): string {
    $params['page'] = $newPage;
    return '?' . http_build_query($params);
}

function paginationLinks(int $page, int $pages, array $params): string {
    if ($pages <= 1) return '';
    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    $start = max(1, $page - 2);
    $end   = min($pages, $page + 2);
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e(pageUrl($params, 1)) . '">&laquo;</a></li>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $page ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . e(pageUrl($params, $i)) . '">' . $i . '</a></li>';
    }
    if ($end < $pages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e(pageUrl($params, $pages)) . '">&raquo;</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

/** Build <option> tags from a rows array. */
function selectOptions(array $rows, string $valueKey, string $labelKey, $selected = null, string $placeholder = ''): string {
    $html = $placeholder !== '' ? '<option value="">' . e($placeholder) . '</option>' : '';
    foreach ($rows as $r) {
        $v = (string)$r[$valueKey];
        $sel = ((string)$selected === $v) ? ' selected' : '';
        $html .= '<option value="' . e($v) . '"' . $sel . '>' . e($r[$labelKey]) . '</option>';
    }
    return $html;
}

/** Render an errors array as a Bootstrap alert. */
function errorList(array $errors): string {
    if (!$errors) return '';
    $html = '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($errors as $er) $html .= '<li>' . e($er) . '</li>';
    $html .= '</ul></div>';
    return $html;
}