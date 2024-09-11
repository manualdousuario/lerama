<?php

namespace Src;

use PDO;
use Exception;
use DateTime;

class FeedFetcher
{
    private $db;
    private $feeds;

    public function __construct(PDO $db, array $feeds)
    {
        $this->db = $db;
        $this->feeds = $feeds;
    }

    public function syncSites()
    {
        $stmt = $this->db->prepare("SELECT * FROM sites");
        $stmt->execute();
        $existingSites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $existingUrls = array_column($existingSites, 'feed_url');
        $configUrls = array_column($this->feeds, 'feed_url');

        foreach ($this->feeds as $feed) {
            if (!in_array($feed['feed_url'], $existingUrls)) {
                $insertStmt = $this->db->prepare("INSERT INTO sites (name, url, feed_url) VALUES (:name, :url, :feed_url)");
                $insertStmt->execute([
                    'name' => $feed['name'],
                    'url' => $feed['url'],
                    'feed_url' => $feed['feed_url']
                ]);
            } else {
                $updateStmt = $this->db->prepare("UPDATE sites SET name = :name WHERE feed_url = :feed_url");
                $updateStmt->execute([
                    'name' => $feed['name'],
                    'feed_url' => $feed['feed_url']
                ]);
            }
        }

        foreach ($existingSites as $site) {
            if (!in_array($site['feed_url'], $configUrls)) {
                $deactivateStmt = $this->db->prepare("UPDATE sites SET status = 'inactive' WHERE id = :id");
                $deactivateStmt->execute(['id' => $site['id']]);
            } else {
                $activateStmt = $this->db->prepare("UPDATE sites SET status = 'active' WHERE id = :id");
                $activateStmt->execute(['id' => $site['id']]);
            }
        }
    }

    public function fetchFeeds()
    {
        $this->syncSites();

        $stmt = $this->db->prepare("SELECT * FROM sites WHERE status = 'active'");
        $stmt->execute();
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sites as $site) {
            try {
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
