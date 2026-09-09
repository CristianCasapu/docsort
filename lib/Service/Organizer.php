<?php

declare(strict_types=1);

namespace OCA\DocSort\Service;

use OCA\DocSort\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Goes through a person's inbox folders, reads every document that was not looked at yet,
 * decides what it is, tags it and files it under Documents/<category>/<kind>/. What was
 * decided is remembered per file (never the text), so a file is read once.
 */
final class Organizer
{
    public const UNKNOWN_FOLDER = ['en' => 'Unsorted', 'ro' => 'Nesortate'];
    public const TAG_PREFIX = ['en' => 'Document: ', 'ro' => 'Document: '];

    public function __construct(
        private IRootFolder $root,
        private Settings $settings,
        private Extractor $extractor,
        private ISystemTagManager $tags,
        private ISystemTagObjectMapper $tagMapper,
        private IDBConnection $db,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param callable(array{name:string, kind:?string, category:?string, score:int, movedTo:?string, error:?string}):void|null $report
     *
     * @return array{files:int, sorted:int, unknown:int, errors:int}
     */
    public function scanUser(string $uid, bool $dryRun = false, int $limit = 0, ?callable $report = null): array
    {
        $conf = $this->settings->forUser($uid);
        $home = $this->root->getUserFolder($uid);
        $rules = $this->settings->rules();
        $known = $this->known($uid);
        $stats = ['files' => 0, 'sorted' => 0, 'unknown' => 0, 'errors' => 0];
        $destination = trim((string) $conf['destination'], '/');

        foreach (explode("\n", (string) $conf['inbox']) as $inbox) {
            $inbox = trim($inbox, '/');
            if ('' === $inbox) {
                continue;
            }

            try {
                $folder = $home->get($inbox);
            } catch (NotFoundException) {
                continue;
            }
            if (!$folder instanceof Folder) {
                continue;
            }
            foreach ($this->files($folder, $destination) as $file) {
                if ($limit > 0 && $stats['files'] >= $limit) {
                    return $stats;
                }
                $id = $file->getId();
                if (isset($known[$id]) && $known[$id] >= $file->getMTime()) {
                    continue; // read already, unchanged since
                }
                ++$stats['files'];
                $result = $this->handle($uid, $home, $file, $rules, $conf, $dryRun);
                if (null !== $result['error']) {
                    ++$stats['errors'];
                } elseif (null === $result['kind']) {
                    ++$stats['unknown'];
                } else {
                    ++$stats['sorted'];
                }
                if (null !== $report) {
                    $report($result);
                }
            }
        }

        return $stats;
    }

    /**
     * One document: text → kind → tag → folder.
     *
     * @param list<array{id:string, category:string, en:string, ro:string, min:int, keywords:array<string,int>}> $rules
     * @param array<string, mixed>                                                                               $conf
     *
     * @return array{name:string, kind:?string, category:?string, score:int, movedTo:?string, error:?string, engine:string}
     */
    public function handle(string $uid, Folder $home, File $file, array $rules, array $conf, bool $dryRun): array
    {
        $name = $file->getName();
        $lang = 'en' === $conf['language'] ? 'en' : 'ro';
        $out = ['name' => $name, 'kind' => null, 'category' => null, 'score' => 0, 'movedTo' => null, 'error' => null, 'engine' => ''];

        try {
            $read = $this->extractor->text($file);
            $out['engine'] = $read['engine'];
            $decision = Classifier::classify($read['text'], $rules);
            unset($read); // the text goes no further
        } catch (ReaderMissingException $e) {
            // nothing is remembered: the file is read once the reader is installed
            $out['error'] = $e->getMessage();

            return $out;
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
            if (!$dryRun) {
                $this->remember($uid, $file, null, null, 0, 'error', null);
            }

            return $out;
        }
        $out['kind'] = $decision['kind'];
        $out['category'] = $decision['category'];
        $out['score'] = $decision['score'];
        $rule = null;
        foreach ($rules as $r) {
            if ($r['id'] === $decision['kind']) {
                $rule = $r;
                break;
            }
        }

        if ($dryRun) {
            $out['movedTo'] = $this->targetPath($conf, $rule, $lang);

            return $out;
        }

        try {
            // the tag: "Document: Carte de identitate" (and for the unknown: "Document: ?")
            $tagName = self::TAG_PREFIX[$lang].(null !== $rule ? $rule[$lang] : '?');
            $this->tag($file, $tagName);

            // the folder
            $target = $this->targetPath($conf, $rule, $lang);
            if (null !== $target && 'tag' !== $conf['mode']) {
                $folder = $this->folder($home, $target);
                $newName = $folder->getNonExistingName($name);
                $path = $folder->getPath().'/'.$newName;
                if ('copy' === $conf['mode']) {
                    $file->copy($path);
                } else {
                    $file->move($path);
                }
                $out['movedTo'] = $target.'/'.$newName;
            }
            $this->remember($uid, $file, $decision['kind'], $decision['category'], $decision['score'], $out['engine'], $out['movedTo']);
        } catch (\Throwable $e) {
            $this->logger->warning('docsort: could not file '.$name.': '.$e->getMessage());
            $out['error'] = $e->getMessage();
        }

        return $out;
    }

    /**
     * Where a document of this kind goes, relative to the person's files (null = stays).
     *
     * @param array<string, mixed> $conf
     */
    private function targetPath(array $conf, ?array $rule, string $lang): ?string
    {
        $destination = trim((string) $conf['destination'], '/');
        if (null === $rule) {
            return 'unknown' === $conf['unknown'] ? $destination.'/'.self::UNKNOWN_FOLDER[$lang] : null;
        }
        $parts = [$destination];
        if ($conf['byCategory']) {
            $parts[] = DefaultRules::CATEGORIES[$rule['category']][$lang] ?? $rule['category'];
        }
        $parts[] = $rule[$lang];

        return implode('/', array_map(static fn ($p) => str_replace(['/', '\\'], '-', (string) $p), array_filter($parts, static fn ($p) => '' !== $p)));
    }

    private function folder(Folder $home, string $path): Folder
    {
        try {
            $node = $home->get($path);
            if ($node instanceof Folder) {
                return $node;
            }
        } catch (NotFoundException) {
        }
        $current = $home;
        foreach (explode('/', $path) as $part) {
            try {
                $next = $current->get($part);
                $current = $next instanceof Folder ? $next : $current->newFolder($part);
            } catch (NotFoundException) {
                $current = $current->newFolder($part);
            }
        }

        return $current;
    }

    private function tag(File $file, string $tagName): void
    {
        try {
            $tag = $this->tags->getTag($tagName, true, true);
        } catch (TagNotFoundException) {
            $tag = $this->tags->createTag($tagName, true, true);
        }
        $this->tagMapper->assignTags((string) $file->getId(), 'files', [$tag->getId()]);
    }

    /**
     * The documents of a folder (and its sub-folders, except the destination itself).
     *
     * @return iterable<File>
     */
    private function files(Folder $folder, string $destination, int $depth = 0): iterable
    {
        if ($depth > 8) {
            return;
        }
        foreach ($folder->getDirectoryListing() as $node) {
            if ($node instanceof Folder) {
                $relative = ltrim(substr($node->getPath(), \strlen($this->root->getUserFolder($this->uidOf($node))->getPath())), '/');
                if ('' !== $destination && (0 === strpos($relative, $destination.'/') || $relative === $destination)) {
                    continue;
                }
                yield from $this->files($node, $destination, $depth + 1);
            } elseif ($node instanceof File && Extractor::readable($node->getName()) && $node->getSize() > 0 && $node->getSize() < 100 * 1024 * 1024) {
                yield $node;
            }
        }
    }

    private function uidOf(\OCP\Files\Node $node): string
    {
        return explode('/', ltrim($node->getPath(), '/'))[0];
    }

    /** @return array<int, int> fileid => mtime when it was read */
    private function known(string $uid): array
    {
        $q = $this->db->getQueryBuilder();
        $q->select('fileid', 'mtime')->from('docsort_files')->where($q->expr()->eq('uid', $q->createNamedParameter($uid)));
        $out = [];
        foreach ($q->executeQuery()->fetchAll() as $row) {
            $out[(int) $row['fileid']] = (int) $row['mtime'];
        }

        return $out;
    }

    private function remember(string $uid, File $file, ?string $kind, ?string $category, int $score, string $engine, ?string $movedTo): void
    {
        $del = $this->db->getQueryBuilder();
        $del->delete('docsort_files')
            ->where($del->expr()->eq('uid', $del->createNamedParameter($uid)))
            ->andWhere($del->expr()->eq('fileid', $del->createNamedParameter($file->getId(), IQueryBuilder::PARAM_INT)));
        $del->executeStatement();
        $ins = $this->db->getQueryBuilder();
        $ins->insert('docsort_files')->values([
            'uid' => $ins->createNamedParameter($uid),
            'fileid' => $ins->createNamedParameter($file->getId(), IQueryBuilder::PARAM_INT),
            'name' => $ins->createNamedParameter(mb_substr($file->getName(), 0, 255)),
            'kind' => $ins->createNamedParameter($kind),
            'category' => $ins->createNamedParameter($category),
            'score' => $ins->createNamedParameter($score, IQueryBuilder::PARAM_INT),
            'engine' => $ins->createNamedParameter(mb_substr($engine, 0, 32)),
            'moved_to' => $ins->createNamedParameter(null === $movedTo ? null : mb_substr($movedTo, 0, 1024)),
            'mtime' => $ins->createNamedParameter($file->getMTime(), IQueryBuilder::PARAM_INT),
            'processed' => $ins->createNamedParameter(time(), IQueryBuilder::PARAM_INT),
        ]);
        $ins->executeStatement();
    }

    /** The last things sorted for a person. @return list<array<string, mixed>> */
    public function history(string $uid, int $limit = 40): array
    {
        $q = $this->db->getQueryBuilder();
        $q->select('name', 'kind', 'category', 'score', 'engine', 'moved_to', 'processed')->from('docsort_files')
            ->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))
            ->orderBy('processed', 'DESC')->setMaxResults($limit);
        $out = [];
        foreach ($q->executeQuery()->fetchAll() as $row) {
            $out[] = ['name' => $row['name'], 'kind' => $row['kind'], 'category' => $row['category'], 'score' => (int) $row['score'], 'engine' => $row['engine'], 'movedTo' => $row['moved_to'], 'processed' => (int) $row['processed']];
        }

        return $out;
    }

    /** Forget what was decided, so everything is read again. */
    public function forget(string $uid): int
    {
        $q = $this->db->getQueryBuilder();
        $q->delete('docsort_files')->where($q->expr()->eq('uid', $q->createNamedParameter($uid)));

        return $q->executeStatement();
    }
}
