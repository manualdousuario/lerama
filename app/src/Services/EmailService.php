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
        $this->adminEmail = $_ENV['ADMIN_EMAIL'];
        
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
            $this->mailer->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
        }
    }

    public function sendFeedOfflineNotification(array $feed): bool
    {
        if (!$this->enabled) {
            error_log("Feed marked as offline: {$feed['title']} ({$feed['feed_url']})");
            return false;
        }
        
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($this->adminEmail);
            $this->mailer->Subject = "Lerama: Feed offline: {$feed['title']}";

            $body = "<h1>Feed marcado como offline</h1>";
            $body .= "<h2>Detalhes do feed</h2>";
            $body .= "<ul>";
            $body .= "<li><strong>Título:</strong> {$feed['title']}</li>";
            $body .= "<li><strong>URL:</strong> {$feed['feed_url']}</li>";
            $body .= "<li><strong>Tipo:</strong> {$feed['feed_type']}</li>";
            $body .= "<li><strong>Última verificação:</strong> {$feed['last_checked']}</li>";
            $body .= "</ul>";
            $body .= "<p>O feed foi pausado inicialmente em {$feed['paused_at']} e está inacessível há mais de 72 horas.</p>";
            
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags(str_replace(['<li>', '</li>'], ["\n- ", ''], $body));
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Error sending feed offline notification: " . $e->getMessage());
            return false;
        }
    }
}