<?php
require_once __DIR__ . '/class/Database.php';

use Src\Database;

$appConfig = require __DIR__ . '/config.php';

$db = Database::getInstance($appConfig['database']);

$stmt = $db->prepare("SELECT * FROM sites");
$stmt->execute();
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/header.php';
?>

<div class="container mt-3 mt-md-5">
    <h2>Feeds</h2>
    <p>Esta é uma lista de todos os feeds que estão sendo indexados.</p>
    <ul class="list-group mt-3">
        <?php foreach ($sites as $site): ?>
            <li class="list-group-item">
                <?php if ($site['status'] === 'inactive'): ?>
                    <s><?php echo htmlspecialchars($site['name']) ?></s>
                <?php else: ?>
                    <?php echo htmlspecialchars($site['name']) ?>
                <?php endif; ?>
                <div class="float-end">
                    <a href="<?php echo htmlspecialchars($site['url']) ?>" target="_blank">Site</a> | <a href="<?php echo htmlspecialchars($site['feed_url']) ?>" target="_blank">Feed</a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<?php include __DIR__ . '/footer.php'; ?>
