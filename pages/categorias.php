<?php
require_once __DIR__ . '/jaboo_page_helpers.php';
$db = jaboo_tela_db();
$erroTela = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        jaboo_tela_validar_csrf();
        $acao = (string) ($_POST['acao'] ?? '');
        if ($acao === 'salvar_categoria') {
            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $tipo = (string) ($_POST['tipo'] ?? '');
            $icone = trim((string) ($_POST['icone'] ?? ''));
            $cor = strtoupper(trim((string) ($_POST['cor'] ?? '#245B40')));
            $paiId = (int) ($_POST['categoria_pai_id'] ?? 0);
            if ($nome === '' || mb_strlen($nome) > 100) throw new RuntimeException('Informe um nome de até 100 caracteres.');
            if (!in_array($tipo, ['receita', 'despesa'], true)) throw new RuntimeException('Selecione receita ou despesa.');
            if (!preg_match('/^#[0-9A-F]{6}$/', $cor)) throw new RuntimeException('Escolha uma cor hexadecimal válida.');
            if (mb_strlen($icone) > 50) throw new RuntimeException('O ícone deve ter até 50 caracteres.');
            $paiBanco = null;
            if ($paiId > 0) {
                if ($paiId === $id) throw new RuntimeException('Uma categoria não pode ser subcategoria dela mesma.');
                $stmt = $db->prepare('SELECT id FROM categorias WHERE id=? AND usuario_id=? AND tipo=? AND ativo=1 LIMIT 1');
                $stmt->bind_param('iis', $paiId, $jabooUsuarioId, $tipo);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) throw new RuntimeException('A categoria principal precisa ser ativa, do mesmo tipo e pertencer à sua conta.');
                $paiBanco = $paiId;
            }
            $icone = $icone === '' ? null : $icone;
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE categorias SET categoria_pai_id=?, nome=?, tipo=?, icone=?, cor=? WHERE id=? AND usuario_id=?');
                $stmt->bind_param('issssii', $paiBanco, $nome, $tipo, $icone, $cor, $id, $jabooUsuarioId);
                $stmt->execute();
                jaboo_tela_flash_definir('sucesso', 'Categoria atualizada.');
            } else {
                $stmt = $db->prepare('INSERT INTO categorias (usuario_id, categoria_pai_id, nome, tipo, icone, cor, categoria_padrao, ativo) VALUES (?, ?, ?, ?, ?, ?, 0, 1)');
                $stmt->bind_param('iissss', $jabooUsuarioId, $paiBanco, $nome, $tipo, $icone, $cor);
                $stmt->execute();
                jaboo_tela_flash_definir('sucesso', 'Categoria criada.');
            }
            jaboo_tela_redirecionar('categorias');
        }
        if ($acao === 'alternar_categoria') {
            $id = (int) ($_POST['id'] ?? 0);
            $novoStatus = (int) ($_POST['novo_status'] ?? 0);
            if ($id <= 0 || !in_array($novoStatus, [0, 1], true)) throw new RuntimeException('Ação inválida.');
            $stmt = $db->prepare('UPDATE categorias SET ativo=? WHERE id=? AND usuario_id=?');
            $stmt->bind_param('iii', $novoStatus, $id, $jabooUsuarioId);
            $stmt->execute();
            jaboo_tela_flash_definir('sucesso', $novoStatus ? 'Categoria reativada.' : 'Categoria desativada.');
            jaboo_tela_redirecionar('categorias');
        }
    } catch (Throwable $e) {
        $erroTela = $e->getMessage();
    }
}

$filtroTipo = (string) ($_GET['tipo'] ?? 'todas');
$filtroStatus = (string) ($_GET['status'] ?? 'ativas');
$sql = 'SELECT c.id, c.categoria_pai_id, c.nome, c.tipo, c.icone, c.cor, c.categoria_padrao, c.ativo, p.nome AS nome_pai FROM categorias c LEFT JOIN categorias p ON p.id=c.categoria_pai_id AND p.usuario_id=c.usuario_id WHERE c.usuario_id=?';
if (in_array($filtroTipo, ['receita', 'despesa'], true)) $sql .= ' AND c.tipo=?';
if ($filtroStatus === 'ativas') $sql .= ' AND c.ativo=1';
elseif ($filtroStatus === 'inativas') $sql .= ' AND c.ativo=0';
$sql .= ' ORDER BY c.tipo ASC, c.nome ASC';
$stmt = $db->prepare($sql);
if (in_array($filtroTipo, ['receita', 'despesa'], true)) {
    $stmt->bind_param('is', $jabooUsuarioId, $filtroTipo);
} else {
    $stmt->bind_param('i', $jabooUsuarioId);
}
$stmt->execute();
$categorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $db->prepare('SELECT id, nome, tipo FROM categorias WHERE usuario_id=? AND ativo=1 ORDER BY nome ASC');
$stmt->bind_param('i', $jabooUsuarioId);
$stmt->execute();
$categoriasPai = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="jaboo-tela">
    <div class="jaboo-cabecalho-tela"><div><h1>Categorias</h1><p>Organize suas receitas e despesas para deixar os relatórios mais claros.</p></div><a class="jaboo-botao-secundario" href="index.php?pagina=configuracoes">Voltar às configurações</a></div>
    <?php jaboo_tela_flash_exibir(); ?>
    <?php if ($erroTela !== ''): ?><div class="jaboo-alerta erro"><?= jaboo_tela_escape($erroTela) ?></div><?php endif; ?>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><h2>Nova categoria</h2></div>
        <form method="post" class="jaboo-formulario">
            <?= jaboo_tela_csrf_input() ?><input type="hidden" name="acao" value="salvar_categoria"><input type="hidden" name="id" value="0">
            <div class="jaboo-grade-form">
                <label>Nome<input name="nome" maxlength="100" required placeholder="Ex.: Alimentação"></label>
                <label>Tipo<select name="tipo" required><option value="despesa">Despesa</option><option value="receita">Receita</option></select></label>
                <label>Categoria principal<select name="categoria_pai_id"><option value="0">Nenhuma (categoria principal)</option><?php foreach ($categoriasPai as $pai): ?><option value="<?= (int) $pai['id'] ?>"><?= jaboo_tela_escape($pai['nome']) ?> · <?= $pai['tipo'] === 'receita' ? 'Receita' : 'Despesa' ?></option><?php endforeach; ?></select></label>
                <label>Ícone (opcional)<input name="icone" maxlength="50" placeholder="Ex.: casa, carro ou 🍎"></label>
                <label>Cor<input type="color" name="cor" value="#245B40"></label>
            </div>
            <div class="jaboo-form-acoes"><button class="jaboo-botao" type="submit">Criar categoria</button></div>
        </form>
    </section>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><h2>Suas categorias</h2><form method="get" class="jaboo-filtros jaboo-filtros-pequenos"><input type="hidden" name="pagina" value="categorias"><label>Tipo<select name="tipo"><option value="todas">Todos</option><option value="receita" <?= $filtroTipo === 'receita' ? 'selected' : '' ?>>Receitas</option><option value="despesa" <?= $filtroTipo === 'despesa' ? 'selected' : '' ?>>Despesas</option></select></label><label>Situação<select name="status"><option value="ativas" <?= $filtroStatus === 'ativas' ? 'selected' : '' ?>>Ativas</option><option value="inativas" <?= $filtroStatus === 'inativas' ? 'selected' : '' ?>>Inativas</option><option value="todas" <?= $filtroStatus === 'todas' ? 'selected' : '' ?>>Todas</option></select></label><button class="jaboo-botao-secundario">Filtrar</button></form></div>
        <div class="jaboo-lista-categorias">
            <?php if (!$categorias): ?><p class="jaboo-vazio">Nenhuma categoria encontrada para este filtro.</p><?php endif; ?>
            <?php foreach ($categorias as $categoria): ?>
                <details class="jaboo-categoria-item">
                    <summary><span class="jaboo-categoria-cor" style="background: <?= jaboo_tela_escape($categoria['cor']) ?>"></span><span class="jaboo-categoria-nome"><strong><?= jaboo_tela_escape($categoria['nome']) ?></strong><small><?= $categoria['tipo'] === 'receita' ? 'Receita' : 'Despesa' ?><?= $categoria['nome_pai'] ? ' · Subcategoria de ' . jaboo_tela_escape($categoria['nome_pai']) : '' ?><?= (int) $categoria['categoria_padrao'] === 1 ? ' · Padrão' : '' ?></small></span><span class="jaboo-status <?= (int) $categoria['ativo'] === 1 ? 'ativo' : 'inativo' ?>"><?= (int) $categoria['ativo'] === 1 ? 'Ativa' : 'Inativa' ?></span><span class="jaboo-categoria-editar">Editar</span></summary>
                    <div class="jaboo-categoria-conteudo">
                        <form method="post" class="jaboo-formulario">
                            <?= jaboo_tela_csrf_input() ?><input type="hidden" name="acao" value="salvar_categoria"><input type="hidden" name="id" value="<?= (int) $categoria['id'] ?>">
                            <div class="jaboo-grade-form">
                                <label>Nome<input name="nome" maxlength="100" required value="<?= jaboo_tela_escape($categoria['nome']) ?>"></label>
                                <label>Tipo<select name="tipo"><option value="despesa" <?= $categoria['tipo'] === 'despesa' ? 'selected' : '' ?>>Despesa</option><option value="receita" <?= $categoria['tipo'] === 'receita' ? 'selected' : '' ?>>Receita</option></select></label>
                                <label>Categoria principal<select name="categoria_pai_id"><option value="0">Nenhuma</option><?php foreach ($categoriasPai as $pai): if ((int)$pai['id'] === (int)$categoria['id']) continue; ?><option value="<?= (int) $pai['id'] ?>" <?= (int) $categoria['categoria_pai_id'] === (int) $pai['id'] ? 'selected' : '' ?>><?= jaboo_tela_escape($pai['nome']) ?> · <?= $pai['tipo'] === 'receita' ? 'Receita' : 'Despesa' ?></option><?php endforeach; ?></select></label>
                                <label>Ícone<input name="icone" maxlength="50" value="<?= jaboo_tela_escape($categoria['icone']) ?>"></label>
                                <label>Cor<input type="color" name="cor" value="<?= jaboo_tela_escape($categoria['cor']) ?>"></label>
                            </div>
                            <div class="jaboo-form-acoes"><button class="jaboo-botao-secundario" type="submit">Salvar categoria</button></div>
                        </form>
                        <form method="post" class="jaboo-form-inline" onsubmit="return confirm('Confirma a alteração desta categoria?')"><?= jaboo_tela_csrf_input() ?><input type="hidden" name="acao" value="alternar_categoria"><input type="hidden" name="id" value="<?= (int) $categoria['id'] ?>"><input type="hidden" name="novo_status" value="<?= (int) $categoria['ativo'] === 1 ? 0 : 1 ?>"><button class="jaboo-link-botao <?= (int) $categoria['ativo'] === 1 ? 'jaboo-link-perigo' : '' ?>" type="submit"><?= (int) $categoria['ativo'] === 1 ? 'Desativar categoria' : 'Reativar categoria' ?></button></form>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </section>
    <p class="jaboo-nota">Categorias usadas em transações ou orçamentos são desativadas em vez de excluídas, para preservar o histórico financeiro.</p>
</div>
