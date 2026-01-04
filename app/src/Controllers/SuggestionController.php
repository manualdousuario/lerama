<?php

declare(strict_types=1);

namespace Lerama\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use League\Plates\Engine;
use Lerama\Services\FeedTypeDetector;
use Lerama\Services\EmailService;
use Gregwar\Captcha\CaptchaBuilder;
use DB;

class SuggestionController
{
    private Engine $templates;

    public function __construct()
    {
        $this->templates = new Engine(__DIR__ . '/../../templates');
    }

    public function suggestForm(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $categories = DB::query("SELECT * FROM categories ORDER BY name");
        $tags = DB::query("SELECT * FROM tags ORDER BY name");

        $html = $this->templates->render('suggest-feed', [
            'title' => 'Sugerir Blog/Feed',
            'categories' => $categories,
            'tags' => $tags
        ]);

        return new HtmlResponse($html);
    }

    public function getCaptcha(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $builder = new CaptchaBuilder();
        $builder->build();

        $_SESSION['captcha_phrase'] = $builder->getPhrase();

        header('Content-Type: image/jpeg');
        $builder->output();
        exit;
    }

    public function submitSuggestion(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $params = (array)$request->getParsedBody();
        $title = trim($params['title'] ?? '');
        $feedUrl = trim($params['feed_url'] ?? '');
        $siteUrl = trim($params['site_url'] ?? '');
        $language = trim($params['language'] ?? 'en');
        $email = trim($params['email'] ?? '');
        $captcha = trim($params['captcha'] ?? '');
        $categoryId = !empty($params['category']) ? (int)$params['category'] : null;
        $tagIds = $params['tags'] ?? [];

        $errors = [];
        
        if (empty($captcha)) {
            $errors['captcha'] = 'O código de verificação é obrigatório';
        } elseif (!isset($_SESSION['captcha_phrase']) || $captcha !== $_SESSION['captcha_phrase']) {
            $errors['captcha'] = 'Código de verificação inválido';
        }
        
        unset($_SESSION['captcha_phrase']);
        
        if (empty($title)) {
            $errors['title'] = 'O título do site é obrigatório';
        } elseif (strlen($title) < 3) {
            $errors['title'] = 'O título deve ter pelo menos 3 caracteres';
        }

        if (empty($feedUrl)) {
            $errors['feed_url'] = 'A URL do feed é obrigatória';
        } elseif (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            $errors['feed_url'] = 'A URL do feed deve ser uma URL válida';
        } else {
            $feedValidation = $this->validateFeed($feedUrl);
            if (!$feedValidation['valid']) {
                $errors['feed_url'] = $feedValidation['error'];
            }
        }

        if (empty($siteUrl)) {
            $errors['site_url'] = 'A URL do blog é obrigatória';
        } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            $errors['site_url'] = 'A URL do blog deve ser uma URL válida';
        }

        $validLanguages = ['en', 'pt-BR', 'es'];
        if (!in_array($language, $validLanguages)) {
            $errors['language'] = 'Idioma inválido';
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email inválido';
        }

        $availableCategories = DB::query("SELECT * FROM categories ORDER BY name");
        if (!empty($availableCategories) && empty($categoryId)) {
            $errors['category'] = 'A categoria é obrigatória';
        }

        $existingFeed = DB::queryFirstRow("SELECT id, status FROM feeds WHERE feed_url = %s", $feedUrl);
        if ($existingFeed) {
            if ($existingFeed['status'] == 'pending') {
                $errors['feed_url'] = 'Este feed já foi sugerido e está aguardando aprovação';
            } else {
                $errors['feed_url'] = 'Este feed já está cadastrado';
            }
        }

        if (!empty($errors)) {
            if ($request->getHeaderLine('Accept') === 'application/json' ||
                $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                return new JsonResponse([
                    'success' => false,
                    'errors' => $errors
                ], 400);
            }

            $categories = DB::query("SELECT * FROM categories ORDER BY name");
            $tags = DB::query("SELECT * FROM tags ORDER BY name");

            $html = $this->templates->render('suggest-feed', [
                'title' => 'Sugerir Blog/Feed',
                'categories' => $categories,
                'tags' => $tags,
                'errors' => $errors,
                'data' => [
                    'title' => $title,
                    'feed_url' => $feedUrl,
                    'site_url' => $siteUrl,
                    'language' => $language,
                    'email' => $email,
                    'selected_category' => $categoryId,
                    'selected_tags' => $tagIds
                ]
            ]);

            return new HtmlResponse($html);
        }

        try {
            // Detect feed type
            $feedType = null;
            if (isset($feedValidation['type'])) {
                $feedType = $feedValidation['type'];
            }

            $feedId = DB::insert('feeds', [
                'title' => $title,
                'feed_url' => $feedUrl,
                'site_url' => $siteUrl,
                'language' => $language,
                'feed_type' => $feedType,
                'submitter_email' => !empty($email) ? $email : null,
                'status' => 'pending'
            ]);

            if ($categoryId !== null) {
                DB::query("INSERT IGNORE INTO feed_categories (feed_id, category_id) VALUES (%i, %i)",
                    $feedId, $categoryId);
            }

            // Insert tags
            if (!empty($tagIds)) {
                foreach ($tagIds as $tagId) {
                    DB::query("INSERT IGNORE INTO feed_tags (feed_id, tag_id) VALUES (%i, %i)",
                        $feedId, (int)$tagId);
                }
            }

            $emailService = new EmailService();
            $emailService->sendFeedRegistrationNotification([
                'title' => $title,
                'feed_url' => $feedUrl,
                'site_url' => $siteUrl,
                'feed_type' => $feedType,
                'language' => $language,
                'status' => 'pending'
            ]);

            if ($request->getHeaderLine('Accept') === 'application/json' ||
                $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Sugestão enviada com sucesso! Aguarde a aprovação do administrador.'
                ]);
            }

            $html = $this->templates->render('suggest-feed', [
                'title' => 'Sugerir Blog/Feed',
                'success' => 'Sugestão enviada com sucesso! Aguarde a aprovação do administrador.'
            ]);

            return new HtmlResponse($html);
        } catch (\Exception $e) {
            if ($request->getHeaderLine('Accept') === 'application/json' || 
                $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erro ao enviar sugestão: ' . $e->getMessage()
                ], 500);
            }

            $categories = DB::query("SELECT * FROM categories ORDER BY name");
            $tags = DB::query("SELECT * FROM tags ORDER BY name");

            $html = $this->templates->render('suggest-feed', [
                'title' => 'Sugerir Blog/Feed',
                'categories' => $categories,
                'tags' => $tags,
                'errors' => ['general' => 'Erro ao enviar sugestão: ' . $e->getMessage()],
                'data' => [
                    'title' => $title,
                    'feed_url' => $feedUrl,
                    'site_url' => $siteUrl,
                    'language' => $language,
                    'email' => $email,
                    'selected_category' => $categoryId,
                    'selected_tags' => $tagIds
                ]
            ]);

            return new HtmlResponse($html);
        }
    }

    private function validateFeed(string $feedUrl): array
    {
        try {
            $detector = new FeedTypeDetector();
            $feedType = $detector->detectType($feedUrl);

            if (!$feedType) {
                return [
                    'valid' => false,
                    'error' => 'Não foi possível validar o feed. Verifique se a URL está correta e se o feed está acessível.'
                ];
            }

            return [
                'valid' => true,
                'type' => $feedType
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'Feed inválido ou inacessível: ' . $e->getMessage()
            ];
        }
    }
}