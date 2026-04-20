<?php

namespace SC_AI\ContentGenerator\Services\API;

defined( 'ABSPATH' ) || exit;

interface ApiProviderInterface {
    public function generate( string $prompt ): string|false;
    public function testConnection(): array;
    public function getName(): string;
    public function getRateLimit(): int;
    public function isEnabled(): bool;
    public function getQuota(): array;
}
