
<?php

require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/auth.php";

jaboo_redirecionar_logado();

$erro = "";
$email = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    jaboo_validar_csrf();

    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $erro = "Digite um e-mail válido.";

    } elseif ($senha === "") {

        $erro = "Digite sua senha.";

    } else {

        $consulta = $conexao->prepare(
            "SELECT id, nome, email, senha_hash
             FROM usuarios
             WHERE email = ?
             LIMIT 1"
        );

        $consulta->bind_param("s", $email);

        $consulta->execute();

        $resultado = $consulta->get_result();

        $usuario = $resultado->fetch_assoc();

        $consulta->close();

        if (
            $usuario &&
            password_verify($senha, $usuario["senha_hash"])
        ) {

            session_regenerate_id(true);

            $_SESSION["usuario_id"] = (int) $usuario["id"];
            $_SESSION["usuario_nome"] = $usuario["nome"];
            $_SESSION["usuario_email"] = $usuario["email"];

            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            $algoritmo = defined("PASSWORD_ARGON2ID")
                ? PASSWORD_ARGON2ID
                : PASSWORD_DEFAULT;

            if (password_needs_rehash($usuario["senha_hash"], $algoritmo)) {

                $novoHash = password_hash($senha, $algoritmo);

                $atualizacao = $conexao->prepare(
                    "UPDATE usuarios SET senha_hash = ? WHERE id = ?"
                );

                $atualizacao->bind_param(
                    "si",
                    $novoHash,
                    $usuario["id"]
                );

                $atualizacao->execute();

                $atualizacao->close();

            }

            header("Location: index.php");

            exit;

        } else {

            $erro = "E-mail ou senha incorretos.";

        }

    }

}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Entrar | JABOO</title>

    <link rel="stylesheet" href="assets/login.css">

</head>

<body>

    <main class="auth-container">

        <section class="auth-card">

            <a href="login.php" class="logo">
                <span class="logo-icon">J</span>
                <span>JABOO</span>
            </a>

            <div class="auth-header">

                <span class="auth-subtitle">BEM-VINDO DE VOLTA</span>

                <h1>Entre na sua conta</h1>

                <p>
                    Organize seu dinheiro e acompanhe sua vida financeira.
                </p>

            </div>

            <?php if ($erro !== ""): ?>

                <div class="message-error">
                    <?= jaboo_escape($erro) ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="login.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= jaboo_escape(jaboo_csrf_token()) ?>"
                >

                <div class="input-group">

                    <label for="email">E-mail</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="seuemail@exemplo.com"
                        value="<?= jaboo_escape($email) ?>"
                        autocomplete="email"
                        required
                    >

                </div>

                <div class="input-group">

                    <label for="senha">Senha</label>

                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="Digite sua senha"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <button type="submit" class="btn-primary">
                    Entrar
                </button>

            </form>

            <div class="auth-footer">

                Ainda não possui uma conta?

                <a href="cadastro.php">Criar conta</a>

            </div>

        </section>

    </main>

</body>

</html>
