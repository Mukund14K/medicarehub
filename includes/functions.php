<?php
/**
 * Helper functions for MedicareHub Clinic Management System
 */

/**
 * Shorthand for htmlspecialchars with ENT_QUOTES and UTF-8 encoding.
 */
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Calculate pagination details from a count query.
 *
 * @param PDO    $pdo        Database connection
 * @param string $countQuery SQL that returns a single COUNT(*) value
 * @param array  $params     Bound parameters for the count query
 * @param int    $perPage    Records per page
 * @return array ['total_pages', 'current_page', 'offset', 'total_records']
 */
function paginate(PDO $pdo, string $countQuery, array $params, int $perPage = 10): array
{
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalRecords = (int) $stmt->fetchColumn();

    $totalPages = max(1, (int) ceil($totalRecords / $perPage));

    $currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($currentPage < 1) {
        $currentPage = 1;
    }
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $perPage;

    return [
        'total_pages'   => $totalPages,
        'current_page'  => $currentPage,
        'offset'        => $offset,
        'total_records' => $totalRecords,
    ];
}

/**
 * Render Bootstrap 5 pagination navigation.
 *
 * Preserves any existing query-string parameters in $baseUrl.
 *
 * @param int    $totalPages  Total number of pages
 * @param int    $currentPage Current active page
 * @param string $baseUrl     Base URL (may already contain query params)
 * @return string HTML string
 */
function renderPagination(int $totalPages, int $currentPage, string $baseUrl): string
{
    if ($totalPages <= 1) {
        return '';
    }

    // Parse existing query parameters so we can preserve them
    $urlParts = parse_url($baseUrl);
    $path = $urlParts['path'] ?? '';
    $existingParams = [];
    if (isset($urlParts['query'])) {
        parse_str($urlParts['query'], $existingParams);
    }

    // Helper to build a page URL
    $buildUrl = function (int $page) use ($path, $existingParams): string {
        $params = $existingParams;
        $params['page'] = $page;
        return $path . '?' . http_build_query($params);
    };

    $html = '<nav aria-label="Page navigation">';
    $html .= '<ul class="pagination justify-content-center">';

    // Previous button
    if ($currentPage <= 1) {
        $html .= '<li class="page-item disabled"><span class="page-link">&laquo; Previous</span></li>';
    } else {
        $html .= '<li class="page-item"><a class="page-link" href="' . e($buildUrl($currentPage - 1)) . '">&laquo; Previous</a></li>';
    }

    // Page numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . e($buildUrl($i)) . '">' . $i . '</a></li>';
        }
    }

    // Next button
    if ($currentPage >= $totalPages) {
        $html .= '<li class="page-item disabled"><span class="page-link">Next &raquo;</span></li>';
    } else {
        $html .= '<li class="page-item"><a class="page-link" href="' . e($buildUrl($currentPage + 1)) . '">Next &raquo;</a></li>';
    }

    $html .= '</ul>';
    $html .= '</nav>';

    return $html;
}

/**
 * Export an associative array as a CSV download.
 *
 * The first row contains column headers derived from the array keys of the
 * first data row.  Terminates script execution after output.
 *
 * @param array  $data     Array of associative arrays
 * @param string $filename Download filename (e.g. "patients.csv")
 */
function exportCSV(array $data, string $filename): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    if (!empty($data)) {
        // Column headers from the keys of the first row
        fputcsv($output, array_keys($data[0]));

        // Data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit;
}

/**
 * Format a date string as "d M Y" (e.g. 17 Nov 2026).
 */
function formatDate(string $date): string
{
    return date('d M Y', strtotime($date));
}

/**
 * Format an amount as Indian Rupees (₹XX.XX).
 */
function formatCurrency(float $amount): string
{
    return '₹' . number_format($amount, 2);
}

/**
 * Return a Bootstrap badge class based on appointment status.
 */
function getStatusBadgeClass(string $status): string
{
    return match (strtolower($status)) {
        'scheduled' => 'bg-primary',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger',
        default     => 'bg-secondary',
    };
}

/**
 * Return a Bootstrap Icons icon class based on appointment status.
 */
function getStatusIcon(string $status): string
{
    return match (strtolower($status)) {
        'scheduled' => 'bi bi-clock',
        'completed' => 'bi bi-check-circle',
        'cancelled' => 'bi bi-x-circle',
        default     => 'bi bi-question-circle',
    };
}

/**
 * Sanitize user input by trimming whitespace and stripping HTML tags.
 */
function sanitizeInput(string $input): string
{
    return strip_tags(trim($input));
}

/**
 * Validate that a phone number is exactly 10 digits.
 */
function validatePhone(string $phone): bool
{
    return (bool) preg_match('/^\d{10}$/', $phone);
}

/**
 * Validate that an age is between 0 and 150 (inclusive).
 */
function validateAge(int $age): bool
{
    return $age >= 0 && $age <= 150;
}

/**
 * Generate Bootstrap 5 breadcrumb navigation.
 *
 * @param array $items Each item: ['label' => string, 'url' => string|null].
 *                     The last item is rendered as the active breadcrumb.
 * @return string HTML string
 */
function generateBreadcrumb(array $items): string
{
    if (empty($items)) {
        return '';
    }

    $html = '<nav aria-label="breadcrumb">';
    $html .= '<ol class="breadcrumb">';

    $lastIndex = count($items) - 1;

    foreach ($items as $index => $item) {
        if ($index === $lastIndex) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . e($item['label']) . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . e($item['url'] ?? '#') . '">' . e($item['label']) . '</a></li>';
        }
    }

    $html .= '</ol>';
    $html .= '</nav>';

    return $html;
}
