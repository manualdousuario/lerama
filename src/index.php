<?php
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/ArticleSearch.php';

use Src\Database;
use Src\ArticleSearch;

$appConfig = require __DIR__ . '/config.php';

$db = Database::getInstance($appConfig['database']);
$search = new ArticleSearch($db);

$query = $_GET['q'] ?? '';
$orderBy = $_GET['order'] ?? 'date';
$page = $_GET['page'] ?? 1;

if ($query) {
    $results = $search->search($query, $orderBy, $page);
    $totalResults = $search->getTotalResults($query);
} else {
    $results = $search->search('', 'date', $page);
    $totalResults = $search->getTotalResults('');
}

include __DIR__ . '/header.php';
?>

<div class="container mt-3 mt-md-5">
    <form method="GET" action="/">
        <div class="row g-3 mb-3">
            <div class="col col-12 col-md-8">
                <input type="text" name="q" class="form-control form-control-md" placeholder="Por..." value="<?php echo htmlspecialchars($query) ?>">
            </div>
            <div class="col col-7 col-md-3 d-flex align-items-center">
            <label class="form-label pe-3 text-nowrap fw-bold m-0 d-block d-md-none">Ordenar:</label>
                <label class="form-label pe-3 text-nowrap fw-bold m-0 d-none d-md-block">Ordenar por:</label>
                <select name="order" class="form-control form-control-md flex-grow-1">
                    <option value="date" <?php echo $orderBy === 'date' ? 'selected' : '' ?>>Mais novo</option>
                    <option value="relevance" <?php echo $orderBy === 'relevance' ? 'selected' : '' ?>>Relevancia</option>
                </select>
            </div>
            <div class="col col-5 col-md-1">
                <button class="btn btn-primary w-100 btn-md" type="submit">Buscar</button>
            </div>
        </div>
    </form>

    <?php if ($query): ?>
        <h5><?php echo $totalResults ?> resultados para "<?php echo htmlspecialchars($query) ?>"</h5>
    <?php else: ?>
        <h5>Últimos artigos</h5>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($results as $article): ?>
            <?php
                $relevanceScore = $article['relevance_score'] ?? null;
                $scaledRelevance = $relevanceScore ? $search->scaleRelevance($relevanceScore) : null;
                $color = $scaledRelevance ? $search->getRelevanceColor($scaledRelevance) : '#808080';

                $siteStmt = $db->prepare("SELECT name, url FROM sites WHERE id = :site_id");
                $siteStmt->execute(['site_id' => $article['site_id']]);
                $site = $siteStmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <div class="col-12 col-md-6">
                <div class="card mb-3" style="border-color: <?php echo $color ?>;">
                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="<?php echo htmlspecialchars($article['link']) ?>" target="_blank" class="text-decoration-none link-primary ">
                                <?php echo htmlspecialchars($article['title']) ?>
                            </a>
                        </h5>
                        <p class="card-text">
                            <small class="text-muted">
                                <?php if(empty($article['author'])) { ?>
                                    Em <?php echo date('d/m/Y', strtotime($article['publication_date'])) ?> | <a href="<?php echo htmlspecialchars($site['url']) ?>"  target="_blank" class="link-secondary"><?php echo htmlspecialchars($site['name']) ?></a>
                                <?php } else { ?>
                                    Por <?php echo htmlspecialchars($article['author'] ?? 'Unknown') ?> em <?php echo date('d/m/Y', strtotime($article['publication_date'])) ?> | <a href="<?php echo htmlspecialchars($site['url']) ?>" target="_blank" class="link-secondary"><?php echo htmlspecialchars($site['name']) ?></a>
                                <?php } ?>
                            </small>
                        </p>
                        <?php if ($scaledRelevance): ?>
                            <span class="badge" style="background-color: <?php echo $color ?>;">Relevancia: <?php echo $scaledRelevance ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    $totalPages = ceil($totalResults / 10);
    $maxDisplayedPages = 3;

    if ($totalPages > 1):
    ?>
        <nav class="d-flex justify-content-center">
            <ul class="pagination pagination-dark">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?q=<?php echo urlencode($query) ?>&order=<?php echo $orderBy ?>&page=<?php echo $page - 1 ?>">«</a>
                    </li>
                <?php endif; ?>

                <?php
                if ($totalPages <= $maxDisplayedPages) {
                    for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?q=<?php echo urlencode($query) ?>&order=<?php echo $orderBy ?>&page=<?php echo $i ?>"><?php echo $i ?></a>
                        </li>
                    <?php endfor;
                } else {
                    $startPage = max(1, $page - 1);
                    $endPage = min($totalPages, $startPage + $maxDisplayedPages - 1);

                    if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?q=<?php echo urlencode($query) ?>&order=<?php echo $orderBy ?>&page=1">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled"><span class="page-link">..</span></li>
                        <?php endif;
                    endif;

                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?q=<?php echo urlencode($query) ?>&order=<?php echo $orderBy ?>&page=<?php echo $i ?>"><?php echo $i ?></a>
                        </li>
                    <?php endfor;

                    if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">..</span></li>
                    <?php endif;

                    if ($endPage < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?q=<?php echo urlencode($query) ?>&order=<?php echo $orderBy ?>&page=<?php echo $totalPages ?>"><?php echo $totalPages ?></a>
                        </li>
                    <?php endif;
                }
                ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?q=<?php echo urlencode($query) ?>&order=<?php echo $orderBy ?>&page=<?php echo $page + 1 ?>">»</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
