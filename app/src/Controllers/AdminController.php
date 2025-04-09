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
        $feeds = DB::query("
            SELECT f.*, 
                  (SELECT COUNT(*) FROM feed_items WHERE feed_id = f.id) as item_count,
                  (SELECT MAX(published_at) FROM feed_items WHERE feed_id = f.id) as latest_item_date
            FROM feeds f
            ORDER BY f.title
        ");

        $html = $this->templates->render('admin/feeds', [
            'feeds' => $feeds,
            'title' => 'Gerenciar Feeds'
        ]);

        return new HtmlResponse($html);
    }

    public function newFeedForm(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $params = (array)$request->getParsedBody();
            $title = $params['title'] ?? '';
            $feedUrl = $params['feed_url'] ?? '';
            $siteUrl = $params['site_url'] ?? '';
            $language = $params['language'] ?? 'en';
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

            if (!in_array($language, ['en', 'pt-BR', 'es'])) {
                $errors['language'] = 'Idioma selecionado inválido';
            } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
                $errors['site_url'] = 'A URL do site deve ser uma URL válida';
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

                    DB::insert('feeds', [
                        'title' => $title,
                        'feed_url' => $feedUrl,
                        'site_url' => $siteUrl,
                        'feed_type' => $feedType,
                        'language' => $language,
                        'status' => 'online'
                    ]);

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
                'errors' => $errors
            ]);

            return new HtmlResponse($html);
        }

        $html = $this->templates->render('admin/feed-form', [
            'title' => 'Adicionar Novo Feed',
            'isEdit' => false,
            'feed' => null,
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

        if ($request->getMethod() === 'POST') {
            $params = (array)$request->getParsedBody();
            $title = $params['title'] ?? '';
            $feedUrl = $params['feed_url'] ?? '';
            $siteUrl = $params['site_url'] ?? '';
            $feedType = $params['feed_type'] ?? '';
            $language = $params['language'] ?? $feed['language'];
            $status = $params['status'] ?? $feed['status'];

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
            } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
                $errors['site_url'] = 'A URL do site deve ser uma URL válida';
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
                'errors' => $errors
            ]);

            return new HtmlResponse($html);
        }

        $html = $this->templates->render('admin/feed-form', [
            'title' => 'Editar Feed',
            'isEdit' => true,
            'feed' => $feed,
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
        $params = (array)$request->getParsedBody();

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
            $updateData['status'] = $params['status'];
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
        $params = (array)$request->getParsedBody();

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
}
