<?php
declare(strict_types=1);

final class Conexao
{
    private ?PDO $pdo = null;

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function conectar(): void
    {
        $host = getenv('LINETIME_DB_HOST') ?: '';
        $name = getenv('LINETIME_DB_NAME') ?: 'linetime';
        $user = getenv('LINETIME_DB_USER') ?: '';
        $pass = getenv('LINETIME_DB_PASSWORD');

        if ($host === '' || $user === '' || $pass === false) {
            throw new RuntimeException('Configure LINETIME_DB_HOST, LINETIME_DB_NAME, LINETIME_DB_USER e LINETIME_DB_PASSWORD no servidor.');
        }

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
        $this->pdo = new PDO($dsn, $user, (string)$pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false
        ]);
    }
}
