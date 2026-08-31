<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class QueueManagementService
{
    protected function pidFile(): string
    {
        return storage_path('app/queue-worker.pid');
    }

    protected function startedAtFile(): string
    {
        return storage_path('app/queue-worker.started_at');
    }

    protected function workerLogFile(): string
    {
        return storage_path('logs/queue-worker.log');
    }

    protected function supervisorProgram(): string
    {
        return (string) config('queue.supervisor.program', 'installment-queue');
    }

    protected function usesSupervisor(): bool
    {
        return (bool) config('queue.supervisor.enabled', false)
            && PHP_OS_FAMILY !== 'Windows'
            && function_exists('shell_exec');
    }

    protected function supervisorCtl(string $action): ?string
    {
        $program = $this->supervisorProgram();

        $command = match ($action) {
            'status' => "sudo supervisorctl status {$program}:* 2>&1",
            'start' => "sudo supervisorctl start {$program}:* 2>&1",
            'stop' => "sudo supervisorctl stop {$program}:* 2>&1",
            'restart' => "sudo supervisorctl restart {$program}:* 2>&1",
            default => null,
        };

        if ($command === null) {
            return null;
        }

        return shell_exec($command);
    }

    /**
     * @return array{running: bool, pid: int|null, uptime: string|null, state: string|null}
     */
    protected function parseSupervisorStatus(?string $output): array
    {
        $program = $this->supervisorProgram();

        if (!is_string($output) || trim($output) === '') {
            return [
                'running' => false,
                'pid' => null,
                'uptime' => null,
                'state' => null,
            ];
        }

        foreach (explode("\n", trim($output)) as $line) {
            if (!str_contains($line, $program)) {
                continue;
            }

            $running = str_contains($line, 'RUNNING');
            $pid = null;
            $uptime = null;

            if (preg_match('/pid\s+(\d+)/', $line, $pidMatch)) {
                $pid = (int) $pidMatch[1];
            }

            if (preg_match('/uptime\s+([^\s,]+)/', $line, $uptimeMatch)) {
                $uptime = $uptimeMatch[1];
            }

            $state = trim((string) preg_replace('/^.*?\s+(RUNNING|STOPPED|STARTING|BACKOFF|FATAL|EXITED|UNKNOWN).*$/', '$1', $line));

            return [
                'running' => $running,
                'pid' => $pid,
                'uptime' => $uptime,
                'state' => $state !== $line ? $state : null,
            ];
        }

        return [
            'running' => false,
            'pid' => null,
            'uptime' => null,
            'state' => 'NOT_FOUND',
        ];
    }

    protected function isProcessRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('tasklist /FI "PID eq ' . $pid . '" 2>NUL');

            return is_string($output) && str_contains($output, (string) $pid);
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        $output = shell_exec('ps -p ' . escapeshellarg((string) $pid) . ' -o pid=');

        return is_string($output) && trim($output) !== '';
    }

    protected function readPid(): ?int
    {
        if (!File::exists($this->pidFile())) {
            return null;
        }

        $pid = (int) trim((string) File::get($this->pidFile()));

        return $pid > 0 ? $pid : null;
    }

    protected function clearPidFile(): void
    {
        if (File::exists($this->pidFile())) {
            File::delete($this->pidFile());
        }

        if (File::exists($this->startedAtFile())) {
            File::delete($this->startedAtFile());
        }
    }

    protected function findWorkerPidFromProcessList(): ?int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        $needle = base_path('artisan') . ' queue:work';
        $output = shell_exec('pgrep -af ' . escapeshellarg('queue:work') . ' 2>/dev/null');

        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        foreach (explode("\n", trim($output)) as $line) {
            if (!str_contains($line, $needle)) {
                continue;
            }

            if (preg_match('/^(\d+)/', trim($line), $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    protected function resolveWorkerPid(): ?int
    {
        $pid = $this->readPid();

        if ($pid !== null && $this->isProcessRunning($pid)) {
            return $pid;
        }

        $discovered = $this->findWorkerPidFromProcessList();

        if ($discovered !== null) {
            File::put($this->pidFile(), (string) $discovered);

            return $discovered;
        }

        $this->clearPidFile();

        return null;
    }

    protected function baseStatus(): array
    {
        return [
            'pending_jobs' => (int) DB::table('jobs')->count(),
            'failed_jobs' => (int) DB::table('failed_jobs')->count(),
            'manager' => $this->usesSupervisor() ? 'supervisor' : 'manual',
            'program' => $this->usesSupervisor() ? $this->supervisorProgram() : null,
        ];
    }

    /**
     * @return array{running: bool, pid: int|null, pending_jobs: int, failed_jobs: int, started_at: string|null, manager: string, program: string|null, uptime: string|null, state: string|null}
     */
    public function getStatus(): array
    {
        if ($this->usesSupervisor()) {
            $supervisor = $this->parseSupervisorStatus($this->supervisorCtl('status'));

            return array_merge($this->baseStatus(), [
                'running' => $supervisor['running'],
                'pid' => $supervisor['pid'],
                'started_at' => null,
                'uptime' => $supervisor['uptime'],
                'state' => $supervisor['state'],
            ]);
        }

        $pid = $this->resolveWorkerPid();
        $running = $pid !== null;

        $startedAt = null;
        if ($running && File::exists($this->startedAtFile())) {
            $startedAt = trim((string) File::get($this->startedAtFile())) ?: null;
        }

        return array_merge($this->baseStatus(), [
            'running' => $running,
            'pid' => $pid,
            'started_at' => $startedAt,
            'uptime' => null,
            'state' => $running ? 'RUNNING' : 'STOPPED',
        ]);
    }

    /**
     * @return array{running: bool, pid: int|null, pending_jobs: int, failed_jobs: int, started_at: string|null, manager: string, program: string|null, uptime: string|null, state: string|null, already_running?: bool}
     */
    public function startWorker(): array
    {
        $status = $this->getStatus();

        if ($status['running']) {
            return array_merge($status, ['already_running' => true]);
        }

        if ($this->usesSupervisor()) {
            $output = $this->supervisorCtl('start');
            Log::info('Supervisor queue start', ['output' => $output]);

            return $this->getStatus();
        }

        if (!function_exists('shell_exec')) {
            abort(503, 'تعذر تشغيل قائمة الانتظار: shell_exec غير متاح على الخادم');
        }

        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $log = $this->workerLogFile();

        File::ensureDirectoryExists(dirname($log));

        $command = sprintf(
            'cd %s && nohup %s %s queue:work --sleep=3 --tries=3 --timeout=120 >> %s 2>&1 & echo $!',
            escapeshellarg(base_path()),
            escapeshellarg($php),
            escapeshellarg($artisan),
            escapeshellarg($log)
        );

        $output = shell_exec($command);
        $pid = is_string($output) ? (int) trim($output) : 0;

        if ($pid <= 0 || !$this->isProcessRunning($pid)) {
            Log::error('Failed to start queue worker', ['output' => $output]);
            abort(500, 'تعذر تشغيل قائمة الانتظار');
        }

        File::put($this->pidFile(), (string) $pid);
        File::put($this->startedAtFile(), now()->toIso8601String());

        return $this->getStatus();
    }

    /**
     * @return array{running: bool, pid: int|null, pending_jobs: int, failed_jobs: int, started_at: string|null, manager: string, program: string|null, uptime: string|null, state: string|null, stopped: bool}
     */
    public function stopWorker(): array
    {
        if ($this->usesSupervisor()) {
            $wasRunning = $this->getStatus()['running'];
            $output = $this->supervisorCtl('stop');
            Log::info('Supervisor queue stop', ['output' => $output]);

            return array_merge($this->getStatus(), ['stopped' => $wasRunning]);
        }

        $pid = $this->resolveWorkerPid();

        if ($pid === null) {
            return array_merge($this->getStatus(), ['stopped' => false]);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            shell_exec('taskkill /PID ' . $pid . ' /F 2>NUL');
        } elseif (function_exists('posix_kill')) {
            @posix_kill($pid, SIGTERM);
            usleep(300000);

            if ($this->isProcessRunning($pid)) {
                @posix_kill($pid, SIGKILL);
            }
        } else {
            shell_exec('kill ' . escapeshellarg((string) $pid) . ' 2>/dev/null');
        }

        $this->clearPidFile();

        return array_merge($this->getStatus(), ['stopped' => true]);
    }

    /**
     * @return array{started: bool, pending_jobs: int}
     */
    public function runPendingJobs(): array
    {
        if ($this->usesSupervisor() && $this->getStatus()['running']) {
            if (!function_exists('shell_exec')) {
                abort(503, 'تعذر تشغيل قائمة الانتظار: shell_exec غير متاح على الخادم');
            }

            $php = PHP_BINARY;
            $artisan = base_path('artisan');
            $log = storage_path('logs/queue-run-once.log');
            File::ensureDirectoryExists(dirname($log));

            $command = sprintf(
                'cd %s && nohup %s %s queue:work database --stop-when-empty --tries=3 --timeout=120 >> %s 2>&1 &',
                escapeshellarg(base_path()),
                escapeshellarg($php),
                escapeshellarg($artisan),
                escapeshellarg($log)
            );
            shell_exec($command);

            return [
                'started' => true,
                'pending_jobs' => (int) DB::table('jobs')->count(),
            ];
        }

        if ($this->usesSupervisor()) {
            $output = $this->supervisorCtl('start');
            Log::info('Supervisor queue start from runPendingJobs', ['output' => $output]);

            return [
                'started' => true,
                'pending_jobs' => (int) DB::table('jobs')->count(),
            ];
        }

        if (!function_exists('shell_exec')) {
            abort(503, 'تعذر تشغيل قائمة الانتظار: shell_exec غير متاح على الخادم');
        }

        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $log = storage_path('logs/queue-run-once.log');

        File::ensureDirectoryExists(dirname($log));

        $command = sprintf(
            'cd %s && nohup %s %s queue:work --stop-when-empty --tries=3 --timeout=120 >> %s 2>&1 &',
            escapeshellarg(base_path()),
            escapeshellarg($php),
            escapeshellarg($artisan),
            escapeshellarg($log)
        );

        shell_exec($command);

        return [
            'started' => true,
            'pending_jobs' => (int) DB::table('jobs')->count(),
        ];
    }

    /**
     * @return array{cleared: list<string>}
     */
    public function clearCache(): array
    {
        $cleared = [];

        foreach (['cache:clear', 'config:clear', 'route:clear', 'view:clear'] as $command) {
            Artisan::call($command);
            $cleared[] = str_replace(':clear', '', $command);
        }

        return ['cleared' => $cleared];
    }
}
