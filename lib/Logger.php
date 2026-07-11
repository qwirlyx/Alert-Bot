<?php
class Logger
{
    private string $file;
    private int $maxSize;

    public function __construct(string $file, int $maxSize = 2097152)
    {
        $this->file = $file;
        $this->maxSize = $maxSize;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context = []): void
    {
        if (is_file($this->file) && filesize($this->file) > $this->maxSize) {
            @rename($this->file, $this->file . '.bak');
        }

        $line = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $message;
        if ($context) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $line .= PHP_EOL;

        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }
}
