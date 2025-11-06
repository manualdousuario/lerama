<?php

declare(strict_types=1);

namespace Lerama\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\Response\EmptyResponse;
use League\Plates\Engine;
use Lerama\Services\FeedTypeDetector;
use DB;

class AdminController
{
    private Engine $templates;

    public function __construct()
    {
        $this->templates = new Engine(__DIR__ . '/../../templates');
    }

    public function loginForm(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            return new RedirectResponse('/admin');
        }

        $html = $this->templates->render('admin/login', [
            'title' => 'Login'
        ]);

        return new HtmlResponse($html);
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $params = (array)$request->getParsedBody();
        $username = $params['username'] ?? '';
        $password = $params['password'] ?? '';

        if ($username === $_ENV['ADMIN_USERNAME'] && $password === $_ENV['ADMIN_PASSWORD']) {
            $_SESSION['admin_logged_in'] = true;
            return new RedirectResponse('/admin');
        }

        $html = $this->templates->render('admin/login', [
            'title' => 'Login',
            'error' => 'Nome de usuário ou senha inválidos'
        ]);

        return new HtmlResponse($html);
    }

    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        session_destroy();

        return new RedirectResponse('/admin/login');
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $search = $params['search'] ?? '';
        $feedId = isset($params['feed']) ? (int)$params['feed'] : null;

        $query = "SELECT fi.*, f.title as feed_title 
                 FROM feed_items fi 
                 JOIN feeds f ON fi.feed_id = f.id";
        $countQuery = "SELECT COUNT(*) FROM feed_items fi JOIN feeds f ON fi.feed_id = f.id";
        $queryParams = [];
        $whereAdded = false;

        if (!empty($search)) {
            $query .= " WHERE MATCH(fi.title, fi.content) AGAINST (%s IN BOOLEAN MODE)";
            $countQuery .= " WHERE MATCH(fi.title, fi.content) AGAINST (%s IN BOOLEAN MODE)";
            $queryParams[] = $search;
            $whereAdded = true;
        }

        if ($feedId) {
            if ($whereAdded) {
                $query .= " AND fi.feed_id = %i";
                $countQuery .= " AND fi.feed_id = %i";
            } else {
                $query .= " WHERE fi.feed_id = %i";
                $countQuery .= " WHERE fi.feed_id = %i";
                $whereAdded = true;
            }
            $queryParams[] = $feedId;
        }

        $query .= " ORDER BY fi.published_at DESC LIMIT %i, %i";
        $finalQueryParams = [...$queryParams, $offset, $perPage];

        $items = DB::query($query, ...$finalQueryParams);
        $totalCount = DB::queryFirstField($countQuery, ...$queryParams);
        $totalPages = ceil($totalCount / $perPage);

        $feeds = DB::query("SELECT id, title FROM feeds ORDER BY title");

        $html = $this->templates->render('admin/items', [
            'items' => $items,
            'feeds' => $feeds,
            'search' => $search,
            'selectedFeed' => $feedId,
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'baseUrl' => '/admin?' . http_build_query(array_filter([
                    'search' => $search,
                    'feed' => $feedId
                ]))
            ],
            'title' => 'Gerenciar Itens do Feed'
        ]);

        return new HtmlResponse($html);
    }

    public function feeds(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        $status = $params['status'] ?? '';
        $search = $params['search'] ?? '';

        $query = "
            SELECT f.*,
                  (SELECT COUNT(*) FROM feed_items WHERE feed_id = f.id) as item_count,
                  (SELECT MAX(published_at) FROM feed_items WHERE feed_id = f.id) as latest_item_date
            FROM feeds f
        ";
        $countQuery = "SELECT COUNT(*) FROM feeds f";
        $queryParams = [];
        $whereConditions = [];

        if (!empty($search)) {
            $searchPattern = '%' . $search . '%';
            $whereConditions[] = "(f.title LIKE %s OR f.feed_url LIKE %s OR f.site_url LIKE %s)";
            $queryParams[] = $searchPattern;
            $queryParams[] = $searchPattern;
            $queryParams[] = $searchPattern;
        }

        if (!empty($status) && in_array($status, ['online', 'offline', 'paused', 'pending', 'rejected'])) {
            $whereConditions[] = "f.status = %s";
            $queryParams[] = $status;
        }

        if (!empty($whereConditions)) {
            $whereClause = " WHERE " . implode(" AND ", $whereConditions);
            $query .= $whereClause;
            $countQuery .= $whereClause;
        }

        $query .= " ORDER BY f.title LIMIT %i, %i";
        $finalQueryParams = [...$queryParams, $offset, $perPage];

        $feeds = DB::query($query, ...$finalQueryParams);
        $totalCount = DB::queryFirstField($countQuery, ...$queryParams);
        $totalPages = ceil($totalCount / $perPage);

        // Get categories and tags for each feed
        foreach ($feeds as &$feed) {
            $feed['categories'] = DB::query("
                SELECT c.name
                FROM categories c
                JOIN feed_categories fc ON c.id = fc.category_id
                WHERE fc.feed_id = %i
                ORDER BY c.name
            ", $feed['id']);
            
            $feed['tags'] = DB::query("
                SELECT t.name
                FROM tags t
                JOIN feed_tags ft ON t.id = ft.tag_id
                WHERE ft.feed_id = %i
                ORDER BY t.name
            ", $feed['id']);
        }

        // Get all categories and tags for bulk editing
        $allCategories = DB::query("SELECT * FROM categories ORDER BY name");
        $allTags = DB::query("SELECT * FROM tags ORDER BY name");

        $html = $this->templates->render('admin/feeds', [
            'feeds' => $feeds,
            'allCategories' => $allCategories,
            'allTags' => $allTags,
            'currentStatus' => $status,
            'searchQuery' => $search,
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'baseUrl' => '/admin/feeds?' . http_build_query(array_filter([
                    'status' => $status,
                    'search' => $search
                ]))
            ],
            'title' => 'Gerenciar Feeds'
        ]);

        return new HtmlResponse($html);
    }

    public function newFeedForm(ServerRequestInterface $request): ResponseInterface
    {
        $allCategories = DB::query("SELECT * FROM categories ORDER BY name");
        $allTags = DB::query("SELECT * FROM tags ORDER BY name");

        if ($request->getMethod() === 'POST') {
            $params = (array)$request->getParsedBody();
            $title = $params['title'] ?? '';
            $feedUrl = $params['feed_url'] ?? '';
            $siteUrl = $params['site_url'] ?? '';
            $language = $params['language'] ?? 'en';
            $feedType = $params['feed_type'] ?? '';
            $categoryIds = $params['categories'] ?? [];
            $tagIds = $params['tags'] ?? [];

            $errors = [];
            if (empty($title)) {
                $errors['title'] = 'O título é obrigatório';
            }
            if (empty($feedUrl)) {
                $errors['feed_url'] = 'A URL do feed é obrigatória';
            } elseif (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
                $errors['feed_url'] = 'A URL do feed deve ser uma URL válida';
            }
            if (empty($siteUrl)) {
                $errors['site_url'] = 'A URL do site é obrigatória';
            } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
                $errors['site_url'] = 'A URL do site deve ser uma URL válida';
            }

            if (!in_array($language, ['en', 'pt-BR', 'es'])) {
                $errors['language'] = 'Idioma selecionado inválido';
            }

            if (empty($errors)) {
                try {
                    if (empty($feedType)) {
                        $detector = new FeedTypeDetector();
                        $detectedType = $detector->detectType($feedUrl);

                        if ($detectedType) {
                            $feedType = $detectedType;
                        } else {
                            $feedType = 'rss2';
                        }
                    }

                    $feedId = DB::insert('feeds', [
                        'title' => $title,
                        'feed_url' => $feedUrl,
                        'site_url' => $siteUrl,
                        'feed_type' => $feedType,
                        'language' => $language,
                        'status' => 'online'
                    ]);

                    // Insert categories
                    if (!empty($categoryIds)) {
                        foreach ($categoryIds as $categoryId) {
                            DB::insert('feed_categories', [
                                'feed_id' => $feedId,
                                'category_id' => (int)$categoryId
                            ]);
                        }
                    }

                    // Insert tags
                    if (!empty($tagIds)) {
                        foreach ($tagIds as $tagId) {
                            DB::insert('feed_tags', [
                                'feed_id' => $feedId,
                                'tag_id' => (int)$tagId
                            ]);
                        }
                    }

                    return new RedirectResponse('/admin/feeds');
                } catch (\Exception $e) {
                    $errors['general'] = 'Erro ao criar feed: ' . $e->getMessage();
                }
            }

            $html = $this->templates->render('admin/feed-form', [
                'title' => 'Adicionar Novo Feed',
                'isEdit' => false,
                'feed' => [
                    'title' => $title,
                    'feed_url' => $feedUrl,
                    'site_url' => $siteUrl,
                    'feed_type' => $feedType,
                    'language' => $language
                ],
                'selectedCategories' => $categoryIds,
                'selectedTags' => $tagIds,
                'allCategories' => $allCategories,
                'allTags' => $allTags,
                'errors' => $errors
            ]);

            return new HtmlResponse($html);
        }

        $html = $this->templates->render('admin/feed-form', [
            'title' => 'Adicionar Novo Feed',
            'isEdit' => false,
            'feed' => null,
            'selectedCategories' => [],
            'selectedTags' => [],
            'allCategories' => $allCategories,
            'allTags' => $allTags,
            'errors' => []
        ]);

        return new HtmlResponse($html);
    }

    public function editFeedForm(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $feed = DB::queryFirstRow("SELECT * FROM feeds WHERE id = %i", $id);
        if (!$feed) {
            return new RedirectResponse('/admin/feeds');
        }

        $allCategories = DB::query("SELECT * FROM categories ORDER BY name");
        $allTags = DB::query("SELECT * FROM tags ORDER BY name");
        $selectedCategories = DB::query("SELECT category_id FROM feed_categories WHERE feed_id = %i", $id);
        $selectedTags = DB::query("SELECT tag_id FROM feed_tags WHERE feed_id = %i", $id);
        
        $selectedCategoryIds = array_column($selectedCategories, 'category_id');
        $selectedTagIds = array_column($selectedTags, 'tag_id');

        if ($request->getMethod() === 'POST') {
            $params = (array)$request->getParsedBody();
            $title = $params['title'] ?? '';
            $feedUrl = $params['feed_url'] ?? '';
            $siteUrl = $params['site_url'] ?? '';
            $feedType = $params['feed_type'] ?? '';
            $language = $params['language'] ?? $feed['language'];
            $status = $params['status'] ?? $feed['status'];
            $categoryIds = $params['categories'] ?? [];
            $tagIds = $params['tags'] ?? [];

            $errors = [];
            if (empty($title)) {
                $errors['title'] = 'O título é obrigatório';
            }
            if (empty($feedUrl)) {
                $errors['feed_url'] = 'A URL do feed é obrigatória';
            } elseif (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
                $errors['feed_url'] = 'A URL do feed deve ser uma URL válida';
            }
            if (empty($siteUrl)) {
                $errors['site_url'] = 'A URL do site é obrigatória';
            } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
                $errors['site_url'] = 'A URL do site deve ser uma URL válida';
            }

            if (!in_array($language, ['en', 'pt-BR', 'es'])) {
                $errors['language'] = 'Idioma selecionado inválido';
            }

            if (empty($errors)) {
                try {
                    $updateData = [
                        'title' => $title,
                        'feed_url' => $feedUrl,
                        'site_url' => $siteUrl,
                        'language' => $language,
                        'status' => $status
                    ];

                    if (!empty($feedType)) {
                        $updateData['feed_type'] = $feedType;
                    }

                    DB::update('feeds', $updateData, 'id=%i', $id);

                    // Update categories
                    DB::delete('feed_categories', 'feed_id=%i', $id);
                    if (!empty($categoryIds)) {
                        foreach ($categoryIds as $categoryId) {
                            DB::insert('feed_categories', [
                                'feed_id' => $id,
                                'category_id' => (int)$categoryId
                            ]);
                        }
                    }

                    // Update tags
                    DB::delete('feed_tags', 'feed_id=%i', $id);
                    if (!empty($tagIds)) {
                        foreach ($tagIds as $tagId) {
                            DB::insert('feed_tags', [
                                'feed_id' => $id,
                                'tag_id' => (int)$tagId
                            ]);
                        }
                    }

                    return new RedirectResponse('/admin/feeds');
                } catch (\Exception $e) {
                    $errors['general'] = 'Erro ao atualizar feed: ' . $e->getMessage();
                }
            }

            $html = $this->templates->render('admin/feed-form', [
                'title' => 'Editar Feed',
                'isEdit' => true,
                'feed' => [
                    'id' => $id,
                    'title' => $title,
                    'feed_url' => $feedUrl,
                    'site_url' => $siteUrl,
                    'feed_type' => $feedType,
                    'language' => $language,
                    'status' => $status
                ],
                'selectedCategories' => $categoryIds,
                'selectedTags' => $tagIds,
                'allCategories' => $allCategories,
                'allTags' => $allTags,
                'errors' => $errors
            ]);

            return new HtmlResponse($html);
        }

        $html = $this->templates->render('admin/feed-form', [
            'title' => 'Editar Feed',
            'isEdit' => true,
            'feed' => $feed,
            'selectedCategories' => $selectedCategoryIds,
            'selectedTags' => $selectedTagIds,
            'allCategories' => $allCategories,
            'allTags' => $allTags,
            'errors' => []
        ]);

        return new HtmlResponse($html);
    }

    public function createFeed(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array)$request->getParsedBody();
        $title = $params['title'] ?? '';
        $feedUrl = $params['feed_url'] ?? '';
        $siteUrl = $params['site_url'] ?? '';
        $feedType = $params['feed_type'] ?? '';

        $errors = [];
        if (empty($title)) {
            $errors['title'] = 'O título é obrigatório';
        }
        if (empty($feedUrl)) {
            $errors['feed_url'] = 'A URL do feed é obrigatória';
        } elseif (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            $errors['feed_url'] = 'A URL do feed deve ser uma URL válida';
        }
        if (empty($siteUrl)) {
            $errors['site_url'] = 'A URL do site é obrigatória';
        } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            $errors['site_url'] = 'A URL do site deve ser uma URL válida';
        }

        if (!empty($errors)) {
            return new JsonResponse([
                'success' => false,
                'errors' => $errors
            ], 400);
        }

        try {
            if (empty($feedType)) {
                $detector = new FeedTypeDetector();
                $detectedType = $detector->detectType($feedUrl);

                if ($detectedType) {
                    $feedType = $detectedType;
                } else {
                    $feedType = 'rss2';
                }
            }

            DB::insert('feeds', [
                'title' => $title,
                'feed_url' => $feedUrl,
                'site_url' => $siteUrl,
                'feed_type' => $feedType,
                'status' => 'online'
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Feed criado com sucesso'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao criar feed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateFeed(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];
        
        $body = $request->getBody()->getContents();
        $params = !empty($body) ? json_decode($body, true) : [];
        
        if ($params === null) {
            $params = (array)$request->getParsedBody();
        }

        $feed = DB::queryFirstRow("SELECT * FROM feeds WHERE id = %i", $id);
        if (!$feed) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Feed não encontrado'
            ], 404);
        }

        $errors = [];
        $updateData = [];

        if (isset($params['title'])) {
            if (empty($params['title'])) {
                $errors['title'] = 'O título não pode estar vazio';
            } else {
                $updateData['title'] = $params['title'];
            }
        }

        if (isset($params['feed_url'])) {
            if (empty($params['feed_url'])) {
                $errors['feed_url'] = 'A URL do feed não pode estar vazia';
            } elseif (!filter_var($params['feed_url'], FILTER_VALIDATE_URL)) {
                $errors['feed_url'] = 'A URL do feed deve ser uma URL válida';
            } else {
                $updateData['feed_url'] = $params['feed_url'];
            }
        }

        if (isset($params['site_url'])) {
            if (empty($params['site_url'])) {
                $errors['site_url'] = 'A URL do site não pode estar vazia';
            } elseif (!filter_var($params['site_url'], FILTER_VALIDATE_URL)) {
                $errors['site_url'] = 'A URL do site deve ser uma URL válida';
            } else {
                $updateData['site_url'] = $params['site_url'];
            }
        }

        if (isset($params['feed_type'])) {
            $updateData['feed_type'] = $params['feed_type'];
        }

        if (isset($params['status'])) {
            $validStatuses = ['online', 'offline', 'paused', 'pending', 'rejected'];
            if (!in_array($params['status'], $validStatuses)) {
                $errors['status'] = 'Status inválido. Valores permitidos: online, offline, paused, pending, rejected';
            } else {
                $updateData['status'] = $params['status'];
            }
        }

        if (!empty($errors)) {
            return new JsonResponse([
                'success' => false,
                'errors' => $errors
            ], 400);
        }

        if (empty($updateData)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nenhum campo para atualizar'
            ], 400);
        }

        try {
            DB::update('feeds', $updateData, 'id=%i', $id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Feed atualizado com sucesso'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao atualizar feed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteFeed(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $feed = DB::queryFirstRow("SELECT * FROM feeds WHERE id = %i", $id);
        if (!$feed) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Feed não encontrado'
            ], 404);
        }

        try {
            DB::delete('feeds', 'id=%i', $id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Feed excluído com sucesso'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao excluir feed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateItem(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];
        
        $body = $request->getBody()->getContents();
        $params = !empty($body) ? json_decode($body, true) : [];
        
        if ($params === null) {
            $params = (array)$request->getParsedBody();
        }

        $item = DB::queryFirstRow("SELECT * FROM feed_items WHERE id = %i", $id);
        if (!$item) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Item não encontrado'
            ], 404);
        }

        if (isset($params['is_visible'])) {
            $isVisible = (bool)$params['is_visible'];

            try {
                DB::update('feed_items', [
                    'is_visible' => $isVisible ? 1 : 0
                ], 'id=%i', $id);

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Item atualizado com sucesso'
                ]);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erro ao atualizar item: ' . $e->getMessage()
                ], 500);
            }
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Nenhum campo para atualizar'
        ], 400);
    }

    // Categories Management
    public function categories(ServerRequestInterface $request): ResponseInterface
    {
        $categories = DB::query("
            SELECT c.*,
                   (SELECT COUNT(*) FROM feed_categories WHERE category_id = c.id) as feed_count
            FROM categories c
            ORDER BY c.name
        ");

        $html = $this->templates->render('admin/categories', [
            'categories' => $categories,
            'title' => 'Gerenciar Categorias'
        ]);

        return new HtmlResponse($html);
    }

    public function newCategoryForm(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $params = (array)$request->getParsedBody();
            $name = trim($params['name'] ?? '');
            $slug = trim($params['slug'] ?? '');

            $errors = [];
            if (empty($name)) {
                $errors['name'] = 'O nome é obrigatório';
            }
            
            if (empty($slug)) {
                $slug = $this->generateSlug($name);
            }

            // Check if slug already exists
            $existing = DB::queryFirstRow("SELECT id FROM categories WHERE slug = %s", $slug);
            if ($existing) {
                $errors['slug'] = 'Este slug já existe';
            }

            if (empty($errors)) {
                try {
                    DB::insert('categories', [
                        'name' => $name,
                        'slug' => $slug
                    ]);

                    return new RedirectResponse('/admin/categories');
                } catch (\Exception $e) {
                    $errors['general'] = 'Erro ao criar categoria: ' . $e->getMessage();
                }
            }

            $html = $this->templates->render('admin/category-form', [
                'title' => 'Nova Categoria',
                'isEdit' => false,
                'category' => [
                    'name' => $name,
                    'slug' => $slug
                ],
                'errors' => $errors
            ]);

            return new HtmlResponse($html);
        }

        $html = $this->templates->render('admin/category-form', [
            'title' => 'Nova Categoria',
            'isEdit' => false,
            'category' => null,
            'errors' => []
        ]);

        return new HtmlResponse($html);
    }

    public function editCategoryForm(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $category = DB::queryFirstRow("SELECT * FROM categories WHERE id = %i", $id);
        if (!$category) {
            return new RedirectResponse('/admin/categories');
        }

        if ($request->getMethod() === 'POST') {
            $params = (array)$request->getParsedBody();
            $name = trim($params['name'] ?? '');
            $slug = trim($params['slug'] ?? '');

            $errors = [];
            if (empty($name)) {
                $errors['name'] = 'O nome é obrigatório';
            }

            if (empty($slug)) {
                $slug = $this->generateSlug($name);
            }

            // Check if slug already exists (excluding current category)
            $existing = DB::queryFirstRow("SELECT id FROM categories WHERE slug = %s AND id != %i", $slug, $id);
            if ($existing) {
                $errors['slug'] = 'Este slug já existe';
            }

            if (empty($errors)) {
                try {
                    DB::update('categories', [
                        'name' => $name,
                        'slug' => $slug
                    ], 'id=%i', $id);

                    return new RedirectResponse('/admin/categories');
                } catch (\Exception $e) {
                    $errors['general'] = 'Erro ao atualizar categoria: ' . $e->getMessage();
                }
            }

            $html = $this->templates->render('admin/category-form', [
                'title' => 'Editar Categoria',
                'isEdit' => true,
                'category' => [
                    'id' => $id,
                    'name' => $name,
                    'slug' => $slug
                ],
                'errors' => $errors
            ]);

            return new HtmlResponse($html);
        }

        $html = $this->templates->render('admin/category-form', [
            'title' => 'Editar Categoria',
            'isEdit' => true,
            'category' => $category,
            'errors' => []
        ]);

        return new HtmlResponse($html);
    }

    public function createCategory(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array)$request->getParsedBody();
        $name = trim($params['name'] ?? '');
        $slug = trim($params['slug'] ?? '');

        $errors = [];
        if (empty($name)) {
            $errors['name'] = 'O nome é obrigatório';
        }
        
        if (empty($slug)) {
            $slug = $this->generateSlug($name);
        }

        // Check if slug already exists
        $existing = DB::queryFirstRow("SELECT id FROM categories WHERE slug = %s", $slug);
        if ($existing) {
            $errors['slug'] = 'Este slug já existe';
        }

        if (!empty($errors)) {
            return new JsonResponse([
                'success' => false,
                'errors' => $errors
            ], 400);
        }

        try {
            DB::insert('categories', [
                'name' => $name,
                'slug' => $slug
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Categoria criada com sucesso'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao criar categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateCategory(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];
        $params = json_decode($request->getBody()->getContents(), true) ?: (array)$request->getParsedBody();

        $category = DB::queryFirstRow("SELECT * FROM categories WHERE id = %i", $id);
        if (!$category) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Categoria não encontrada'
            ], 404);
        }

        $updateData = [];
        if (isset($params['name'])) {
            $updateData['name'] = trim($params['name']);
        }
        if (isset($params['slug'])) {
            $slug = trim($params['slug']);
            $existing = DB::queryFirstRow("SELECT id FROM categories WHERE slug = %s AND id != %i", $slug, $id);
            if ($existing) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => ['slug' => 'Este slug já existe']
                ], 400);
            }
            $updateData['slug'] = $slug;
        }

        if (empty($updateData)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nenhum campo para atualizar'
            ], 400);
        }

        try {
            DB::update('categories', $updateData, 'id=%i', $id);
            return new JsonResponse([
                'success' => true,
                'message' => 'Categoria atualizada com sucesso'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao atualizar categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteCategory(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $category = DB::queryFirstRow("SELECT * FROM categories WHERE id = %i", $id);
        if (!$category) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Categoria não encontrada'
            ], 404);
        }

        try {
            DB::delete('categories', 'id=%i', $id);
            return new JsonResponse([
                'success' => true,
                'message' => 'Categoria excluída com sucesso'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao excluir categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    // Tags Management
    public function tags(ServerRequestInterface $request): ResponseInterface
    {
        $tags = DB::query("
            SELECT t.*,
                   (SELECT COUNT(*) FROM feed_tags WHERE tag_id = t.id) as feed_count
            FROM tags t
            ORDER BY t.name
        ");

        $html = $this->templates->render('admin/tags', [
            'tags' => $tags,
            'title' => 'Gerenciar Tags'
        ]);

        return new HtmlResponse($html);
    }

    public function newTagForm(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $params = (array)$request->getParsedBody();
            $name = trim($params['name'] ?? '');
            $slug = trim($params['slug'] ?? '');

            $errors = [];
            if (empty($name)) {
                $errors['name'] = 'O nome é obrigatório';
            }
            
            if (empty($slug)) {
                $slug = $this->generateSlug($name);
            }

            // Check if slug already exists
            $existing = DB::queryFirstRow("SELECT id FROM tags WHERE slug = %s", $slug);
            if ($existing) {
                $errors['slug'] = 'Este slug já existe';
            }

            if (empty($errors)) {
                try {
                    DB::insert('tags', [
                        'name' => $name,
                        'slug' => $slug
                    ]);

                    return new RedirectResponse('/admin/tags');
                } catch (\Exception $e) {
                    $errors['general'] = 'Erro ao criar tag: ' . $e->getMessage();
                }
            }

            $html = $this->templates->render('admin/tag-form', [
                'title' => 'Nova Tag',
                'isEdit' => false,
                'tag' => [
                    'name' => $name,
                    'slug' => $slug
                ],
                'errors' => $errors
            ]);

            return new HtmlResponse($html);
        }

        $html = $this->templates->render('admin/tag-form', [
            'title' => 'Nova Tag',
            'isEdit' => false,
            'tag' => null,
            'errors' => []
        ]);

        return new HtmlResponse($html);
    }

    public function editTagForm(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $tag = DB::queryFirstRow("SELECT * FROM tags WHERE id = %i", $id);
        if (!$tag) {
            return new RedirectResponse('/admin/tags');
        }

        if ($request->getMethod() === 'POST') {
            $params = (array)$request->getParsedBody();
            $name = trim($params['name'] ?? '');
            $slug = trim($params['slug'] ?? '');

            $errors = [];
            if (empty($name)) {
                $errors['name'] = 'O nome é obrigatório';
            }

            if (empty($slug)) {
                $slug = $this->generateSlug($name);
            }

            // Check if slug already exists (excluding current tag)
            $existing = DB::queryFirstRow("SELECT id FROM tags WHERE slug = %s AND id != %i", $slug, $id);
            if ($existing) {
                $errors['slug'] = 'Este slug já existe';
            }

            if (empty($errors)) {
                try {
                    DB::update('tags', [
                        'name' => $name,
                        'slug' => $slug
                    ], 'id=%i', $id);

                    return new RedirectResponse('/admin/tags');
                } catch (\Exception $e) {
                    $errors['general'] = 'Erro ao atualizar tag: ' . $e->getMessage();
                }
            }

            $html = $this->templates->render('admin/tag-form', [
                'title' => 'Editar Tag',
                'isEdit' => true,
                'tag' => [
                    'id' => $id,
                    'name' => $name,
                    'slug' => $slug
                ],
                'errors' => $errors
            ]);

            return new HtmlResponse($html);
        }

        $html = $this->templates->render('admin/tag-form', [
            'title' => 'Editar Tag',
            'isEdit' => true,
            'tag' => $tag,
            'errors' => []
        ]);

        return new HtmlResponse($html);
    }

    public function createTag(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array)$request->getParsedBody();
        $name = trim($params['name'] ?? '');
        $slug = trim($params['slug'] ?? '');

        $errors = [];
        if (empty($name)) {
            $errors['name'] = 'O nome é obrigatório';
        }
        
        if (empty($slug)) {
            $slug = $this->generateSlug($name);
        }

        // Check if slug already exists
        $existing = DB::queryFirstRow("SELECT id FROM tags WHERE slug = %s", $slug);
        if ($existing) {
            $errors['slug'] = 'Este slug já existe';
        }

        if (!empty($errors)) {
            return new JsonResponse([
                'success' => false,
                'errors' => $errors
            ], 400);
        }

        try {
            DB::insert('tags', [
                'name' => $name,
                'slug' => $slug
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Tag criada com sucesso'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao criar tag: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateTag(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];
        $params = json_decode($request->getBody()->getContents(), true) ?: (array)$request->getParsedBody();

        $tag = DB::queryFirstRow("SELECT * FROM tags WHERE id = %i", $id);
        if (!$tag) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Tag não encontrada'
            ], 404);
        }

        $updateData = [];
        if (isset($params['name'])) {
            $updateData['name'] = trim($params['name']);
        }
        if (isset($params['slug'])) {
            $slug = trim($params['slug']);
            $existing = DB::queryFirstRow("SELECT id FROM tags WHERE slug = %s AND id != %i", $slug, $id);
            if ($existing) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => ['slug' => 'Este slug já existe']
                ], 400);
            }
            $updateData['slug'] = $slug;
        }

        if (empty($updateData)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nenhum campo para atualizar'
            ], 400);
        }

        try {
            DB::update('tags', $updateData, 'id=%i', $id);
            return new JsonResponse([
                'success' => true,
                'message' => 'Tag atualizada com sucesso'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao atualizar tag: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteTag(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $tag = DB::queryFirstRow("SELECT * FROM tags WHERE id = %i", $id);
        if (!$tag) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Tag não encontrada'
            ], 404);
        }

        try {
            DB::delete('tags', 'id=%i', $id);
            return new JsonResponse([
                'success' => true,
                'message' => 'Tag excluída com sucesso'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao excluir tag: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkUpdateFeedCategories(ServerRequestInterface $request): ResponseInterface
    {
        $params = json_decode($request->getBody()->getContents(), true) ?: (array)$request->getParsedBody();
        
        $feedIds = $params['feed_ids'] ?? [];
        $categoryIds = $params['category_ids'] ?? [];
        
        if (empty($feedIds) || !is_array($feedIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nenhum feed selecionado'
            ], 400);
        }
        
        if (empty($categoryIds) || !is_array($categoryIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nenhuma categoria selecionada'
            ], 400);
        }
        
        try {
            $affectedCategoryIds = [];
            foreach ($feedIds as $feedId) {
                $oldCategories = DB::query("SELECT category_id FROM feed_categories WHERE feed_id = %i", (int)$feedId);
                foreach ($oldCategories as $cat) {
                    $affectedCategoryIds[] = $cat['category_id'];
                }
            }
            $affectedCategoryIds = array_merge($affectedCategoryIds, $categoryIds);
            $affectedCategoryIds = array_unique($affectedCategoryIds);
            
            // Remove existing categories for selected feeds
            foreach ($feedIds as $feedId) {
                DB::delete('feed_categories', 'feed_id=%i', (int)$feedId);
            }
            
            // Add new categories
            foreach ($feedIds as $feedId) {
                foreach ($categoryIds as $categoryId) {
                    DB::insert('feed_categories', [
                        'feed_id' => (int)$feedId,
                        'category_id' => (int)$categoryId
                    ]);
                }
            }
            
            $this->recalculateCategoryItemCounts($affectedCategoryIds);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Categorias atualizadas com sucesso para ' . count($feedIds) . ' feed(s)'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao atualizar categorias: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function bulkUpdateFeedTags(ServerRequestInterface $request): ResponseInterface
    {
        $params = json_decode($request->getBody()->getContents(), true) ?: (array)$request->getParsedBody();
        
        $feedIds = $params['feed_ids'] ?? [];
        $tagIds = $params['tag_ids'] ?? [];
        
        if (empty($feedIds) || !is_array($feedIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nenhum feed selecionado'
            ], 400);
        }
        
        if (empty($tagIds) || !is_array($tagIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nenhuma tag selecionada'
            ], 400);
        }
        
        try {
            $affectedTagIds = [];
            foreach ($feedIds as $feedId) {
                $oldTags = DB::query("SELECT tag_id FROM feed_tags WHERE feed_id = %i", (int)$feedId);
                foreach ($oldTags as $tag) {
                    $affectedTagIds[] = $tag['tag_id'];
                }
            }
            $affectedTagIds = array_merge($affectedTagIds, $tagIds);
            $affectedTagIds = array_unique($affectedTagIds);
            
            // Remove existing tags for selected feeds
            foreach ($feedIds as $feedId) {
                DB::delete('feed_tags', 'feed_id=%i', (int)$feedId);
            }
            
            // Add new tags
            foreach ($feedIds as $feedId) {
                foreach ($tagIds as $tagId) {
                    DB::insert('feed_tags', [
                        'feed_id' => (int)$feedId,
                        'tag_id' => (int)$tagId
                    ]);
                }
            }
            
            $this->recalculateTagItemCounts($affectedTagIds);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Tags atualizadas com sucesso para ' . count($feedIds) . ' feed(s)'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao atualizar tags: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate item counts for specified categories
     *
     * @param array $categoryIds Array of category IDs to recalculate
     */
    private function recalculateCategoryItemCounts(array $categoryIds): void
    {
        if (empty($categoryIds)) {
            return;
        }
        
        foreach ($categoryIds as $categoryId) {
            $count = DB::queryFirstField("
                SELECT COUNT(DISTINCT fi.id)
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                JOIN feed_categories fc ON f.id = fc.feed_id
                WHERE fc.category_id = %i
                AND fi.is_visible = 1
            ", (int)$categoryId);
            
            DB::update('categories', [
                'item_count' => $count ?: 0
            ], 'id=%i', (int)$categoryId);
        }
    }
    
    /**
     * Recalculate item counts for specified tags
     *
     * @param array $tagIds Array of tag IDs to recalculate
     */
    private function recalculateTagItemCounts(array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }
        
        foreach ($tagIds as $tagId) {
            $count = DB::queryFirstField("
                SELECT COUNT(DISTINCT fi.id)
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                JOIN feed_tags ft ON f.id = ft.feed_id
                WHERE ft.tag_id = %i
                AND fi.is_visible = 1
            ", (int)$tagId);
            
            DB::update('tags', [
                'item_count' => $count ?: 0
            ], 'id=%i', (int)$tagId);
        }
    }

    // Helper function to generate slug
    private function generateSlug(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        
        // Replace special characters
        $text = str_replace(
            ['á', 'à', 'ã', 'â', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ü', 'ç'],
            ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'c'],
            $text
        );
        
        // Remove non-alphanumeric characters
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        // Remove leading/trailing hyphens
        $text = trim($text, '-');
        
        return $text;
    }
}
