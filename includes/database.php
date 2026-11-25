
<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "Jaboo";
$porta = 3306;

try {

    $conexao = new mysqli(
        $host,
        $usuario,
        $senha,
        $banco,
        $porta
    );

    $conexao->set_charset("utf8mb4");

} catch (mysqli_sql_exception $erro) {

    error_log("Erro de conexão JABOO: " . $erro->getMessage());

    http_response_code(500);

    exit("Não foi possível conectar ao banco de dados.");

}
