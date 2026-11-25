
<?php

// sessão do BAGUI

if (session_status() !== PHP_SESSION_ACTIVE) {

    ini_set("session.use_strict_mode", "1");

    $https = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $https,
        "httponly" => true,
        "samesite" => "Lax"
    ]);

    session_start();

}

//proteção de textos html

function jaboo_escape($valor): string {

    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        "UTF-8"
    );

}

// tokens de segurança

function jaboo_csrf_token(): string {

    if (empty($_SESSION["csrf_token"])) {

        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

    }

    return $_SESSION["csrf_token"];

}

function jaboo_validar_csrf(): void {

    $tokenPost = $_POST["csrf_token"] ?? "";
    $tokenSessao = $_SESSION["csrf_token"] ?? "";

    if (
        !is_string($tokenPost) ||
        !is_string($tokenSessao) ||
        $tokenSessao === "" ||
        !hash_equals($tokenSessao, $tokenPost)
    ) {

        http_response_code(403);

        exit("Solicitação inválida. Atualize a página e tente novamente.");

    }

}

//verificar login

function jaboo_usuario_logado(): bool {

    return isset($_SESSION["usuario_id"]);

}

//proteção de paginas

function jaboo_exigir_login(): void {

    if (!jaboo_usuario_logado()) {

        header("Location: login.php");

        exit;

    }

}

//redirecionar um usuario já conectado

function jaboo_redirecionar_logado(): void {

    if (jaboo_usuario_logado()) {

        header("Location: index.php");

        exit;

    }

}
 