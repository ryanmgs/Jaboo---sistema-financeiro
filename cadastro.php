
<?php

require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/auth.php";

jaboo_redirecionar_logado();

$erro = "";
$nome = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    jaboo_validar_csrf();

    $nome = trim($_POST["nome"] ?? "");
    $email = trim($_POST["email"] ?? "");

    $senha = $_POST["senha"] ?? "";
    $confirmarSenha = $_POST["confirmar_senha"] ?? "";

    $tamanhoNome = function_exists("mb_strlen")
        ? mb_strlen($nome, "UTF-8")
        : strlen($nome);

    if ($tamanhoNome < 2 || $tamanhoNome > 100) {

        $erro = "Digite um nome válido.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $erro = "Digite um e-mail válido.";

    } elseif (strlen($senha) < 8) {

        $erro = "A senha precisa ter pelo menos 8 caracteres.";

    } elseif ($senha !== $confirmarSenha) {

        $erro = "As senhas não coincidem.";

    } else {

        $algoritmo = defined("PASSWORD_ARGON2ID")
            ? PASSWORD_ARGON2ID
            : PASSWORD_DEFAULT;

        $senhaHash = password_hash($senha, $algoritmo);

        try {

            $insercao = $conexao->prepare(
                "INSERT INTO usuarios (nome, email, senha_hash)
                 VALUES (?, ?, ?)"
            );

            $insercao->bind_param(
                "sss",
                $nome,
                $email,
                $senhaHash
            );

            $insercao->execute();

            $novoUsuarioId = $conexao->insert_id;

            $insercao->close();

            session_regenerate_id(true);

            $_SESSION["usuario_id"] = (int) $novoUsuarioId;
            $_SESSION["usuario_nome"] = $nome;
            $_SESSION["usuario_email"] = $email;

            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            header("Location: index.php");

            exit;

        } catch (mysqli_sql_exception $erroBanco) {

            error_log("Erro no cadastro JABOO: " . $erroBanco->getMessage());

            if ((int) $erroBanco->getCode() === 1062) {

                $erro = "Este e-mail já está cadastrado.";

            } else {

                $erro = "Não foi possível criar sua conta. Tente novamente.";

            }

        }

    }

}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Criar conta | JABOO</title>

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

                <span class="auth-subtitle">COMECE SUA ORGANIZAÇÃO</span>

                <h1>Crie sua conta</h1>

                <p>
                    Dê o primeiro passo para organizar sua vida financeira.
                </p>

            </div>

            <?php if ($erro !== ""): ?>

                <div class="message-error">
                    <?= jaboo_escape($erro) ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="cadastro.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= jaboo_escape(jaboo_csrf_token()) ?>"
                >

                <div class="input-group">

                    <label for="nome">Nome completo</label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        placeholder="Digite seu nome"
                        value="<?= jaboo_escape($nome) ?>"
                        maxlength="100"
                        autocomplete="name"
                        required
                    >

                </div>

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
                        placeholder="Mínimo de 8 caracteres"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <div class="input-group">

                    <label for="confirmar_senha">Confirmar senha</label>

                    <input
                        type="password"
                        id="confirmar_senha"
                        name="confirmar_senha"
                        placeholder="Digite a senha novamente"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <button type="submit" class="btn-primary">
                    Criar conta
                </button>

            </form>

            <div class="auth-footer">

                Já possui uma conta?

                <a href="login.php">Entrar</a>

            </div>

        </section>

    </main>

</body>

</html>
