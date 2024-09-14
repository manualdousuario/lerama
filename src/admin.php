<?php
require_once __DIR__ . '/class/Database.php';

use Src\Database;

$appConfig = require __DIR__ . '/config.php';

session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
        $submittedPassword = $_POST['admin_password'];
        if ($submittedPassword === $appConfig['admin_password']) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: /admin');
            exit;
        } else {
            $loginError = "🚷";
        }
    }

    include __DIR__ . '/header.php';
    ?>
    <div class="container mt-5">
        <h2>🔑</h2>
        <?php if (isset($loginError)): ?>
            <div class="alert alert-danger"><?php echo $loginError ?></div>
        <?php endif; ?>
        <form method="POST" action="/admin">
            <div class="mb-3">
                <label for="admin_password" class="form-label">Senha</label>
                <input type="password" class="form-control" id="admin_password" name="admin_password" required>
            </div>
            <button type="submit" class="btn btn-primary">Entrar</button>
        </form>
    </div>
    <?php
    include __DIR__ . '/footer.php';
    exit;
}

$db = Database::getInstance($appConfig['database']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['url'], $_POST['feed_url'])) {
    $name = $_POST['name'];
    $url = $_POST['url'];
    $feed_url = $_POST['feed_url'];

    if ($name && $url && $feed_url) {
        $stmt = $db->prepare("INSERT INTO sites (name, url, feed_url, status) VALUES (:name, :url, :feed_url, 'active')");
        $stmt->execute([
            'name' => $name,
            'url' => $url,
            'feed_url' => $feed_url
        ]);

        header('Location: /admin');
        exit;
    } else {
        $error = "Preencha todos os campos!";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['site_id'], $_POST['new_name'])) {
    $site_id = $_POST['site_id'];
    $new_name = $_POST['new_name'];

    $stmt = $db->prepare("UPDATE sites SET name = :new_name WHERE id = :id");
    $stmt->execute([
        'new_name' => $new_name,
        'id' => $site_id
    ]);

    header('Location: /admin');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['site_id'], $_POST['status']) && !isset($_POST['new_name'])) {
    $site_id = $_POST['site_id'];
    $status = $_POST['status'];

    $stmt = $db->prepare("UPDATE sites SET status = :status WHERE id = :id");
    $stmt->execute([
        'status' => $status,
        'id' => $site_id
    ]);

    header('Location: /admin');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['site_id'], $_POST['status'])) {
    $site_id = $_POST['site_id'];
    $status = $_POST['status'];

    $stmt = $db->prepare("UPDATE sites SET status = :status WHERE id = :id");
    $stmt->execute([
        'status' => $status,
        'id' => $site_id
    ]);

    header('Location: /admin');
    exit;
}

$sites = $db->query("SELECT * FROM sites")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/header.php';
?>

<div class="container mt-5">
    <h2>Sites</h2>

    <table class="table table-striped mt-4">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>URL</th>
                <th>Feed URL</th>
                <th>Status</th>
                <th></th>
                <th>Error</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sites as $site): ?>
            <tr class="<?php echo $site['status'] == 'inactive' ? 'table-danger' : '' ?>">
                <td class="align-middle">
                    <small alt="Ultimo erro <?php echo $site['last_error_check'] ?>" class="text-muted"><?php echo htmlspecialchars($site['id']) ?></small>
                </td>
                <td class="align-middle">
                    <form method="POST" action="/admin">
                        <div class="input-group input-group-sm flex-nowrap">
                            <input type="hidden" name="site_id" value="<?php echo htmlspecialchars($site['id']) ?>">
                            <input type="text" name="new_name" value="<?php echo htmlspecialchars($site['name']) ?>" class="form-control">
                            <button type="submit" class="btn btn-secondary">💾</button>
                        </div>
                    </form>
                </td>
                <td class="align-middle"><a href="<?php echo htmlspecialchars($site['url']) ?>" target="_blank"><?php echo htmlspecialchars($site['url']) ?></a></td>
                <td class="align-middle"><a href="<?php echo htmlspecialchars($site['feed_url']) ?>" target="_blank"><?php echo htmlspecialchars($site['feed_url']) ?></a></td>
                <td class="text-center align-middle"><?php echo $site['status'] == 'active' ? '🟢' : '🔴' ?></td>
                <td class="align-middle">
                    <form method="POST" action="/admin" style="display:inline;">
                        <input type="hidden" name="site_id" value="<?php echo htmlspecialchars($site['id']) ?>">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="active" <?php echo $site['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inactive" <?php echo $site['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </form>
                </td>
                <td class="text-center align-middle">
                    <?php if($site['error_count']) { ?>
                        <small alt="Ultimo erro <?php echo $site['last_error_check'] ?>" class="text-muted"><?php echo $site['error_count'] ?></small>
                    <?php } ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3 class="mt-5">Novo Site</h3>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin">
        <div class="mb-3">
            <label for="name" class="form-label">Nome</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <div class="mb-3">
            <label for="url" class="form-label">URL</label>
            <input type="url" class="form-control" id="url" name="url" required>
        </div>
        <div class="mb-3">
            <label for="feed_url" class="form-label">Feed URL</label>
            <input type="url" class="form-control" id="feed_url" name="feed_url" required>
        </div>
        <button type="submit" class="btn btn-primary">Adicionar</button>
    </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>
