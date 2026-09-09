<?php

declare(strict_types=1);

namespace OCA\DocSort\Service;

use OCP\Files\File;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * The text of a document. Office files and plain text are read here, in PHP, so they sort
 * even without the reader; pictures and PDFs go through src/docsort.py (RapidOCR, pypdfium2).
 */
final class Extractor
{
    public const KINDS = ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'webp', 'gif', 'heic', 'heif', 'docx', 'odt', 'pptx', 'odp', 'xlsx', 'ods', 'txt', 'md', 'rtf', 'eml', 'html', 'htm'];

    /** What PHP reads by itself */
    private const PHP_KINDS = ['docx', 'odt', 'pptx', 'odp', 'xlsx', 'ods', 'txt', 'md', 'rtf', 'eml', 'html', 'htm'];

    /** docsort.py ends with this code when the OCR engine cannot be imported */
    public const EXIT_NO_READER = 3;

    private const MAX_CHARS = 20000;

    public function __construct(private Settings $settings, private ITempManager $temp, private LoggerInterface $logger) {}

    public static function readable(string $name): bool
    {
        return \in_array(strtolower((string) pathinfo($name, PATHINFO_EXTENSION)), self::KINDS, true);
    }

    /**
     * @return array{text:string, engine:string, chars:int}
     *
     * @throws ReaderMissingException when the file needs the reader and it is not installed
     * @throws \RuntimeException      when the file cannot be read
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
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (\in_array($ext, self::PHP_KINDS, true)) {
            $text = $this->phpText($path, $ext);

            return ['text' => $text, 'engine' => 'php', 'chars' => mb_strlen($text)];
        }
        if (!$this->settings->readerReady()) {
            throw new ReaderMissingException('the document reader is not installed (Administration settings › Document sorter)');
        }
        $process = new Process([$this->settings->pythonBinary(), \dirname(__DIR__, 2).'/src/docsort.py', $path, '--pages', '3'], \dirname(__DIR__, 2), ['TMPDIR' => (string) $this->temp->getTempBaseDir(), 'OMP_NUM_THREADS' => '2', 'HOME' => $this->settings->readerHome()]);
        $process->setTimeout(300);
        $process->run();
        if (!$process->isSuccessful()) {
            if (self::EXIT_NO_READER === $process->getExitCode()) {
                throw new ReaderMissingException('the document reader is not installed (Administration settings › Document sorter)');
            }
            $lines = \array_slice(array_filter(explode("\n", trim($process->getErrorOutput()))), -2);
            $this->logger->warning('docsort: reading failed: '.implode(' | ', $lines));

            throw new \RuntimeException(implode(' | ', $lines) ?: 'the reader failed');
        }
        $out = json_decode(trim($process->getOutput()), true);
        if (!\is_array($out)) {
            throw new \RuntimeException('the reader gave no answer');
        }

        return ['text' => (string) ($out['text'] ?? ''), 'engine' => (string) ($out['engine'] ?? ''), 'chars' => (int) ($out['chars'] ?? 0)];
    }

    /** Office documents (the XML inside the zip) and plain text, without any outside program. */
    private function phpText(string $path, string $ext): string
    {
        $text = '';
        if (\in_array($ext, ['docx', 'odt', 'pptx', 'odp', 'xlsx', 'ods'], true)) {
            $zip = new \ZipArchive();
            if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
                throw new \RuntimeException('not a readable office file');
            }
            $parts = [];
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = (string) $zip->getNameIndex($i);
                if (\in_array($name, ['word/document.xml', 'content.xml', 'xl/sharedStrings.xml'], true) || str_starts_with($name, 'ppt/slides/slide') || str_starts_with($name, 'xl/worksheets/')) {
                    $parts[] = (string) $zip->getFromIndex($i, 400000);
                }
            }
            $zip->close();
            // a tag boundary is a word boundary in these formats
            $text = html_entity_decode(strip_tags(preg_replace('/<[^>]+>/', ' ', implode(' ', $parts)) ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
        } else {
            $raw = (string) file_get_contents($path, false, null, 0, self::MAX_CHARS * 2);
            if (!mb_check_encoding($raw, 'UTF-8')) {
                $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-2');
            }
            $text = match ($ext) {
                'html', 'htm', 'eml' => html_entity_decode(strip_tags(preg_replace('/<(script|style)\b.*?<\/\1>/is', ' ', $raw) ?? ''), ENT_QUOTES, 'UTF-8'),
                'rtf' => preg_replace(['/\\\\[a-z]+-?\d* ?/i', "/\\\\'[0-9a-f]{2}/i", '/[{}]/'], [' ', ' ', ' '], $raw) ?? '',
                default => $raw,
            };
        }

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $text)), 0, self::MAX_CHARS);
    }
}
