<?php
/**
 * Atomically allocates the next invoice number for the given year, formatted
 * INV-YYYY-000X. Must be called inside an already-open PDO transaction so the
 * row lock (SELECT ... FOR UPDATE) is held until the caller commits.
 */
function next_invoice_no(PDO $pdo, $year) {
    // Row-level lock to serialize concurrent sales; SQLite (used only by the
    // local test harness) has no FOR UPDATE and relies on its own file lock instead.
    $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    $stmt = $pdo->prepare('SELECT last_seq FROM invoice_counters WHERE year_val = ?' . $lockClause);
    $stmt->execute([$year]);
    $lastSeq = $stmt->fetchColumn();

    if ($lastSeq === false) {
        $pdo->prepare('INSERT INTO invoice_counters (year_val, last_seq) VALUES (?, 0)')->execute([$year]);
        $lastSeq = 0;
    }

    $nextSeq = (int)$lastSeq + 1;
    $pdo->prepare('UPDATE invoice_counters SET last_seq = ? WHERE year_val = ?')->execute([$nextSeq, $year]);

    return sprintf('INV-%d-%04d', $year, $nextSeq);
}
