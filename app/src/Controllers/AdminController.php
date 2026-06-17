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
use Lerama\Services\EmailService;
use Lerama\Services\CacheService;
use Lerama\Services\CacheableQuery;
use Lerama\Services\CacheInvalidator;
use Lerama\Services\ThumbnailService;
use Lerama\Services\CsrfService;
use Lerama\Services\UrlValidator;
use Lerama\Services\BulkInserter;
use Lerama\Services\FeedSlugService;
use DB;

class AdminController
{
    private Engine $templates;
    private CacheService $cache;

    public function __construct()
    {
        $this->templates = new Engine(__DIR__ . '/../../templates');
        $this->cache = CacheService::getInstance();
    }

    public function loginForm(ServerRequestInterface $request): ResponseInterface
    {
        $this->ensureSession();
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
        $this->ensureSession();

        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError, true);
        }

        $params = (array)$request->getParsedBody();
        $username = $params['username'] ?? '';
        $password = $params['password'] ?? '';

        if ($username === $_ENV['ADMIN_USERNAME'] && $this->verifyPassword($password)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            return new RedirectResponse('/admin');
        }

        $html = $this->templates->render('admin/login', [
            'title' => __('admin.login.title'),
            'error' => __('error.login_invalid')
        ]);

        return new HtmlResponse($html);
    }

    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $this->ensureSession();
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Strict',
                ]
            );
        }
        session_destroy();

        return new RedirectResponse('/admin/login');
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function verifyPassword(string $password): bool
    {
        $plainPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
        return !empty($plainPassword) && hash_equals($plainPassword, $password);
    }

    private function validateCsrf(ServerRequestInterface $request): ?string
    {
        CsrfService::ensureSession();

        $token = $request->getHeaderLine('X-CSRF-Token');
        if (empty($token)) {
            $params = (array)$request->getParsedBody();
            $token = $params['csrf_token'] ?? '';
        }

        if (empty($token)) {
            $rawBody = $request->getBody()->getContents();
            if (!empty($rawBody)) {
                $jsonData = json_decode($rawBody, true);
                if (is_array($jsonData)) {
                    $token = $jsonData['csrf_token'] ?? '';
                }
            }
            // Rewind body so later code can read it again
            $request->getBody()->rewind();
        }

        if (!CsrfService::validateToken($token)) {
            return __('error.csrf_invalid');
        }

        return null;
    }

    private function csrfErrorResponse(?string $error, bool $isHtml = false): ResponseInterface
    {
        if ($error === null) {
            return new EmptyResponse(200);
        }

        if ($isHtml) {
            return new HtmlResponse(
                $this->templates->render('admin/login', [
                    'title' => __('admin.login.title'),
                    'error' => $error
                ]),
                403
            );
        }

        return new JsonResponse([
            'success' => false,
            'message' => $error
        ], 403);
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

        $adminTtl = (int)($_ENV['CACHE_ADMIN_TTL'] ?? 60);
        $searchHash = $this->cache->hash([
            'search' => $search,
            'feed' => $feedId,
            'page' => $page,
            'perPage' => $perPage
        ]);

        $countCacheKey = $this->cache->key('admin', 'items', 'count', $searchHash);
        $totalCount = $this->cache->remember($countCacheKey, $adminTtl, ['admin:items'], function () use ($countQuery, $queryParams) {
            return DB::queryFirstField($countQuery, ...$queryParams);
        });
        $totalPages = ceil($totalCount / $perPage);

        $itemsCacheKey = $this->cache->key('admin', 'items', $searchHash);
        $items = $this->cache->remember($itemsCacheKey, $adminTtl, ['admin:items'], function () use ($query, $finalQueryParams) {
            return DB::query($query, ...$finalQueryParams);
        });

        $feeds = CacheableQuery::query(
            'feeds', 'dropdown', ['feeds'], 300,
            "SELECT id, title FROM feeds ORDER BY title"
        );

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
            'title' => __('admin.items.title')
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
        $shuffle = isset($params['shuffle']) && $params['shuffle'] !== '' ? (int)$params['shuffle'] : null;
        $search = $params['search'] ?? '';

        $query = "SELECT f.* FROM feeds f";
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

        if ($shuffle !== null && in_array($shuffle, [0, 1], true)) {
            $whereConditions[] = "f.shuffle = %i";
            $queryParams[] = $shuffle;
        }

        if (!empty($whereConditions)) {
            $whereClause = " WHERE " . implode(" AND ", $whereConditions);
            $query .= $whereClause;
            $countQuery .= $whereClause;
        }

        $query .= " ORDER BY f.title ASC LIMIT %i, %i";
        $finalQueryParams = [...$queryParams, $offset, $perPage];

        $adminTtl = (int)($_ENV['CACHE_ADMIN_TTL'] ?? 60);
        $filterHash = $this->cache->hash([
            'status' => $status,
            'shuffle' => $shuffle,
            'search' => $search,
            'page' => $page,
            'perPage' => $perPage
        ]);

        $countCacheKey = $this->cache->key('admin', 'feeds', 'count', $filterHash);
        $totalCount = $this->cache->remember($countCacheKey, $adminTtl, ['admin:feeds'], function () use ($countQuery, $queryParams) {
            return DB::queryFirstField($countQuery, ...$queryParams);
        });
        $totalPages = ceil($totalCount / $perPage);

        $feedsCacheKey = $this->cache->key('admin', 'feeds', $filterHash);
        $feeds = $this->cache->remember($feedsCacheKey, $adminTtl, ['admin:feeds'], function () use ($query, $finalQueryParams) {
            return DB::query($query, ...$finalQueryParams) ?: [];
        });

        if (!empty($feeds)) {
            $feedIds = array_column($feeds, 'id');

            $categoryRows = DB::query("
                SELECT fc.feed_id, c.name
                FROM feed_categories fc
                JOIN categories c ON c.id = fc.category_id
                WHERE fc.feed_id IN %li
                ORDER BY c.name
            ", $feedIds) ?: [];

            $tagRows = DB::query("
                SELECT ft.feed_id, t.name
                FROM feed_tags ft
                JOIN tags t ON t.id = ft.tag_id
                WHERE ft.feed_id IN %li
                ORDER BY t.name
            ", $feedIds) ?: [];

            $categoriesByFeed = [];
            foreach ($categoryRows as $row) {
                $categoriesByFeed[$row['feed_id']][] = ['name' => $row['name']];
            }
            $tagsByFeed = [];
            foreach ($tagRows as $row) {
                $tagsByFeed[$row['feed_id']][] = ['name' => $row['name']];
            }

            foreach ($feeds as &$feed) {
                $feed['categories'] = $categoriesByFeed[$feed['id']] ?? [];
                $feed['tags'] = $tagsByFeed[$feed['id']] ?? [];
            }
            unset($feed);
        }

        // Admin dropdowns are warm; cache for 5 min
        $allCategories = CacheableQuery::query(
            'categories', 'all', ['categories'], 300,
            "SELECT * FROM categories ORDER BY name"
        );
        $allTags = CacheableQuery::query(
            'tags', 'all', ['tags'], 300,
            "SELECT * FROM tags ORDER BY name"
        );

        $html = $this->templates->render('admin/feeds', [
            'feeds' => $feeds,
            'allCategories' => $allCategories,
            'allTags' => $allTags,
            'currentStatus' => $status,
            'currentShuffle' => $shuffle,
            'searchQuery' => $search,
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'baseUrl' => '/admin/feeds?' . http_build_query(array_filter([
                    'status' => $status,
                    'shuffle' => $shuffle !== null ? $shuffle : '',
                    'search' => $search
                ]))
            ],
            'title' => __('admin.feeds.title')
        ]);

        return new HtmlResponse($html);
    }

    public function newFeedForm(ServerRequestInterface $request): ResponseInterface
    {
        $allCategories = DB::query("SELECT * FROM categories ORDER BY name");
        $allTags = DB::query("SELECT * FROM tags ORDER BY name");

        if ($request->getMethod() === 'POST') {
            $csrfError = $this->validateCsrf($request);
            if ($csrfError !== null) {
                return $this->csrfErrorResponse($csrfError, true);
            }

            $params = (array)$request->getParsedBody();
            $title = $params['title'] ?? '';
            $feedUrl = $params['feed_url'] ?? '';
            $siteUrl = $params['site_url'] ?? '';
            $language = $params['language'] ?? 'en';
            $feedType = $params['feed_type'] ?? '';
            $categoryIds = $params['categories'] ?? [];
            $tagIds = $params['tags'] ?? [];
            $proxyOnly = isset($params['proxy_only']) ? 1 : 0;
            $shuffle = isset($params['shuffle']) ? 1 : 0;

            $errors = [];
            if (empty($title)) {
                $errors['title'] = __('validation.title_required');
            }
            if (empty($feedUrl)) {
                $errors['feed_url'] = __('validation.feed_url_required');
            } elseif (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
                $errors['feed_url'] = __('validation.feed_url_valid');
            } else {
                $urlCheck = UrlValidator::validate($feedUrl);
                if (!$urlCheck['valid']) {
                    $errors['feed_url'] = $urlCheck['error'];
                }
            }
            if (empty($siteUrl)) {
                $errors['site_url'] = __('validation.site_url_required');
            } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
                $errors['site_url'] = __('validation.site_url_valid');
            } else {
                $urlCheck = UrlValidator::validate($siteUrl);
                if (!$urlCheck['valid']) {
                    $errors['site_url'] = $urlCheck['error'];
                }
            }

            if (!in_array($language, ['en', 'pt-BR', 'es'])) {
                $errors['language'] = __('validation.language_invalid');
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
                        'slug' => FeedSlugService::generateForFeed($siteUrl),
                        'feed_type' => $feedType,
                        'language' => $language,
                        'status' => 'online',
                        'proxy_only' => $proxyOnly,
                        'shuffle' => $shuffle
                    ]);

                    // Insert categories in bulk
                    if (!empty($categoryIds)) {
                        $categoryRows = [];
                        foreach ($categoryIds as $categoryId) {
                            $categoryRows[] = [
                                'feed_id' => (int)$feedId,
                                'category_id' => (int)$categoryId,
                            ];
                        }
                        BulkInserter::insert('feed_categories', $categoryRows, ['ignore' => true]);
                    }

                    // Insert tags in bulk
                    if (!empty($tagIds)) {
                        $tagRows = [];
                        foreach ($tagIds as $tagId) {
                            $tagRows[] = [
                                'feed_id' => (int)$feedId,
                                'tag_id' => (int)$tagId,
                            ];
                        }
                        BulkInserter::insert('feed_tags', $tagRows, ['ignore' => true]);
                    }

                    $emailService = new EmailService();
                    $emailService->sendFeedRegistrationNotification([
                        'title' => $title,
                        'feed_url' => $feedUrl,
                        'site_url' => $siteUrl,
                        'feed_type' => $feedType,
                        'language' => $language,
                        'status' => 'online'
                    ]);

                    CacheInvalidator::invalidateFeeds();
                    CacheInvalidator::invalidate('admin:feeds');

                    return new RedirectResponse('/admin/feeds');
                } catch (\Exception $e) {
                    $errors['general'] = __('error.feed_create') . ': ' . $e->getMessage();
                }
            }

            $html = $this->templates->render('admin/feed-form', [
                'title' => __('admin.feed_form.add_title'),
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
            'title' => __('admin.feed_form.add_title'),
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
            $csrfError = $this->validateCsrf($request);
            if ($csrfError !== null) {
                return $this->csrfErrorResponse($csrfError, true);
            }

            $params = (array)$request->getParsedBody();
            $title = $params['title'] ?? '';
            $feedUrl = $params['feed_url'] ?? '';
            $siteUrl = $params['site_url'] ?? '';
            $feedType = $params['feed_type'] ?? '';
            $language = $params['language'] ?? $feed['language'];
            $status = $params['status'] ?? $feed['status'];
            $categoryIds = $params['categories'] ?? [];
            $tagIds = $params['tags'] ?? [];
            $proxyOnly = isset($params['proxy_only']) ? 1 : 0;
            $shuffle = isset($params['shuffle']) ? 1 : 0;

            $errors = [];
            if (empty($title)) {
                $errors['title'] = __('validation.title_required');
            }
            if (empty($feedUrl)) {
                $errors['feed_url'] = __('validation.feed_url_required');
            } elseif (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
                $errors['feed_url'] = __('validation.feed_url_valid');
            } else {
                $urlCheck = UrlValidator::validate($feedUrl);
                if (!$urlCheck['valid']) {
                    $errors['feed_url'] = $urlCheck['error'];
                }
            }
            if (empty($siteUrl)) {
                $errors['site_url'] = __('validation.site_url_required');
            } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
                $errors['site_url'] = __('validation.site_url_valid');
            } else {
                $urlCheck = UrlValidator::validate($siteUrl);
                if (!$urlCheck['valid']) {
                    $errors['site_url'] = $urlCheck['error'];
                }
            }

            if (!in_array($language, ['en', 'pt-BR', 'es'])) {
                $errors['language'] = __('validation.language_invalid');
            }

            if (empty($errors)) {
                try {
                    $updateData = [
                        'title' => $title,
                        'feed_url' => $feedUrl,
                        'site_url' => $siteUrl,
                        'language' => $language,
                        'status' => $status,
                        'proxy_only' => $proxyOnly,
                        'shuffle' => $shuffle
                    ];

                    if ($siteUrl !== $feed['site_url']) {
                        $updateData['slug'] = FeedSlugService::generateForFeed($siteUrl, $id);
                    }

                    if (!empty($feedType)) {
                        $updateData['feed_type'] = $feedType;
                    }

                    DB::update('feeds', $updateData, 'id=%i', $id);

                    // Update categories in bulk
                    DB::query("DELETE FROM feed_categories WHERE feed_id = %i", $id);
                    if (!empty($categoryIds)) {
                        $categoryRows = [];
                        foreach ($categoryIds as $categoryId) {
                            $categoryRows[] = [
                                'feed_id' => $id,
                                'category_id' => (int)$categoryId,
                            ];
                        }
                        BulkInserter::insert('feed_categories', $categoryRows, ['ignore' => true]);
                    }

                    // Update tags in bulk
                    DB::query("DELETE FROM feed_tags WHERE feed_id = %i", $id);
                    if (!empty($tagIds)) {
                        $tagRows = [];
                        foreach ($tagIds as $tagId) {
                            $tagRows[] = [
                                'feed_id' => $id,
                                'tag_id' => (int)$tagId,
                            ];
                        }
                        BulkInserter::insert('feed_tags', $tagRows, ['ignore' => true]);
                    }

                    CacheInvalidator::invalidateFeed($id);
                    CacheInvalidator::invalidate('admin:feeds');

                    return new RedirectResponse('/admin/feeds');
                } catch (\Exception $e) {
                    $errors['general'] = __('error.feed_update') . ': ' . $e->getMessage();
                }
            }

            $html = $this->templates->render('admin/feed-form', [
                'title' => __('admin.feed_form.edit_title'),
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
            'title' => __('admin.feed_form.edit_title'),
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
        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }

        $params = (array)$request->getParsedBody();
        $title = $params['title'] ?? '';
        $feedUrl = $params['feed_url'] ?? '';
        $siteUrl = $params['site_url'] ?? '';
        $feedType = $params['feed_type'] ?? '';

        $errors = [];
        if (empty($title)) {
            $errors['title'] = __('validation.title_required');
        }
        if (empty($feedUrl)) {
            $errors['feed_url'] = __('validation.feed_url_required');
        } elseif (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            $errors['feed_url'] = __('validation.feed_url_valid');
        } else {
            $urlCheck = UrlValidator::validate($feedUrl);
            if (!$urlCheck['valid']) {
                $errors['feed_url'] = $urlCheck['error'];
            }
        }
        if (empty($siteUrl)) {
            $errors['site_url'] = __('validation.site_url_required');
        } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            $errors['site_url'] = __('validation.site_url_valid');
        } else {
            $urlCheck = UrlValidator::validate($siteUrl);
            if (!$urlCheck['valid']) {
                $errors['site_url'] = $urlCheck['error'];
            }
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
                'slug' => FeedSlugService::generateForFeed($siteUrl),
                'feed_type' => $feedType,
                'status' => 'online'
            ]);

            $emailService = new EmailService();
            $emailService->sendFeedRegistrationNotification([
                'title' => $title,
                'feed_url' => $feedUrl,
                'site_url' => $siteUrl,
                'feed_type' => $feedType,
                'language' => 'en',
                'status' => 'online'
            ]);

            CacheInvalidator::invalidateFeeds();
            CacheInvalidator::invalidate('admin:feeds');

            return new JsonResponse([
                'success' => true,
                'message' => __('success.feed_created')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.feed_create') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateFeed(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }
        
        $body = $request->getBody()->getContents();
        $params = !empty($body) ? json_decode($body, true) : [];
        
        if ($params === null) {
            $params = (array)$request->getParsedBody();
        }

        $feed = DB::queryFirstRow("SELECT * FROM feeds WHERE id = %i", $id);
        if (!$feed) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.feed_not_found')
            ], 404);
        }

        $errors = [];
        $updateData = [];

        if (isset($params['title'])) {
            if (empty($params['title'])) {
                $errors['title'] = __('validation.title_not_empty');
            } else {
                $updateData['title'] = $params['title'];
            }
        }

        if (isset($params['feed_url'])) {
            if (empty($params['feed_url'])) {
                $errors['feed_url'] = __('validation.feed_url_not_empty');
            } elseif (!filter_var($params['feed_url'], FILTER_VALIDATE_URL)) {
                $errors['feed_url'] = __('validation.feed_url_valid');
            } else {
                $urlCheck = UrlValidator::validate($params['feed_url']);
                if (!$urlCheck['valid']) {
                    $errors['feed_url'] = $urlCheck['error'];
                } else {
                    $updateData['feed_url'] = $params['feed_url'];
                }
            }
        }

        if (isset($params['site_url'])) {
            if (empty($params['site_url'])) {
                $errors['site_url'] = __('validation.site_url_not_empty');
            } elseif (!filter_var($params['site_url'], FILTER_VALIDATE_URL)) {
                $errors['site_url'] = __('validation.site_url_valid');
            } else {
                $urlCheck = UrlValidator::validate($params['site_url']);
                if (!$urlCheck['valid']) {
                    $errors['site_url'] = $urlCheck['error'];
                } else {
                    $updateData['site_url'] = $params['site_url'];
                }
            }
        }

        if (isset($params['feed_type'])) {
            $updateData['feed_type'] = $params['feed_type'];
        }

        if (isset($updateData['site_url']) && $updateData['site_url'] !== $feed['site_url']) {
            $updateData['slug'] = FeedSlugService::generateForFeed($updateData['site_url'], $id);
        }

        if (isset($params['status'])) {
            $validStatuses = ['online', 'offline', 'paused', 'pending', 'rejected'];
            if (!in_array($params['status'], $validStatuses)) {
                $errors['status'] = __('validation.status_invalid');
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
                'message' => __('error.no_fields_update')
            ], 400);
        }

        try {
            DB::update('feeds', $updateData, 'id=%i', $id);

            CacheInvalidator::invalidateFeed($id);
            CacheInvalidator::invalidate('admin:feeds');

            return new JsonResponse([
                'success' => true,
                'message' => __('success.feed_updated')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.feed_update') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteFeed(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }

        $feed = DB::queryFirstRow("SELECT * FROM feeds WHERE id = %i", $id);
        if (!$feed) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.feed_not_found')
            ], 404);
        }

        try {
            DB::delete('feeds', 'id=%i', $id);

            CacheInvalidator::invalidate(['feeds', 'items']);
            CacheInvalidator::invalidate('admin:feeds');

            return new JsonResponse([
                'success' => true,
                'message' => __('success.feed_deleted')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.feed_delete') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateItem(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }
        
        $body = $request->getBody()->getContents();
        $params = !empty($body) ? json_decode($body, true) : [];
        
        if ($params === null) {
            $params = (array)$request->getParsedBody();
        }

        $item = DB::queryFirstRow("SELECT * FROM feed_items WHERE id = %i", $id);
        if (!$item) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.item_not_found')
            ], 404);
        }

        if (isset($params['is_visible'])) {
            $isVisible = (bool)$params['is_visible'];

            try {
                DB::update('feed_items', [
                    'is_visible' => $isVisible ? 1 : 0
                ], 'id=%i', $id);

                CacheInvalidator::invalidateItems();
                CacheInvalidator::invalidate('admin:items');

                return new JsonResponse([
                    'success' => true,
                    'message' => __('success.item_updated')
                ]);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'success' => false,
                    'message' => __('error.item_update') . ': ' . $e->getMessage()
                ], 500);
            }
        }
        
        if (isset($params['refresh_thumbnail']) && $params['refresh_thumbnail']) {
            try {
                $imageUrl = $item['image_url'] ?? '';
                
                if (empty($imageUrl)) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => __('admin.items.no_thumbnail')
                    ], 400);
                }
                
                $thumbnailService = new ThumbnailService();
                $thumbnailFilename = md5($imageUrl . 120 . 60) . '.jpg';
                $thumbnailPath = __DIR__ . '/../../public/storage/thumbnails/' . $thumbnailFilename;
                
                if (file_exists($thumbnailPath)) {
                    @unlink($thumbnailPath);
                }
                
                $newThumbnailUrl = $thumbnailService->getThumbnail($imageUrl);
                
                return new JsonResponse([
                    'success' => true,
                    'message' => __('admin.items.thumbnail_updated'),
                    'thumbnail_url' => $newThumbnailUrl
                ]);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'success' => false,
                    'message' => __('admin.items.thumbnail_error') . ': ' . $e->getMessage()
                ], 500);
            }
        }

        return new JsonResponse([
            'success' => false,
            'message' => __('error.no_fields_update')
        ], 400);
    }

    // Categories Management
    public function categories(ServerRequestInterface $request): ResponseInterface
    {
        $adminTtl = (int)($_ENV['CACHE_ADMIN_TTL'] ?? 60);

        $categories = CacheableQuery::query(
            'admin', 'categories', ['admin:categories'], $adminTtl,
            "SELECT c.*,
                   (SELECT COUNT(*) FROM feed_categories WHERE category_id = c.id) as feed_count
            FROM categories c
            ORDER BY c.name"
        );

        $html = $this->templates->render('admin/categories', [
            'categories' => $categories,
            'title' => __('admin.categories.title')
        ]);

        return new HtmlResponse($html);
    }

    public function newCategoryForm(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $csrfError = $this->validateCsrf($request);
            if ($csrfError !== null) {
                return $this->csrfErrorResponse($csrfError, true);
            }

            $params = (array)$request->getParsedBody();
            $name = trim($params['name'] ?? '');
            $slug = trim($params['slug'] ?? '');

            $errors = [];
            if (empty($name)) {
                $errors['name'] = __('validation.name_required');
            }
            
            if (empty($slug)) {
                $slug = $this->generateSlug($name);
            }

            // Check if slug already exists
            $existing = DB::queryFirstRow("SELECT id FROM categories WHERE slug = %s", $slug);
            if ($existing) {
                $errors['slug'] = __('validation.slug_exists');
            }

            if (empty($errors)) {
                try {
                    DB::insert('categories', [
                        'name' => $name,
                        'slug' => $slug
                    ]);

                    CacheInvalidator::invalidateCategories();
                    CacheInvalidator::invalidate('admin:categories');

                    return new RedirectResponse('/admin/categories');
                } catch (\Exception $e) {
                    $errors['general'] = __('error.category_create') . ': ' . $e->getMessage();
                }
            }

            $html = $this->templates->render('admin/category-form', [
                'title' => __('admin.categories.new'),
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
            'title' => __('admin.categories.new'),
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
            $csrfError = $this->validateCsrf($request);
            if ($csrfError !== null) {
                return $this->csrfErrorResponse($csrfError, true);
            }

            $params = (array)$request->getParsedBody();
            $name = trim($params['name'] ?? '');
            $slug = trim($params['slug'] ?? '');

            $errors = [];
            if (empty($name)) {
                $errors['name'] = __('validation.name_required');
            }

            if (empty($slug)) {
                $slug = $this->generateSlug($name);
            }

            // Check if slug already exists (excluding current category)
            $existing = DB::queryFirstRow("SELECT id FROM categories WHERE slug = %s AND id != %i", $slug, $id);
            if ($existing) {
                $errors['slug'] = __('validation.slug_exists');
            }

            if (empty($errors)) {
                try {
                    DB::update('categories', [
                        'name' => $name,
                        'slug' => $slug
                    ], 'id=%i', $id);

                    CacheInvalidator::invalidateCategories();
                    CacheInvalidator::invalidate('admin:categories');

                    return new RedirectResponse('/admin/categories');
                } catch (\Exception $e) {
                    $errors['general'] = __('error.category_update') . ': ' . $e->getMessage();
                }
            }

            $html = $this->templates->render('admin/category-form', [
                'title' => __('admin.category_form.edit_title'),
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
            'title' => __('admin.category_form.edit_title'),
            'isEdit' => true,
            'category' => $category,
            'errors' => []
        ]);

        return new HtmlResponse($html);
    }

    public function createCategory(ServerRequestInterface $request): ResponseInterface
    {
        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }

        $params = (array)$request->getParsedBody();
        $name = trim($params['name'] ?? '');
        $slug = trim($params['slug'] ?? '');

        $errors = [];
        if (empty($name)) {
            $errors['name'] = __('validation.name_required');
        }
        
        if (empty($slug)) {
            $slug = $this->generateSlug($name);
        }

        // Check if slug already exists
        $existing = DB::queryFirstRow("SELECT id FROM categories WHERE slug = %s", $slug);
        if ($existing) {
            $errors['slug'] = __('validation.slug_exists');
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

            CacheInvalidator::invalidateCategories();
            CacheInvalidator::invalidate('admin:categories');

            return new JsonResponse([
                'success' => true,
                'message' => __('success.category_created')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.category_create') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateCategory(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }

        $params = json_decode($request->getBody()->getContents(), true) ?: (array)$request->getParsedBody();

        $category = DB::queryFirstRow("SELECT * FROM categories WHERE id = %i", $id);
        if (!$category) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.category_not_found')
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
                    'errors' => ['slug' => __('validation.slug_exists')]
                ], 400);
            }
            $updateData['slug'] = $slug;
        }

        if (empty($updateData)) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.no_fields_update')
            ], 400);
        }

        try {
            DB::update('categories', $updateData, 'id=%i', $id);
            CacheInvalidator::invalidateCategories();
            CacheInvalidator::invalidate('admin:categories');

            return new JsonResponse([
                'success' => true,
                'message' => __('success.category_updated')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.category_update') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteCategory(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }

        $category = DB::queryFirstRow("SELECT * FROM categories WHERE id = %i", $id);
        if (!$category) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.category_not_found')
            ], 404);
        }

        try {
            DB::delete('categories', 'id=%i', $id);
            CacheInvalidator::invalidateCategories();
            CacheInvalidator::invalidate('admin:categories');

            return new JsonResponse([
                'success' => true,
                'message' => __('success.category_deleted')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.category_delete') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    // Tags Management
    public function tags(ServerRequestInterface $request): ResponseInterface
    {
        $adminTtl = (int)($_ENV['CACHE_ADMIN_TTL'] ?? 60);

        $tags = CacheableQuery::query(
            'admin', 'tags', ['admin:tags'], $adminTtl,
            "SELECT t.*,
                   (SELECT COUNT(*) FROM feed_tags WHERE tag_id = t.id) as feed_count
            FROM tags t
            ORDER BY t.name"
        );

        $html = $this->templates->render('admin/tags', [
            'tags' => $tags,
            'title' => __('admin.tags.title')
        ]);

        return new HtmlResponse($html);
    }

    public function newTagForm(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $csrfError = $this->validateCsrf($request);
            if ($csrfError !== null) {
                return $this->csrfErrorResponse($csrfError, true);
            }

            $params = (array)$request->getParsedBody();
            $name = trim($params['name'] ?? '');
            $slug = trim($params['slug'] ?? '');

            $errors = [];
            if (empty($name)) {
                $errors['name'] = __('validation.name_required');
            }
            
            if (empty($slug)) {
                $slug = $this->generateSlug($name);
            }

            // Check if slug already exists
            $existing = DB::queryFirstRow("SELECT id FROM tags WHERE slug = %s", $slug);
            if ($existing) {
                $errors['slug'] = __('validation.slug_exists');
            }

            if (empty($errors)) {
                try {
                    DB::insert('tags', [
                        'name' => $name,
                        'slug' => $slug
                    ]);

                    CacheInvalidator::invalidateTags();
                    CacheInvalidator::invalidate('admin:tags');

                    return new RedirectResponse('/admin/tags');
                } catch (\Exception $e) {
                    $errors['general'] = __('error.tag_create') . ': ' . $e->getMessage();
                }
            }

            $html = $this->templates->render('admin/tag-form', [
                'title' => __('admin.tags.new'),
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
            'title' => __('admin.tags.new'),
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
            $csrfError = $this->validateCsrf($request);
            if ($csrfError !== null) {
                return $this->csrfErrorResponse($csrfError, true);
            }

            $params = (array)$request->getParsedBody();
            $name = trim($params['name'] ?? '');
            $slug = trim($params['slug'] ?? '');

            $errors = [];
            if (empty($name)) {
                $errors['name'] = __('validation.name_required');
            }

            if (empty($slug)) {
                $slug = $this->generateSlug($name);
            }

            // Check if slug already exists (excluding current tag)
            $existing = DB::queryFirstRow("SELECT id FROM tags WHERE slug = %s AND id != %i", $slug, $id);
            if ($existing) {
                $errors['slug'] = __('validation.slug_exists');
            }

            if (empty($errors)) {
                try {
                    DB::update('tags', [
                        'name' => $name,
                        'slug' => $slug
                    ], 'id=%i', $id);

                    CacheInvalidator::invalidateTags();
                    CacheInvalidator::invalidate('admin:tags');

                    return new RedirectResponse('/admin/tags');
                } catch (\Exception $e) {
                    $errors['general'] = __('error.tag_update') . ': ' . $e->getMessage();
                }
            }

            $html = $this->templates->render('admin/tag-form', [
                'title' => __('admin.tag_form.edit_title'),
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
            'title' => __('admin.tag_form.edit_title'),
            'isEdit' => true,
            'tag' => $tag,
            'errors' => []
        ]);

        return new HtmlResponse($html);
    }

    public function createTag(ServerRequestInterface $request): ResponseInterface
    {
        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }

        $params = (array)$request->getParsedBody();
        $name = trim($params['name'] ?? '');
        $slug = trim($params['slug'] ?? '');

        $errors = [];
        if (empty($name)) {
            $errors['name'] = __('validation.name_required');
        }
        
        if (empty($slug)) {
            $slug = $this->generateSlug($name);
        }

        // Check if slug already exists
        $existing = DB::queryFirstRow("SELECT id FROM tags WHERE slug = %s", $slug);
        if ($existing) {
            $errors['slug'] = __('validation.slug_exists');
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

            CacheInvalidator::invalidateTags();
                    CacheInvalidator::invalidate('admin:tags');

            return new JsonResponse([
                'success' => true,
                'message' => __('success.tag_created')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.tag_create') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateTag(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }

        $params = json_decode($request->getBody()->getContents(), true) ?: (array)$request->getParsedBody();

        $tag = DB::queryFirstRow("SELECT * FROM tags WHERE id = %i", $id);
        if (!$tag) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.tag_not_found')
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
                    'errors' => ['slug' => __('validation.slug_exists')]
                ], 400);
            }
            $updateData['slug'] = $slug;
        }

        if (empty($updateData)) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.no_fields_update')
            ], 400);
        }

        try {
            DB::update('tags', $updateData, 'id=%i', $id);
            CacheInvalidator::invalidateTags();
                    CacheInvalidator::invalidate('admin:tags');

            return new JsonResponse([
                'success' => true,
                'message' => __('success.tag_updated')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.tag_update') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteTag(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }

        $tag = DB::queryFirstRow("SELECT * FROM tags WHERE id = %i", $id);
        if (!$tag) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.tag_not_found')
            ], 404);
        }

        try {
            DB::delete('tags', 'id=%i', $id);

            CacheInvalidator::invalidateTags();
                    CacheInvalidator::invalidate('admin:tags');

            return new JsonResponse([
                'success' => true,
                'message' => __('success.tag_deleted')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.tag_delete') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkUpdateFeedCategories(ServerRequestInterface $request): ResponseInterface
    {
        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }

        $params = json_decode($request->getBody()->getContents(), true) ?: (array)$request->getParsedBody();

        $feedIds = $params['feed_ids'] ?? [];
        $categoryIds = $params['category_ids'] ?? [];

        if (empty($feedIds) || !is_array($feedIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.no_feed_selected')
            ], 400);
        }

        if (empty($categoryIds) || !is_array($categoryIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.no_category_selected')
            ], 400);
        }

        try {
            $feedIds = array_map('intval', $feedIds);
            $categoryIds = array_map('intval', $categoryIds);

            // Bulk remove existing category associations for selected feeds.
            DB::query("DELETE FROM feed_categories WHERE feed_id IN %li", $feedIds);

            // Build and insert all (feed_id, category_id) pairs in batches.
            $rows = [];
            foreach ($feedIds as $feedId) {
                foreach ($categoryIds as $categoryId) {
                    $rows[] = [
                        'feed_id' => $feedId,
                        'category_id' => $categoryId,
                    ];
                }
            }
            BulkInserter::insert('feed_categories', $rows, ['ignore' => true, 'batchSize' => 500]);

            // Triggers automatically recalculate item counts for affected categories.
            CacheInvalidator::invalidate(['categories', 'feeds']);
            CacheInvalidator::invalidate(['admin:categories', 'admin:feeds']);

            return new JsonResponse([
                'success' => true,
                'message' => __('success.categories_updated') . ' ' . count($feedIds) . ' ' . __('success.feeds')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.categories_update') . ': ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function bulkUpdateFeedTags(ServerRequestInterface $request): ResponseInterface
    {
        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }

        $params = json_decode($request->getBody()->getContents(), true) ?: (array)$request->getParsedBody();

        $feedIds = $params['feed_ids'] ?? [];
        $tagIds = $params['tag_ids'] ?? [];

        if (empty($feedIds) || !is_array($feedIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.no_feed_selected')
            ], 400);
        }

        if (empty($tagIds) || !is_array($tagIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.no_tag_selected')
            ], 400);
        }

        try {
            $feedIds = array_map('intval', $feedIds);
            $tagIds = array_map('intval', $tagIds);

            // Bulk remove existing tag associations for selected feeds.
            DB::query("DELETE FROM feed_tags WHERE feed_id IN %li", $feedIds);

            // Build and insert all (feed_id, tag_id) pairs in batches.
            $rows = [];
            foreach ($feedIds as $feedId) {
                foreach ($tagIds as $tagId) {
                    $rows[] = [
                        'feed_id' => $feedId,
                        'tag_id' => $tagId,
                    ];
                }
            }
            BulkInserter::insert('feed_tags', $rows, ['ignore' => true, 'batchSize' => 500]);

            // Triggers automatically recalculate item counts for affected tags.
            CacheInvalidator::invalidate(['tags', 'feeds']);
            CacheInvalidator::invalidate(['admin:tags', 'admin:feeds']);

            return new JsonResponse([
                'success' => true,
                'message' => __('success.tags_updated') . ' ' . count($feedIds) . ' ' . __('success.feeds')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.tags_update') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkUpdateFeedStatus(ServerRequestInterface $request): ResponseInterface
    {
        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }

        $params = json_decode($request->getBody()->getContents(), true) ?: (array)$request->getParsedBody();

        $feedIds = $params['feed_ids'] ?? [];
        $status = $params['status'] ?? '';

        if (empty($feedIds) || !is_array($feedIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.no_feed_selected')
            ], 400);
        }

        $validStatuses = ['online', 'offline', 'paused', 'pending', 'rejected'];
        if (!in_array($status, $validStatuses)) {
            return new JsonResponse([
                'success' => false,
                'message' => __('validation.status_invalid')
            ], 400);
        }

        try {
            // Single UPDATE for all selected feeds.
            DB::query("UPDATE feeds SET status = %s WHERE id IN %li", $status, array_map('intval', $feedIds));

            CacheInvalidator::invalidateFeeds();
            CacheInvalidator::invalidate('admin:feeds');

            return new JsonResponse([
                'success' => true,
                'message' => __('success.status_updated') . ' ' . count($feedIds) . ' ' . __('success.feeds')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.status_update') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggleShuffle(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $id = (int)$args['id'];

        $csrfError = $this->validateCsrf($request);
        if ($csrfError !== null) {
            return $this->csrfErrorResponse($csrfError);
        }
        
        $feed = DB::queryFirstRow("SELECT * FROM feeds WHERE id = %i", $id);
        if (!$feed) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.feed_not_found')
            ], 404);
        }
        
        $newShuffle = (int)(!($feed['shuffle'] ?? 1));
        
        try {
            DB::update('feeds', [
                'shuffle' => $newShuffle
            ], 'id=%i', $id);
            
            CacheInvalidator::invalidateFeed($id);
            CacheInvalidator::invalidate('admin:feeds');

            return new JsonResponse([
                'success' => true,
                'message' => __('success.shuffle_updated'),
                'shuffle' => $newShuffle
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => __('error.shuffle_update') . ': ' . $e->getMessage()
            ], 500);
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
