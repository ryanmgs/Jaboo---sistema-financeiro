
<?php

require_once __DIR__ . "/includes/auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);
    exit("Método não permitido.");

}

jaboo_validar_csrf();

$_SESSION = [];

if (ini_get("session.use_cookies")) {

    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires" => time() - 42000,
            "path" => $parametros["path"],
            "domain" => $parametros["domain"],
            "secure" => $parametros["secure"],
            "httponly" => $parametros["httponly"],
            "samesite" => $parametros["samesite"]
        ]
    );

}

session_destroy();

header("Location: login.php");

exit;
