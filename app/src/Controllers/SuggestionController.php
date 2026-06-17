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
use Lerama\Services\CacheableQuery;
use Lerama\Services\UrlValidator;
use Lerama\Services\FeedSlugService;
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

        $categories = CacheableQuery::query(
            'categories', 'all', ['categories'], 300,
            "SELECT * FROM categories ORDER BY name"
        );
        $tags = CacheableQuery::query(
            'tags', 'all', ['tags'], 300,
            "SELECT * FROM tags ORDER BY name"
        );

        $html = $this->templates->render('suggest-feed', [
            'title' => __('suggest.heading'),
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
        if (!is_array($tagIds)) {
            $tagIds = !empty($tagIds) ? [$tagIds] : [];
        }
        $tagIds = array_map('intval', array_filter($tagIds));

        $errors = [];
        
        if (empty($captcha)) {
            $errors['captcha'] = __('validation.captcha_required');
        } elseif (!isset($_SESSION['captcha_phrase']) || $captcha !== $_SESSION['captcha_phrase']) {
            $errors['captcha'] = __('validation.captcha_invalid');
        }
        
        unset($_SESSION['captcha_phrase']);
        
        if (empty($title)) {
            $errors['title'] = __('validation.title_required');
        } elseif (strlen($title) < 3) {
            $errors['title'] = __('validation.title_min_length');
        }

        if (empty($feedUrl)) {
            $errors['feed_url'] = __('validation.feed_url_required');
        } elseif (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            $errors['feed_url'] = __('validation.feed_url_valid');
        } else {
            $urlCheck = UrlValidator::validate($feedUrl);
            if (!$urlCheck['valid']) {
                $errors['feed_url'] = $urlCheck['error'];
            } else {
                $feedValidation = $this->validateFeed($feedUrl);
                if (!$feedValidation['valid']) {
                    $errors['feed_url'] = $feedValidation['error'];
                }
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

        $validLanguages = ['en', 'pt-BR', 'es'];
        if (!in_array($language, $validLanguages)) {
            $errors['language'] = __('validation.language_invalid');
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('validation.email_invalid');
        }

        $availableCategories = CacheableQuery::query(
            'categories', 'all', ['categories'], 300,
            "SELECT * FROM categories ORDER BY name"
        );
        if (!empty($availableCategories) && empty($categoryId)) {
            $errors['category'] = __('validation.category_required');
        }

        $availableTags = CacheableQuery::query(
            'tags', 'all', ['tags'], 300,
            "SELECT * FROM tags ORDER BY name"
        );
        if (!empty($availableTags) && empty($tagIds)) {
            $errors['tags'] = __('validation.tag_required');
        }

        $existingFeed = DB::queryFirstRow("SELECT id, status FROM feeds WHERE feed_url = %s", $feedUrl);
        if ($existingFeed) {
            if ($existingFeed['status'] == 'pending') {
                $errors['feed_url'] = __('feed.already_pending');
            } else {
                $errors['feed_url'] = __('feed.already_registered');
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
                'title' => __('suggest.heading'),
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

            DB::insert('feeds', [
                'title' => $title,
                'feed_url' => $feedUrl,
                'site_url' => $siteUrl,
                'slug' => FeedSlugService::generateForFeed($siteUrl),
                'language' => $language,
                'feed_type' => $feedType,
                'submitter_email' => !empty($email) ? $email : null,
                'status' => 'pending'
            ]);
            $feedId = DB::insertId();

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
                    'message' => __('success.suggestion_sent')
                ]);
            }

            $categories = DB::query("SELECT * FROM categories ORDER BY name");
            $tags = DB::query("SELECT * FROM tags ORDER BY name");

            $html = $this->templates->render('suggest-feed', [
                'title' => __('suggest.heading'),
                'categories' => $categories,
                'tags' => $tags,
                'success' => __('success.suggestion_sent')
            ]);

            return new HtmlResponse($html);
        } catch (\Exception $e) {
            if ($request->getHeaderLine('Accept') === 'application/json' || 
                $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                return new JsonResponse([
                    'success' => false,
                    'message' => __('error.suggestion_send') . ': ' . $e->getMessage()
                ], 500);
            }

            $categories = DB::query("SELECT * FROM categories ORDER BY name");
            $tags = DB::query("SELECT * FROM tags ORDER BY name");

            $html = $this->templates->render('suggest-feed', [
                'title' => __('suggest.heading'),
                'categories' => $categories,
                'tags' => $tags,
                'errors' => ['general' => __('error.suggestion_send') . ': ' . $e->getMessage()],
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
                    'error' => __('error.feed_validate')
                ];
            }

            return [
                'valid' => true,
                'type' => $feedType
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => __('error.feed_invalid') . ': ' . $e->getMessage()
            ];
        }
    }
}