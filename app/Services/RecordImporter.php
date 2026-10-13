<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Project;
use App\Models\Record;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordImporter
{
    /**
     * پیش‌نمایش فایل بدون ذخیره
     */
    public function preview(UploadedFile $file): array
    {
        $parsed = $this->parseFile($file);
        $analysis = $this->analyze($parsed);

        return array_merge($analysis, [
            'format' => $this->detectFormat($file),
            'records' => $parsed, // برای استفاده در import بعدی
        ]);
    }

    /**
     * Import واقعی + ثبت batch
     */
    public function import(
        Project $project,
        array $records,
        UploadedFile $file,
        array $options = [],
        ?array $previewData = null,
    ): ImportBatch {
        $preserveUuids = $options['preserve_uuids'] ?? true;
        $format = $this->detectFormat($file);

        return DB::transaction(function () use ($project, $records, $file, $preserveUuids, $format, $previewData) {
            $batch = ImportBatch::create([
                'team_id' => $project->team_id,
                'project_id' => $project->id,
                'user_id' => Auth::id(),
                'original_filename' => $file->getClientOriginalName(),
                'format' => $format,
                'record_count' => 0,
                'file_size' => $file->getSize() ?? 0,
                'status' => 'pending',
                'metadata' => [
                    'key_stats' => $previewData['key_stats'] ?? [],
                    'unique_keys' => $previewData['unique_keys'] ?? 0,
                    'max_depth' => $previewData['max_depth'] ?? 0,
                    'has_nested' => $previewData['has_nested'] ?? false,
                    'duplicate_uuids' => $previewData['duplicate_uuids'] ?? 0,
                ],
            ]);

            $count = 0;

            foreach ($records as $row) {
                $uuid = $row['uuid'] ?? null;

                if ($preserveUuids && $uuid && Record::where('uuid', $uuid)->exists()) {
                    $uuid = null;
                }

                $record = Record::create([
                    'uuid' => $uuid,
                    'title' => $row['title'] ?? null,
                    'note' => $row['note'] ?? null,
                    'import_batch_id' => $batch->id,
                    'import_source' => 'file-import',
                    'created_by' => Auth::id(),
                ]);

                $project->records()->attach($record->id);

                $data = $row['data'] ?? [];
                if (! empty($data)) {
                    $record->syncValues($data);
                }

                $count++;
            }

            $batch->update([
                'record_count' => $count,
                'status' => 'completed',
            ]);

            return $batch->fresh();
        });
    }

    /**
     * برگرداندن import — حذف همه‌ی رکوردهای یک batch
     */
    public function revert(ImportBatch $batch): int
    {
        if ($batch->status === 'reverted') {
            return 0;
        }

        return DB::transaction(function () use ($batch) {
            $records = Record::where('import_batch_id', $batch->id)->get();
            $count = 0;

            foreach ($records as $record) {
                // حذف از همه‌ی پروژه‌ها
                $record->projects()->detach();

                // حذف کامل (چون این رکورد فقط برای این import بوده)
                $record->forceDelete();
                $count++;
            }

            $batch->update([
                'status' => 'reverted',
                'reverted_at' => now(),
                'reverted_by' => Auth::id(),
            ]);

            return $count;
        });
    }

    // ================ Parsing ================

    protected function detectFormat(UploadedFile $file): string
    {
        $name = strtolower($file->getClientOriginalName());

        if (str_ends_with($name, '.ndjson') || str_ends_with($name, '.jsonl')) {
            return 'ndjson';
        }
        if (str_ends_with($name, '.sqlite') || str_ends_with($name, '.sqlite3') || str_ends_with($name, '.db')) {
            return 'sqlite';
        }
        return 'json';
    }

    protected function parseFile(UploadedFile $file): array
    {
        return match ($this->detectFormat($file)) {
            'ndjson' => $this->parseNdjson($file),
            'sqlite' => $this->parseSqlite($file),
            default => $this->parseJson($file),
        };
    }

    protected function parseJson(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('فایل JSON معتبر نیست: '.json_last_error_msg());
        }

        // پشتیبانی از workspace export
        if (isset($data['projects']) && is_array($data['projects'])) {
            return $this->flattenWorkspace($data);
        }

        if (! is_array($data)) {
            throw new \RuntimeException('ساختار JSON باید آرایه باشد.');
        }

        return $data;
    }

    protected function flattenWorkspace(array $workspace): array
    {
        $result = [];
        foreach ($workspace['projects'] as $project) {
            foreach ($project['records'] ?? [] as $record) {
                $record['_project_uuid'] = $project['uuid'] ?? null;
                $record['_project_name'] = $project['name'] ?? null;
                $result[] = $record;
            }
        }
        return $result;
    }

    protected function parseNdjson(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $records = [];
        $lineNo = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNo++;
            $line = trim($line);
            if ($line === '') continue;

            $row = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                fclose($handle);
                throw new \RuntimeException("خط {$lineNo} معتبر نیست.");
            }
            $records[] = $row;
        }

        fclose($handle);

        return $records;
    }

    protected function parseSqlite(UploadedFile $file): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'imp_').'.sqlite';
        copy($file->getRealPath(), $tmp);

        try {
            $pdo = new \PDO('sqlite:'.$tmp);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $rows = $pdo->query('SELECT * FROM records')->fetchAll(\PDO::FETCH_ASSOC);

            return array_map(fn ($r) => [
                'uuid' => $r['uuid'] ?? null,
                'title' => $r['title'] ?? null,
                'note' => $r['note'] ?? null,
                'data' => isset($r['data_json'])
                    ? (json_decode($r['data_json'], true) ?? [])
                    : [],
            ], $rows);
        } finally {
            @unlink($tmp);
        }
    }

    // ================ Analysis ================

    protected function analyze(array $records): array
    {
        $total = count($records);
        $keyStats = [];
        $uuidSet = [];
        $duplicateUuids = 0;
        $maxDepth = 0;
        $hasNested = false;

        foreach ($records as $row) {
            $uuid = $row['uuid'] ?? null;
            if ($uuid) {
                if (isset($uuidSet[$uuid])) $duplicateUuids++;
                $uuidSet[$uuid] = true;
            }

            $this->walkKeys($row['data'] ?? [], function ($key, $depth, $hasChildren) use (&$keyStats, &$maxDepth, &$hasNested) {
                $keyStats[$key] = ($keyStats[$key] ?? 0) + 1;
                $maxDepth = max($maxDepth, $depth);
                if ($hasChildren) $hasNested = true;
            });
        }

        arsort($keyStats);

        return [
            'total' => $total,
            'duplicate_uuids' => $duplicateUuids,
            'key_stats' => $keyStats,
            'unique_keys' => count($keyStats),
            'max_depth' => $maxDepth,
            'has_nested' => $hasNested,
            'samples' => array_slice($records, 0, 5),
        ];
    }

    protected function walkKeys(array $data, callable $cb, int $depth = 0): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'value') continue;

            $hasChildren = is_array($value);

            $cb($key, $depth, $hasChildren);

            if ($hasChildren) {
                $children = $value;
                unset($children['value']);
                $this->walkKeys($children, $cb, $depth + 1);
            }
        }
    }
}