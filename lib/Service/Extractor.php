<?php

declare(strict_types=1);

namespace OCA\DocSort\Service;

use OCP\Files\File;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/** The text of a document, through src/docsort.py (pdftotext, RapidOCR, office XML). */
final class Extractor
{
    public const KINDS = ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'webp', 'gif', 'heic', 'heif', 'docx', 'odt', 'pptx', 'odp', 'xlsx', 'ods', 'txt', 'md', 'rtf', 'eml', 'html', 'htm'];

    public function __construct(private Settings $settings, private ITempManager $temp, private LoggerInterface $logger) {}

    public static function readable(string $name): bool
    {
        return \in_array(strtolower((string) pathinfo($name, PATHINFO_EXTENSION)), self::KINDS, true);
    }

    /**
     * @return array{text:string, engine:string, chars:int}
     *
     * @throws \RuntimeException when the file cannot be read
     */
    public function text(File $file): array
    {
        $ext = strtolower((string) pathinfo($file->getName(), PATHINFO_EXTENSION));
        $tmp = $this->temp->getTemporaryFile('.'.$ext);
        if (false === $tmp) {
            throw new \RuntimeException('no temporary file');
        }

        try {
            $in = $file->fopen('r');
            $out = fopen($tmp, 'w');
            if (false === $in || false === $out) {
                throw new \RuntimeException('cannot read '.$file->getPath());
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);

            return $this->textOfPath($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /** @return array{text:string, engine:string, chars:int} */
    public function textOfPath(string $path): array
    {
        $process = new Process([$this->settings->pythonBinary(), \dirname(__DIR__, 2).'/src/docsort.py', $path, '--pages', '3'], \dirname(__DIR__, 2), ['TMPDIR' => (string) $this->temp->getTempBaseDir(), 'OMP_NUM_THREADS' => '2']);
        $process->setTimeout(300);
        $process->run();
        if (!$process->isSuccessful()) {
            $lines = array_slice(array_filter(explode("\n", trim($process->getErrorOutput()))), -2);
            $this->logger->warning('docsort: reading failed: '.implode(' | ', $lines));

            throw new \RuntimeException(implode(' | ', $lines) ?: 'the reader failed');
        }
        $out = json_decode(trim($process->getOutput()), true);
        if (!\is_array($out)) {
            throw new \RuntimeException('the reader gave no answer');
        }

        return ['text' => (string) ($out['text'] ?? ''), 'engine' => (string) ($out['engine'] ?? ''), 'chars' => (int) ($out['chars'] ?? 0)];
    }
}
