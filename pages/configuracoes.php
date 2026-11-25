<?php
require_once __DIR__ . '/jaboo_page_helpers.php';
$db = jaboo_tela_db();
$erroTela = '';

$stmt = $db->prepare('SELECT nome, email, moeda_padrao, tema, criado_em FROM usuarios WHERE id=? AND ativo=1 LIMIT 1');
$stmt->bind_param('i', $jabooUsuarioId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
if (!$usuario) throw new RuntimeException('Não foi possível localizar sua conta.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        jaboo_tela_validar_csrf();
        $acao = (string) ($_POST['acao'] ?? '');
        if ($acao === 'salvar_perfil') {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $moeda = (string) ($_POST['moeda_padrao'] ?? 'BRL');
            $tema = (string) ($_POST['tema'] ?? 'sistema');
            if ($nome === '' || mb_strlen($nome) > 120) throw new RuntimeException('Informe um nome de até 120 caracteres.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) throw new RuntimeException('Informe um endereço de e-mail válido.');
            if (!in_array($moeda, ['BRL', 'USD', 'EUR'], true)) throw new RuntimeException('Selecione uma moeda válida.');
            if (!in_array($tema, ['claro', 'escuro', 'sistema'], true)) throw new RuntimeException('Selecione um tema válido.');
            $stmt = $db->prepare('SELECT id FROM usuarios WHERE email=? AND id<>? LIMIT 1');
            $stmt->bind_param('si', $email, $jabooUsuarioId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) throw new RuntimeException('Este e-mail já está sendo utilizado por outra conta.');
            $stmt = $db->prepare('UPDATE usuarios SET nome=?, email=?, moeda_padrao=?, tema=? WHERE id=?');
            $stmt->bind_param('ssssi', $nome, $email, $moeda, $tema, $jabooUsuarioId);
            $stmt->execute();
            if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])) {
                $_SESSION['usuario']['nome'] = $nome;
                $_SESSION['usuario']['email'] = $email;
            }
            jaboo_tela_flash_definir('sucesso', 'Perfil e preferências atualizados.');
            jaboo_tela_redirecionar('configuracoes');
        }

        if ($acao === 'alterar_senha') {
            $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
            $senhaNova = (string) ($_POST['senha_nova'] ?? '');
            $confirmacao = (string) ($_POST['confirmar_senha'] ?? '');
            if (mb_strlen($senhaNova) < 8) throw new RuntimeException('A nova senha deve ter pelo menos 8 caracteres.');
            if ($senhaNova !== $confirmacao) throw new RuntimeException('A confirmação da nova senha não corresponde.');
            $stmt = $db->prepare('SELECT senha_hash FROM usuarios WHERE id=? LIMIT 1');
            $stmt->bind_param('i', $jabooUsuarioId);
            $stmt->execute();
            $registroSenha = $stmt->get_result()->fetch_assoc();
            if (!$registroSenha || !password_verify($senhaAtual, $registroSenha['senha_hash'])) throw new RuntimeException('A senha atual está incorreta.');
            $hash = password_hash($senhaNova, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE usuarios SET senha_hash=? WHERE id=?');
            $stmt->bind_param('si', $hash, $jabooUsuarioId);
            $stmt->execute();
            jaboo_tela_flash_definir('sucesso', 'Senha alterada com sucesso.');
            jaboo_tela_redirecionar('configuracoes');
        }
    } catch (Throwable $e) {
        $erroTela = $e->getMessage();
    }
    $stmt = $db->prepare('SELECT nome, email, moeda_padrao, tema, criado_em FROM usuarios WHERE id=? AND ativo=1 LIMIT 1');
    $stmt->bind_param('i', $jabooUsuarioId);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc() ?: $usuario;
}
?>
<div class="jaboo-tela">
    <div class="jaboo-cabecalho-tela"><div><h1>Configurações</h1><p>Personalize sua experiência no JABOO.</p></div></div>
    <?php jaboo_tela_flash_exibir(); ?>
    <?php if ($erroTela !== ''): ?><div class="jaboo-alerta erro"><?= jaboo_tela_escape($erroTela) ?></div><?php endif; ?>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><div><h2>Perfil e preferências</h2><p>Atualize os dados principais da sua conta.</p></div></div>
        <form method="post" class="jaboo-formulario">
            <?= jaboo_tela_csrf_input() ?><input type="hidden" name="acao" value="salvar_perfil">
            <div class="jaboo-grade-form">
                <label>Nome<input name="nome" maxlength="120" required value="<?= jaboo_tela_escape($usuario['nome']) ?>"></label>
                <label>E-mail<input type="email" name="email" maxlength="190" required value="<?= jaboo_tela_escape($usuario['email']) ?>"></label>
                <label>Moeda padrão<select name="moeda_padrao"><option value="BRL" <?= $usuario['moeda_padrao'] === 'BRL' ? 'selected' : '' ?>>Real brasileiro (BRL)</option><option value="USD" <?= $usuario['moeda_padrao'] === 'USD' ? 'selected' : '' ?>>Dólar americano (USD)</option><option value="EUR" <?= $usuario['moeda_padrao'] === 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option></select></label>
                <label>Aparência<select name="tema"><option value="claro" <?= $usuario['tema'] === 'claro' ? 'selected' : '' ?>>Claro</option><option value="escuro" <?= $usuario['tema'] === 'escuro' ? 'selected' : '' ?>>Escuro</option><option value="sistema" <?= $usuario['tema'] === 'sistema' ? 'selected' : '' ?>>Seguir o sistema</option></select></label>
            </div>
            <div class="jaboo-form-acoes"><button class="jaboo-botao" type="submit">Salvar preferências</button></div>
        </form>
    </section>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><div><h2>Segurança</h2><p>Altere sua senha de acesso.</p></div></div>
        <form method="post" class="jaboo-formulario">
            <?= jaboo_tela_csrf_input() ?><input type="hidden" name="acao" value="alterar_senha">
            <div class="jaboo-grade-form">
                <label>Senha atual<input type="password" name="senha_atual" autocomplete="current-password" required></label>
                <label>Nova senha<input type="password" name="senha_nova" autocomplete="new-password" minlength="8" required></label>
                <label>Confirmar nova senha<input type="password" name="confirmar_senha" autocomplete="new-password" minlength="8" required></label>
            </div>
            <div class="jaboo-form-acoes"><button class="jaboo-botao-secundario" type="submit">Alterar senha</button></div>
        </form>
    </section>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><div><h2>Organização</h2><p>Gerencie os registros que ajudam a organizar seu planejamento.</p></div></div>
        <div class="jaboo-atalhos-config">
            <a href="index.php?pagina=categorias"><strong>Gerenciar categorias</strong><span>Crie, edite e desative categorias de receitas e despesas.</span><b>→</b></a>
            <a href="index.php?pagina=contas"><strong>Gerenciar contas</strong><span>Consulte seus bancos, carteiras e saldos.</span><b>→</b></a>
            <a href="index.php?pagina=investimentos"><strong>Gerenciar investimentos</strong><span>Atualize sua carteira e registre operações.</span><b>→</b></a>
        </div>
    </section>

    <section class="jaboo-painel jaboo-painel-seguranca">
        <div class="jaboo-titulo-painel"><div><h2>Sessão</h2><p>Conta criada em <?= jaboo_tela_data_br(substr((string) $usuario['criado_em'], 0, 10)) ?>.</p></div><a class="jaboo-link-perigo" href="sair.php">Sair da conta</a></div>
    </section>
    <p class="jaboo-nota">As preferências de tema e moeda ficam salvas no cadastro. A aplicação global do tema e a conversão entre moedas ainda precisam ser integradas ao layout e às regras financeiras gerais do sistema; escolher USD ou EUR nesta tela não converte os valores já registrados.</p>
</div>
