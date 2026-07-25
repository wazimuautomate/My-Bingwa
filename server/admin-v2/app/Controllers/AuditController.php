<?php
/**
 * Append-only audit log viewer. Read-only: this UI never updates or deletes audit rows.
 * Filters by actor, action, entity, date range and publish version. CSV export for
 * authorised roles. Sensitive values are already masked at write time.
 */

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Support\Csv;

final class AuditController extends Controller
{
    private function table(): string { return Database::table('audit_logs'); }

    public function index(Request $request): void
    {
        $this->guard('audit.view');
        $f = [
            'actor' => (string) $request->get('actor', ''),
            'action' => (string) $request->get('action', ''),
            'entity' => (string) $request->get('entity', ''),
            'from' => (string) $request->get('from', ''),
            'to' => (string) $request->get('to', ''),
            'version' => (string) $request->get('version', ''),
        ];
        [$where, $params] = $this->buildWhere($f);
        $page = max(1, (int) $request->get('page', 1));
        $per = 40;
        $total = (int) (Database::scalar('SELECT COUNT(*) FROM ' . $this->table() . " {$where}", $params) ?? 0);
        $rows = Database::fetchAll(
            'SELECT * FROM ' . $this->table() . " {$where} ORDER BY created_at DESC LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        );
        $this->view('audit/index', [
            'activeNav' => 'audit', 'pageTitle' => 'Audit log',
            'rows' => $rows, 'filters' => $f, 'page' => $page, 'total' => $total, 'per' => $per,
            'actions' => Database::fetchAll('SELECT DISTINCT action FROM ' . $this->table() . ' ORDER BY action'),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $this->guard('audit.view');
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) { $this->redirect('/audit'); }
        $this->view('audit/show', [
            'activeNav' => 'audit', 'pageTitle' => 'Audit entry',
            'row' => $row,
            'diff' => json_decode((string) ($row['diff_json'] ?? ''), true) ?: [],
        ]);
    }

    public function exportCsv(Request $request): void
    {
        $this->guard('audit.view');
        [$where, $params] = $this->buildWhere([
            'actor' => (string) $request->get('actor', ''), 'action' => (string) $request->get('action', ''),
            'entity' => (string) $request->get('entity', ''), 'from' => (string) $request->get('from', ''),
            'to' => (string) $request->get('to', ''), 'version' => (string) $request->get('version', ''),
        ]);
        $rows = Database::fetchAll('SELECT * FROM ' . $this->table() . " {$where} ORDER BY created_at DESC LIMIT 5000", $params);
        Csv::stream('mybingwa-audit.csv',
            ['time_utc', 'time_nairobi', 'actor', 'role', 'action', 'entity_type', 'entity_id', 'version', 'ip', 'success'],
            array_map(fn($r) => [
                $r['created_at'], fmt_nairobi($r['created_at'], 'Y-m-d H:i'),
                $r['actor_name'], $r['actor_role'], $r['action'], $r['entity_type'], $r['entity_id'],
                $r['release_version'], $r['ip'], $r['success'] ? 'ok' : 'fail',
            ], $rows)
        );
    }

    private function buildWhere(array $f): array
    {
        $c = [];
        $p = [];
        if ($f['actor'] !== '')   { $c[] = 'actor_name LIKE ?'; $p[] = '%' . $f['actor'] . '%'; }
        if ($f['action'] !== '')  { $c[] = 'action = ?'; $p[] = $f['action']; }
        if ($f['entity'] !== '')  { $c[] = '(entity_type LIKE ? OR entity_id LIKE ?)'; $p[] = '%' . $f['entity'] . '%'; $p[] = '%' . $f['entity'] . '%'; }
        if ($f['from'] !== '')    { $c[] = 'created_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
        if ($f['to'] !== '')      { $c[] = 'created_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
        if ($f['version'] !== '') { $c[] = 'release_version = ?'; $p[] = (int) $f['version']; }
        return [$c ? ('WHERE ' . implode(' AND ', $c)) : '', $p];
    }
}
