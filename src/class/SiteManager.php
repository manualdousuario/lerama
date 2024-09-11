<?php
namespace Src;

use PDO;

class SiteManager
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
}
