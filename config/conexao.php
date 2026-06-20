<?php

// Carrega variáveis do .env, caso exista
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        if ($name !== '') {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

$host = getenv('DB_HOST') ?: 'dpg-d8rghsho3t8c73d4mn70-a.oregon-postgres.render.com';
$port = getenv('DB_PORT') ?: '5432';
$db   = getenv('DB_DATABASE') ?: 'projeto_breno_bihm';
$user = getenv('DB_USERNAME') ?: 'projeto_breno_bihm_user';
$pass = getenv('DB_PASSWORD') ?: 'zppaaBNuNqjsurHOqGZfCskGFTIu6345';
$sslmode = getenv('DB_SSLMODE') ?: 'require';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=$sslmode;connect_timeout=5";

    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET search_path TO public");
    $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");

    // Se quiser testar se deu certo, descomente a linha abaixo:
    // echo "Conectado com sucesso na base de dados!";

} catch (PDOException $e) {
    die("Erro crítico na conexão com PostgreSQL: " . $e->getMessage());
}

?>
