<?php
class DirectoryScanner
{
    private $directory;
    private $fileList = [];
    private $excluded = [];

    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    public function scan(array $ext = []): array
    {
        if (! is_dir($this->directory)) {
            throw new InvalidArgumentException("Il percorso '$this->directory' non è una directory valida.");
        }

        $this->scanDirectory($this->directory, $ext);
        return $this->fileList;
    }

    public function exclude(array $arr): DirectoryScanner
    {
        $this->excluded = array_map(fn($f) => strtoupper($f), $arr);
        $this->fileList = array_filter($this->fileList, fn($f) => ! in_array(strtoupper(pathinfo($f)['filename']), $this->excluded));
        return $this;
    }

    public function search(string $pattern): array
    {
        $pattern = preg_quote($pattern, '/'); // Escape special characters
        return array_filter($this->fileList, fn($f) => preg_match('/' . $pattern . '/', pathinfo($f)['filename']));
    }

    private function scanDirectory(string $directory, array $ext = []): void
    {
        $isDisk    = preg_match('/([A-Z]):\\$/', $directory);
        $directory = $isDisk ? $directory : rtrim($directory, '\/\\');
        $ext       = array_map(fn($e) => strtoupper($e), $ext);
        $files     = scandir($directory);

        foreach ($files as $file) {

            $path  = $directory . ($isDisk ? '' : DIRECTORY_SEPARATOR) . $file;
            $isDir = is_dir($path);

            if ($file === '.' || $file === '..' || (! $isDir && in_array(strtoupper(pathinfo($file)['filename']), $this->excluded))
                || (! $isDir && $ext && ! in_array(strtoupper(pathinfo($file)['extension']), $ext))) {
                continue; // Salta i riferimenti alla directory corrente e alla parent
            }

            if (is_dir($path)) {
                $this->scanDirectory($path, $ext); // Ricorsione per le sottocartelle
            } else {
                $this->fileList[] = $path; // Aggiungi il file alla lista
            }
        }
    }

}
