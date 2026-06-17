<?php
declare(strict_types=1);

namespace Lerama\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private PHPMailer $mailer;
    private bool $enabled;
    private string $adminEmail;

    public function __construct()
    {
        $this->enabled = !empty($_ENV['SMTP_HOST']) && !empty($_ENV['SMTP_PORT']);
        $this->adminEmail = $_ENV['ADMIN_EMAIL'] ?? '';
        
        if ($this->enabled) {
            $this->mailer = new PHPMailer(true);
            
            $this->mailer->isSMTP();
            $this->mailer->Host = $_ENV['SMTP_HOST'];
            $this->mailer->Port = (int)$_ENV['SMTP_PORT'];
            $this->mailer->SMTPAuth = !empty($_ENV['SMTP_USERNAME']) && !empty($_ENV['SMTP_PASSWORD']);
            
            if ($this->mailer->SMTPAuth) {
                $this->mailer->Username = $_ENV['SMTP_USERNAME'];
                $this->mailer->Password = $_ENV['SMTP_PASSWORD'];
            }
            
            $this->mailer->SMTPSecure = $_ENV['SMTP_SECURE'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $this->mailer->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
        }
    }

    public function sendFeedOfflineNotification(array $feed): bool
    {
        if (!$this->enabled || empty($this->adminEmail)) {
            error_log("Feed marked as offline (email not sent - SMTP disabled or ADMIN_EMAIL not set): {$feed['title']} ({$feed['feed_url']})");
            return false;
        }
        
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->clearCustomHeaders();
            $this->mailer->addAddress($this->adminEmail);
            $this->mailer->Subject = "[Lerama] Feed offline: " . $this->e($feed['title'] ?? '');

            $title = $this->e($feed['title'] ?? '');
            $feedUrl = $this->e($feed['feed_url'] ?? '');
            $feedType = $this->e($feed['feed_type'] ?? '');
            $lastChecked = $this->e($feed['last_checked'] ?? '');
            $pausedAt = $this->e($feed['paused_at'] ?? '');

            $body = "<h1>Feed marcado como offline</h1>";
            $body .= "<h2>Detalhes do feed</h2>";
            $body .= "<ul>";
            $body .= "<li><strong>Título:</strong> {$title}</li>";
            $body .= "<li><strong>URL:</strong> {$feedUrl}</li>";
            $body .= "<li><strong>Tipo:</strong> {$feedType}</li>";
            $body .= "<li><strong>Última verificação:</strong> {$lastChecked}</li>";
            $body .= "</ul>";
            $body .= "<p>O feed foi pausado inicialmente em {$pausedAt} e está inacessível há mais de 72 horas.</p>";
            
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags(str_replace(['<li>', '</li>'], ["\n- ", ''], $body));
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Error sending feed offline notification: " . $e->getMessage());
            return false;
        }
    }

    public function sendFeedRegistrationNotification(array $feed): bool
    {
        if (!$this->enabled || empty($this->adminEmail)) {
            error_log("New feed registered (email not sent - SMTP disabled or ADMIN_EMAIL not set): {$feed['title']} ({$feed['feed_url']})");
            return false;
        }
        
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->clearCustomHeaders();
            $this->mailer->addAddress($this->adminEmail);
            $this->mailer->Subject = "[Lerama] Novo feed registrado: " . $this->e($feed['title'] ?? '');

            $title = $this->e($feed['title'] ?? '');
            $feedUrl = $this->e($feed['feed_url'] ?? '');
            $siteUrl = $this->e($feed['site_url'] ?? '');
            $feedType = $this->e($feed['feed_type'] ?? '');
            $language = $this->e($feed['language'] ?? '');
            $status = $this->e($feed['status'] ?? '');

            $body = "<h1>Novo feed registrado</h1>";
            $body .= "<h2>Detalhes do feed</h2>";
            $body .= "<ul>";
            $body .= "<li><strong>Título:</strong> {$title}</li>";
            $body .= "<li><strong>URL do Feed:</strong> <a href=\"{$feedUrl}\">{$feedUrl}</a></li>";
            $body .= "<li><strong>URL do Site:</strong> <a href=\"{$siteUrl}\">{$siteUrl}</a></li>";
            $body .= "<li><strong>Tipo:</strong> {$feedType}</li>";
            $body .= "<li><strong>Idioma:</strong> {$language}</li>";
            $body .= "<li><strong>Status:</strong> {$status}</li>";
            $body .= "<li><strong>Data de registro:</strong> " . date('d/m/Y H:i:s') . "</li>";
            $body .= "</ul>";
            
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags(str_replace(['<li>', '</li>'], ["\n- ", ''], $body));
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Error sending feed registration notification: " . $e->getMessage());
            return false;
        }
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}