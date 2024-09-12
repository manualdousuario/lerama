<?php

namespace Src;

use PDO;
use Exception;
use DateTime;

class FeedFetcher
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function fetchFeeds()
    {
        $stmt = $this->db->prepare("SELECT * FROM sites WHERE status = 'active'");
        $stmt->execute();
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sites as $site) {
            try {
                echo '- ' . $site['feed_url'].PHP_EOL;
                $feedContent = @file_get_contents($site['feed_url']);
                if ($feedContent === false) {
                    $this->handleFeedError($site);
                    continue;
                }

                $feed = @simplexml_load_string($feedContent);
                if ($feed === false) {
                    $this->handleFeedError($site);
                    continue;
                }

                if (isset($feed->channel)) {
                    $this->processRssFeed($feed, $site);
                } elseif (isset($feed->entry)) {
                    $this->processAtomFeed($feed, $site);
                } else {
                    $this->handleFeedError($site);
                    continue;
                }

                $this->resetFeedError($site);
            } catch (Exception $e) {
                $this->handleFeedError($site);
            }
        }
    }

    private function processRssFeed($feed, $site)
    {
        foreach ($feed->channel->item as $item) {
            $uniqueId = md5((string)$item->guid);

            $existsStmt = $this->db->prepare("SELECT id FROM articles WHERE unique_identifier = :unique_id");
            $existsStmt->execute(['unique_id' => $uniqueId]);
            if ($existsStmt->fetch()) {
                continue;
            }

            $publicationDate = new DateTime($item->pubDate);
            $link = (string)$item->link;

            $insertStmt = $this->db->prepare("INSERT INTO articles (site_id, title, author, publication_date, link, unique_identifier) VALUES (:site_id, :title, :author, :publication_date, :link, :unique_identifier)");
            $insertStmt->execute([
                'site_id' => $site['id'],
                'title' => (string)$item->title,
                'author' => (string)$item->{'dc:creator'} ?? null,
                'publication_date' => $publicationDate->format('Y-m-d H:i:s'),
                'link' => $link,
                'unique_identifier' => $uniqueId
            ]);
        }
    }

    private function processAtomFeed($feed, $site)
    {
        foreach ($feed->entry as $entry) {
            $uniqueId = md5($entry->id . $entry->updated);

            $existsStmt = $this->db->prepare("SELECT id FROM articles WHERE unique_identifier = :unique_id");
            $existsStmt->execute(['unique_id' => $uniqueId]);
            if ($existsStmt->fetch()) {
                continue;
            }

            $publicationDate = new DateTime($entry->published);
            $link = (string)$entry->link['href'];

            $insertStmt = $this->db->prepare("INSERT INTO articles (site_id, title, author, publication_date, link, unique_identifier) VALUES (:site_id, :title, :author, :publication_date, :link, :unique_identifier)");
            $insertStmt->execute([
                'site_id' => $site['id'],
                'title' => (string)$entry->title,
                'author' => (string)$entry->author->name ?? null,
                'publication_date' => $publicationDate->format('Y-m-d H:i:s'),
                'link' => $link,
                'unique_identifier' => $uniqueId
            ]);
        }
    }

    private function handleFeedError($site)
    {
        $errorCount = $site['error_count'] + 1;
        $updateStmt = $this->db->prepare("UPDATE sites SET error_count = :error_count, last_error_check = NOW() WHERE id = :id");
        $updateStmt->execute([
            'error_count' => $errorCount,
            'id' => $site['id']
        ]);

        if ($errorCount >= 12) {
            $deactivateStmt = $this->db->prepare("UPDATE sites SET status = 'inactive' WHERE id = :id");
            $deactivateStmt->execute(['id' => $site['id']]);
        }
    }

    private function resetFeedError($site)
    {
        if ($site['error_count'] > 0) {
            $updateStmt = $this->db->prepare("UPDATE sites SET error_count = 0, last_error_check = NULL WHERE id = :id");
            $updateStmt->execute(['id' => $site['id']]);
        }
    }
}
