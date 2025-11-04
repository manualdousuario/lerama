<?php

namespace App\Services {
    class Translator
    {
        private static $instance = null;
        private $translations = [];
        private $language = 'pt-BR';
        private $fallbackLanguage = 'pt-BR';

        private function __construct()
        {
            $this->language = $_ENV['APP_LANGUAGE'] ?? 'pt-BR';
            $this->loadTranslations($this->language);
            
            // Load fallback if different from current
            if ($this->language !== $this->fallbackLanguage) {
                $this->loadTranslations($this->fallbackLanguage, true);
            }
        }

        public static function getInstance(): self
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function loadTranslations(string $language, bool $isFallback = false): void
        {
            $langFile = __DIR__ . '/../../lang/' . $language . '.php';
            
            if (file_exists($langFile)) {
                $translations = require $langFile;
                
                if ($isFallback) {
                    // Merge with existing, keeping the main language values
                    $this->translations = array_merge($translations, $this->translations);
                } else {
                    $this->translations = $translations;
                }
            }
        }

        public function translate(string $key, array $replacements = []): string
        {
            $translation = $this->translations[$key] ?? $key;
            
            // Replace placeholders like :name, :count, etc.
            foreach ($replacements as $placeholder => $value) {
                $translation = str_replace(':' . $placeholder, $value, $translation);
            }
            
            return $translation;
        }

        public function getLanguage(): string
        {
            return $this->language;
        }

        public function getAvailableLanguages(): array
        {
            return [
                'pt-BR' => 'Português (Brasil)',
                'en' => 'English',
                'es' => 'Español'
            ];
        }
    }
}

namespace {
    // Helper functions in global namespace
    if (!function_exists('__')) {
        function __(string $key, array $replacements = []): string
        {
            return \App\Services\Translator::getInstance()->translate($key, $replacements);
        }
    }

    if (!function_exists('current_language')) {
        function current_language(): string
        {
            return \App\Services\Translator::getInstance()->getLanguage();
        }
    }
}