<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Record;
use App\Models\Team;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordExporter
{
    /**
     * Export رکوردهای یک پروژه
     *
     * @param  array<int, string>  $columns  اگر خالی باشد، همه‌ی کلیدها
     */
    public function exportProject(
        Project $project,
        array $columns = [],
        string $format = 'json',
    ): StreamedResponse|BinaryFileResponse {
        $records = $project->records()->with('values')->get();

        return $this->exportRecords($records, $columns, $format, 'project-'.$project->uuid);
    }

    /**
     * Export کل تیم (workspace) - همه‌ی پروژه‌ها
     */
    public function exportTeam(
        Team $team,
        array $columns = [],
        string $format = 'json',
    ): StreamedResponse|BinaryFileResponse {
        $projects = $team->projects()->with(['records.values'])->get();

        return $this->exportWorkspace($team, $projects, $columns, $format);
    }

    /**
     * Export یک مجموعه‌ی دلخواه از رکوردها (مثلاً selected records)
     */
    public function exportSelection(
        Collection $records,
        array $columns = [],
        string $format = 'json',
        string $nameHint = 'selection',
    ): StreamedResponse|BinaryFileResponse {
        return $this->exportRecords($records, $columns, $format, $nameHint);
    }

    // ================ Internal ================

    protected function exportRecords(
        Collection $records,
        array $columns,
        string $format,
        string $nameHint,
    ): StreamedResponse|BinaryFileResponse {
        $data = $records->map(fn (Record $r) => $this->recordToArray($r, $columns))->all();

        return $this->stream($data, $format, $nameHint);
    }

    protected function exportWorkspace(
        Team $team,
        Collection $projects,
        array $columns,
        string $format,
    ): StreamedResponse|BinaryFileResponse {
        if ($format === 'sqlite') {
            return $this->exportWorkspaceSqlite($team, $projects);
        }

        $workspace = [
            '_meta' => [
                'exported_at' => now()->toIso8601String(),
                'team_uuid' => $team->uuid,
                'team_name' => $team->name,
                'projects_count' => $projects->count(),
                'total_records' => $projects->sum(fn ($p) => $p->records->count()),
            ],
            'projects' => $projects->map(fn (Project $p) => [
                'uuid' => $p->uuid,
                'name' => $p->name,
                'description' => $p->description,
                'created_at' => $p->created_at->toIso8601String(),
                'records' => $p->records->map(fn (Record $r) => $this->recordToArray($r, $columns))->all(),
            ])->all(),
        ];

        $filename = 'workspace-'.$team->uuid.'-'.now()->format('Ymd-His').'.'.$format;
        $payload = $format === 'ndjson'
            ? collect($workspace['projects'])->flatMap(fn ($p) => array_map(
                fn ($r) => array_merge($r, ['_project_uuid' => $p['uuid'], '_project_name' => $p['name']]),
                $p['records']
            ))->all()
            : $workspace;

        return response()->streamDownload(function () use ($payload, $format) {
            if ($format === 'ndjson') {
                foreach ($payload as $row) {
                    echo json_encode($row, JSON_UNESCAPED_UNICODE)."\n";
                }
            } else {
                echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        }, $filename, [
            'Content-Type' => $format === 'ndjson' ? 'application/x-ndjson' : 'application/json',
        ]);
    }

    protected function recordToArray(Record $record, array $columns): array
    {
        $data = $record->data;

        // فیلتر ستون‌ها (فقط اگر مشخص شده باشد)
        if (! empty($columns)) {
            $data = $this->filterColumns($data, $columns);
        }

        return [
            'uuid' => $record->uuid,
            'title' => $record->title,
            'note' => $record->note,
            'created_at' => $record->created_at->toIso8601String(),
            'updated_at' => $record->updated_at->toIso8601String(),
            'data' => $data,
        ];
    }

    /**
     * فیلتر بازگشتی روی درخت مقادیر بر اساس لیست ستون‌ها
     * پشتیبانی از dot notation: "contact.phone"
     */
    protected function filterColumns(array $data, array $columns): array
    {
        $result = [];

        foreach ($columns as $column) {
            // اگر column شامل نقطه است، یعنی مسیر تودرتو
            if (str_contains($column, '.')) {
                $value = data_get($data, $column);
                if ($value !== null) {
                    data_set($result, $column, $value);
                }
            } else {
                // ستون سطح بالا — اگر آرایه است، کاملاً کپی کن
                if (array_key_exists($column, $data)) {
                    $result[$column] = $data[$column];
                }
            }
        }

        return $result;
    }

    protected function stream(array $data, string $format, string $nameHint): StreamedResponse|BinaryFileResponse
    {
        $filename = $nameHint.'-'.now()->format('Ymd-His').'.'.$format;

        if ($format === 'sqlite') {
            return $this->streamSqlite($data, $filename);
        }

        return response()->streamDownload(function () use ($data, $format) {
            if ($format === 'ndjson') {
                foreach ($data as $row) {
                    echo json_encode($row, JSON_UNESCAPED_UNICODE)."\n";
                }
            } else {
                echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        }, $filename, [
            'Content-Type' => $format === 'ndjson' ? 'application/x-ndjson' : 'application/json',
        ]);
    }

    protected function streamSqlite(array $data, string $filename): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'exp_').'.sqlite';
        $pdo = new \PDO('sqlite:'.$tmp);

        $pdo->exec('
            CREATE TABLE records (
                id INTEGER PRIMARY KEY,
                uuid TEXT UNIQUE,
                title TEXT,
                note TEXT,
                created_at TEXT,
                updated_at TEXT,
                data_json TEXT
            );
            CREATE TABLE record_values (
                id INTEGER PRIMARY KEY,
                record_uuid TEXT,
                parent_uuid TEXT,
                key TEXT,
                value_type TEXT,
                value_string TEXT,
                value_text TEXT,
                value_number REAL,
                value_date TEXT,
                value_boolean INTEGER
            );
            CREATE INDEX idx_rv_record ON record_values(record_uuid);
            CREATE INDEX idx_rv_key ON record_values(key);
        ');

        $stmtR = $pdo->prepare('INSERT INTO records (uuid, title, note, created_at, updated_at, data_json) VALUES (?,?,?,?,?,?)');
        $stmtV = $pdo->prepare('INSERT INTO record_values (record_uuid, parent_uuid, key, value_type, value_string, value_text, value_number, value_date, value_boolean) VALUES (?,?,?,?,?,?,?,?,?)');

        $pdo->beginTransaction();

        // نگاشت values به uuid برای parent
        foreach ($data as $row) {
            $stmtR->execute([
                $row['uuid'],
                $row['title'],
                $row['note'],
                $row['created_at'],
                $row['updated_at'],
                json_encode($row['data'], JSON_UNESCAPED_UNICODE),
            ]);
        }

        $pdo->commit();

        return response()->download($tmp, $filename)->deleteFileAfterSend(true);
    }

    protected function exportWorkspaceSqlite(Team $team, Collection $projects): BinaryFileResponse
    {
        $filename = 'workspace-'.$team->uuid.'-'.now()->format('Ymd-His').'.sqlite';
        $tmp = tempnam(sys_get_temp_dir(), 'wsp_').'.sqlite';

        $pdo = new \PDO('sqlite:'.$tmp);
        $pdo->exec('
            CREATE TABLE projects (
                uuid TEXT PRIMARY KEY,
                name TEXT,
                description TEXT,
                created_at TEXT
            );
            CREATE TABLE records (
                uuid TEXT PRIMARY KEY,
                project_uuid TEXT,
                title TEXT,
                note TEXT,
                created_at TEXT,
                updated_at TEXT,
                data_json TEXT
            );
            CREATE TABLE record_values (
                record_uuid TEXT,
                key TEXT,
                value_type TEXT,
                value_string TEXT,
                value_text TEXT,
                value_number REAL,
                value_date TEXT,
                value_boolean INTEGER
            );
            CREATE INDEX idx_rv_record ON record_values(record_uuid);
        ');

        $stmtP = $pdo->prepare('INSERT INTO projects VALUES (?,?,?,?)');
        $stmtR = $pdo->prepare('INSERT INTO records VALUES (?,?,?,?,?,?,?)');
        $stmtV = $pdo->prepare('INSERT INTO record_values VALUES (?,?,?,?,?,?,?,?)');

        $pdo->beginTransaction();

        foreach ($projects as $project) {
            $stmtP->execute([
                $project->uuid,
                $project->name,
                $project->description,
                $project->created_at->toIso8601String(),
            ]);

            foreach ($project->records as $record) {
                $stmtR->execute([
                    $record->uuid,
                    $project->uuid,
                    $record->title,
                    $record->note,
                    $record->created_at->toIso8601String(),
                    $record->updated_at->toIso8601String(),
                    json_encode($record->data, JSON_UNESCAPED_UNICODE),
                ]);

                foreach ($record->values as $v) {
                    $stmtV->execute([
                        $record->uuid,
                        $v->key,
                        $v->value_type,
                        $v->value_string,
                        $v->value_text,
                        $v->value_number,
                        $v->value_date?->toIso8601String(),
                        $v->value_boolean === null ? null : (int) $v->value_boolean,
                    ]);
                }
            }
        }

        $pdo->commit();

        return response()->download($tmp, $filename)->deleteFileAfterSend(true);
    }
}