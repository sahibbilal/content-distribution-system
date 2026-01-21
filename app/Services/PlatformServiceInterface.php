<?php

namespace App\Services;

interface PlatformServiceInterface
{
    public function publish(string $content, array $credentials, array $media = []): array;
    public function validateCredentials(array $credentials): bool;
    public function testConnection(array $credentials): array;
}

