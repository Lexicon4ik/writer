<?php declare(strict_types=1);

namespace NewsBot\Core;

/**
 * File-based locking using flock() for atomic concurrent process prevention.
 *
 * Uses flock() instead of PID-based locking to eliminate race conditions
 * between file_exists() checks and file creation.
 */
class Lock
{
    private string $lockFile;
    private bool $acquired = false;
    /** @var resource|null */
    private $handle = null;
    private const STALE_TIMEOUT = 1800; // 30 minutes

    /**
     * Create a lock.
     *
     * @param string $name Lock name (e.g., 'master', 'fetch')
     * @param int[] $channelIds Optional channel IDs for channel-specific locks
     */
    public function __construct(string $name, array $channelIds = [])
    {
        $lockDir = ROOT_DIR . '/locks';

        // Ensure lock directory exists
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }

        if (!empty($channelIds)) {
            sort($channelIds);
            $suffix = '_ch' . implode('_', $channelIds);
            $this->lockFile = "{$lockDir}/{$name}{$suffix}.lock";
        } else {
            $this->lockFile = "{$lockDir}/{$name}.lock";
        }
    }

    /**
     * Try to acquire the lock (non-blocking).
     *
     * Uses flock() with LOCK_NB for atomic, non-blocking acquisition.
     * This eliminates race conditions between checking and creating locks.
     *
     * @return bool True if lock acquired, false if already locked by another process
     */
    public function acquire(): bool
    {
        if ($this->acquired) {
            return true; // Already acquired by this instance
        }

        // Open or create lock file
        $this->handle = @fopen($this->lockFile, 'c+');
        if ($this->handle === false) {
            Logger::error('Failed to open lock file', ['lock_file' => $this->lockFile]);
            return false;
        }

        // Try non-blocking exclusive lock
        if (!flock($this->handle, LOCK_EX | LOCK_NB)) {
            // Another process holds the lock
            fclose($this->handle);
            $this->handle = null;
            return false;
        }

        // Write PID for debugging/monitoring
        ftruncate($this->handle, 0);
        fwrite($this->handle, (string)getmypid());
        fflush($this->handle);

        // Update mtime for stale detection
        touch($this->lockFile);

        $this->acquired = true;
        return true;
    }

    /**
     * Release the lock.
     */
    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }

        if ($this->acquired && file_exists($this->lockFile)) {
            @unlink($this->lockFile);
        }

        $this->acquired = false;
    }

    /**
     * Check if the lock is stale (file older than STALE_TIMEOUT).
     * Note: With flock(), stale locks are rare since the OS releases them
     * when the process terminates. This is mainly for orphaned files.
     */
    public function isStale(): bool
    {
        if (!file_exists($this->lockFile)) {
            return false;
        }

        $mtime = @filemtime($this->lockFile);
        if ($mtime === false) {
            return true;
        }

        return (time() - $mtime) > self::STALE_TIMEOUT;
    }

    /**
     * Check if lock is currently held (by trying to acquire it).
     */
    public function isLocked(): bool
    {
        if ($this->acquired) {
            return true; // We hold it
        }

        if (!file_exists($this->lockFile)) {
            return false;
        }

        // Try to acquire - if we can, release immediately
        $handle = @fopen($this->lockFile, 'c+');
        if ($handle === false) {
            return true; // Can't open, assume locked
        }

        $canLock = flock($handle, LOCK_EX | LOCK_NB);
        if ($canLock) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        return !$canLock;
    }

    /**
     * Get PID of process holding the lock (for debugging).
     */
    public function getHolderPid(): ?int
    {
        if (!file_exists($this->lockFile)) {
            return null;
        }

        $contents = @file_get_contents($this->lockFile);
        if ($contents === false) {
            return null;
        }

        $pid = (int)trim($contents);
        return $pid > 0 ? $pid : null;
    }

    /**
     * Auto-release on destruction.
     */
    public function __destruct()
    {
        $this->release();
    }

    /**
     * Get lock file path (for debugging).
     */
    public function getLockFile(): string
    {
        return $this->lockFile;
    }
}
