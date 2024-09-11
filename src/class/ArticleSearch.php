<?php
namespace Src;

use PDO;

class ArticleSearch
{
    private $db;
    private $perPage = 10;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function search($query, $orderBy = 'date', $page = 1)
    {
        $offset = ($page - 1) * $this->perPage;
        $params = ['query' => '%' . $query . '%', 'limit' => $this->perPage, 'offset' => $offset];

        if ($orderBy === 'relevance' && !empty($query)) {
            $sql = "
                SELECT 
                    a.*, 
                    (
                        (CASE 
                            WHEN title = :exact_query THEN 10  -- Exact match
                            WHEN title LIKE :query THEN 5 + (LENGTH(:query_term) / LENGTH(title)) * 5  -- Partial match scaled by match length
                            ELSE (LENGTH(title) - LENGTH(REPLACE(title, :query_term, ''))) / LENGTH(:query_term) * 2  -- Word match and frequency
                        END) +
                        (CASE WHEN DATEDIFF(NOW(), publication_date) <= 30 THEN 1 ELSE 0 END)  -- Recency boost for articles within the last 30 days
                    ) AS relevance_score
                FROM articles a
                WHERE title LIKE :query
                ORDER BY relevance_score DESC
                LIMIT :limit OFFSET :offset
            ";
            $params['exact_query'] = $query;
            $params['query_term'] = str_replace('%', '', $params['query']);
        } else {
            $sql = "
                SELECT * FROM articles
                WHERE title LIKE :query
                ORDER BY publication_date DESC
                LIMIT :limit OFFSET :offset
            ";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':query', $params['query'], PDO::PARAM_STR);
        if (isset($params['exact_query'])) {
            $stmt->bindValue(':exact_query', $params['exact_query'], PDO::PARAM_STR);
        }
        if (isset($params['query_term'])) {
            $stmt->bindValue(':query_term', $params['query_term'], PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$params['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$params['offset'], PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalResults($query)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM articles WHERE title LIKE :query");
        $stmt->execute(['query' => '%' . $query . '%']);
        return $stmt->fetchColumn();
    }

    public function scaleRelevance($score)
    {
        $scaledScore = max(0, min(10, round($score)));
        return $scaledScore;
    }

    public function getRelevanceColor($scaledScore)
    {
        if ($scaledScore <= 5) {
            $green = 255 * ($scaledScore / 5);
            return sprintf("#%02x%02x00", 255, $green);
        } else {
            $red = 255 * ((10 - $scaledScore) / 5);
            return sprintf("#%02xFF00", $red);
        }
    }
}
