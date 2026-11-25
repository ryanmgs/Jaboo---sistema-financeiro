<?php
require_once __DIR__ . '/jaboo_page_helpers.php';
$db = jaboo_tela_db();
$tiposInvestimento = ['renda_fixa', 'acao', 'fii', 'fundo', 'cripto', 'tesouro', 'outro'];
$erroTela = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        jaboo_tela_validar_csrf();
        $acao = (string) ($_POST['acao'] ?? '');

        if ($acao === 'salvar_investimento') {
            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $tipo = (string) ($_POST['tipo'] ?? '');
            $instituicao = trim((string) ($_POST['instituicao'] ?? ''));
            $ticker = strtoupper(trim((string) ($_POST['ticker'] ?? '')));
            $quantidade = jaboo_tela_decimal($_POST['quantidade'] ?? '0');
            $precoMedio = jaboo_tela_decimal($_POST['preco_medio'] ?? '0');
            $valorInvestido = jaboo_tela_decimal($_POST['valor_investido'] ?? '0');
            $valorAtual = jaboo_tela_decimal($_POST['valor_atual'] ?? '0');
            $dataInicio = trim((string) ($_POST['data_inicio'] ?? ''));
            $observacoes = trim((string) ($_POST['observacoes'] ?? ''));

            if ($nome === '' || mb_strlen($nome) > 150) throw new RuntimeException('Informe um nome de até 150 caracteres.');
            if (!in_array($tipo, $tiposInvestimento, true)) throw new RuntimeException('Selecione um tipo de investimento válido.');
            foreach ([$quantidade, $precoMedio, $valorInvestido, $valorAtual] as $numero) {
                if (!is_finite($numero) || $numero < 0) throw new RuntimeException('Os valores do investimento não podem ser negativos.');
            }
            if ($valorInvestido > 9999999999999.99 || $valorAtual > 9999999999999.99) throw new RuntimeException('O valor informado excede o limite permitido.');
            if ($dataInicio !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) throw new RuntimeException('Informe uma data de início válida.');
            $instituicao = $instituicao === '' ? null : $instituicao;
            $ticker = $ticker === '' ? null : $ticker;
            $dataInicio = $dataInicio === '' ? null : $dataInicio;
            $observacoes = $observacoes === '' ? null : $observacoes;
            $qSql = jaboo_tela_decimal_sql($quantidade, 8);
            $pmSql = jaboo_tela_decimal_sql($precoMedio, 8);
            $viSql = jaboo_tela_decimal_sql($valorInvestido, 2);
            $vaSql = jaboo_tela_decimal_sql($valorAtual, 2);

            if ($id > 0) {
                $stmt = $db->prepare('UPDATE investimentos SET nome=?, tipo=?, instituicao=?, ticker=?, quantidade=?, preco_medio=?, valor_investido=?, valor_atual=?, data_inicio=?, observacoes=? WHERE id=? AND usuario_id=?');
                $stmt->bind_param('ssssssssssii', $nome, $tipo, $instituicao, $ticker, $qSql, $pmSql, $viSql, $vaSql, $dataInicio, $observacoes, $id, $jabooUsuarioId);
                $stmt->execute();
                if ($stmt->affected_rows < 0) throw new RuntimeException('Não foi possível atualizar o investimento.');
                jaboo_tela_flash_definir('sucesso', 'Investimento atualizado com sucesso.');
                jaboo_tela_redirecionar('investimentos', 'investimento=' . $id);
            } else {
                $stmt = $db->prepare('INSERT INTO investimentos (usuario_id, nome, tipo, instituicao, ticker, quantidade, preco_medio, valor_investido, valor_atual, data_inicio, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('issssssssss', $jabooUsuarioId, $nome, $tipo, $instituicao, $ticker, $qSql, $pmSql, $viSql, $vaSql, $dataInicio, $observacoes);
                $stmt->execute();
                $novoId = (int) $db->insert_id;
                if ($valorAtual > 0 || $quantidade > 0) {
                    $precoUnitario = $quantidade > 0 ? $valorAtual / $quantidade : 0;
                    $puSql = jaboo_tela_decimal_sql($precoUnitario, 8);
                    $stmtAvaliacao = $db->prepare('INSERT INTO avaliacoes_investimento (usuario_id, investimento_id, valor_total, quantidade, preco_unitario, data_avaliacao) VALUES (?, ?, ?, ?, ?, CURDATE())');
                    $stmtAvaliacao->bind_param('iisss', $jabooUsuarioId, $novoId, $vaSql, $qSql, $puSql);
                    $stmtAvaliacao->execute();
                }
                jaboo_tela_flash_definir('sucesso', 'Investimento cadastrado com sucesso.');
                jaboo_tela_redirecionar('investimentos', 'investimento=' . $novoId);
            }
        }

        if ($acao === 'registrar_movimentacao') {
            $investimentoId = (int) ($_POST['investimento_id'] ?? 0);
            $tipoMovimento = (string) ($_POST['tipo_movimento'] ?? '');
            $quantidadeMovimento = jaboo_tela_decimal($_POST['quantidade_movimento'] ?? '0');
            $valorMovimento = jaboo_tela_decimal($_POST['valor_movimento'] ?? '0');
            $dataMovimento = (string) ($_POST['data_movimentacao'] ?? date('Y-m-d'));
            $descricao = trim((string) ($_POST['descricao_movimentacao'] ?? ''));
            $tiposMovimento = ['aporte', 'resgate', 'dividendo', 'juros', 'taxa', 'outro'];
            if ($investimentoId <= 0 || !in_array($tipoMovimento, $tiposMovimento, true)) throw new RuntimeException('Selecione um investimento e um tipo de operação.');
            if (!is_finite($quantidadeMovimento) || $quantidadeMovimento < 0 || !is_finite($valorMovimento) || $valorMovimento <= 0) throw new RuntimeException('Informe um valor positivo e uma quantidade igual ou maior que zero.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataMovimento)) throw new RuntimeException('Informe uma data válida.');

            $stmt = $db->prepare('SELECT quantidade, preco_medio, valor_investido FROM investimentos WHERE id=? AND usuario_id=? AND ativo=1 LIMIT 1');
            $stmt->bind_param('ii', $investimentoId, $jabooUsuarioId);
            $stmt->execute();
            $atual = $stmt->get_result()->fetch_assoc();
            if (!$atual) throw new RuntimeException('Investimento não encontrado ou inativo.');

            $quantidadeAtual = (float) $atual['quantidade'];
            $precoMedioAtual = (float) $atual['preco_medio'];
            $investidoAtual = (float) $atual['valor_investido'];
            $novaQuantidade = $quantidadeAtual;
            $novoInvestido = $investidoAtual;
            $novoPrecoMedio = $precoMedioAtual;

            if ($tipoMovimento === 'aporte') {
                $novaQuantidade += $quantidadeMovimento;
                $novoInvestido += $valorMovimento;
                if ($novaQuantidade > 0) $novoPrecoMedio = $novoInvestido / $novaQuantidade;
            } elseif ($tipoMovimento === 'resgate') {
                if ($quantidadeMovimento > 0) {
                    if ($quantidadeMovimento > $quantidadeAtual + 0.00000001) throw new RuntimeException('A quantidade do resgate é maior que a quantidade cadastrada.');
                    $novaQuantidade = max(0, $quantidadeAtual - $quantidadeMovimento);
                    $novoInvestido = $novaQuantidade * $precoMedioAtual;
                } else {
                    if ($valorMovimento > $investidoAtual + 0.00000001) throw new RuntimeException('O valor do resgate não pode ser maior que o valor investido quando a quantidade não é informada.');
                    $novoInvestido = max(0, $investidoAtual - $valorMovimento);
                }
            }

            $qSql = jaboo_tela_decimal_sql($quantidadeMovimento, 8);
            $vSql = jaboo_tela_decimal_sql($valorMovimento, 2);
            $novoQSql = jaboo_tela_decimal_sql($novaQuantidade, 8);
            $novoISql = jaboo_tela_decimal_sql($novoInvestido, 2);
            $novoPMSql = jaboo_tela_decimal_sql($novoPrecoMedio, 8);
            $db->begin_transaction();
            try {
                $stmtMov = $db->prepare('INSERT INTO movimentacoes_investimento (usuario_id, investimento_id, tipo, quantidade, valor, data_movimentacao, descricao) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmtMov->bind_param('iisssss', $jabooUsuarioId, $investimentoId, $tipoMovimento, $qSql, $vSql, $dataMovimento, $descricao);
                $stmtMov->execute();
                if ($tipoMovimento === 'aporte' || $tipoMovimento === 'resgate') {
                    $stmtUpd = $db->prepare('UPDATE investimentos SET quantidade=?, valor_investido=?, preco_medio=? WHERE id=? AND usuario_id=?');
                    $stmtUpd->bind_param('sssii', $novoQSql, $novoISql, $novoPMSql, $investimentoId, $jabooUsuarioId);
                    $stmtUpd->execute();
                }
                $db->commit();
            } catch (Throwable $e) {
                $db->rollback();
                throw $e;
            }
            jaboo_tela_flash_definir('sucesso', 'Movimentação registrada. O valor de mercado continua sendo atualizado na avaliação do investimento.');
            jaboo_tela_redirecionar('investimentos', 'investimento=' . $investimentoId);
        }

        if ($acao === 'atualizar_avaliacao') {
            $investimentoId = (int) ($_POST['investimento_id'] ?? 0);
            $valorAtual = jaboo_tela_decimal($_POST['valor_atual'] ?? '0');
            $quantidade = jaboo_tela_decimal($_POST['quantidade_avaliacao'] ?? '0');
            $dataAvaliacao = (string) ($_POST['data_avaliacao'] ?? date('Y-m-d'));
            if ($investimentoId <= 0 || !is_finite($valorAtual) || $valorAtual < 0 || !is_finite($quantidade) || $quantidade < 0) throw new RuntimeException('Informe valores válidos para a avaliação.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAvaliacao)) throw new RuntimeException('Informe uma data válida.');
            $stmt = $db->prepare('SELECT id FROM investimentos WHERE id=? AND usuario_id=? AND ativo=1 LIMIT 1');
            $stmt->bind_param('ii', $investimentoId, $jabooUsuarioId);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) throw new RuntimeException('Investimento não encontrado.');
            $valorSql = jaboo_tela_decimal_sql($valorAtual, 2);
            $quantidadeSql = jaboo_tela_decimal_sql($quantidade, 8);
            $precoUnitario = $quantidade > 0 ? $valorAtual / $quantidade : 0;
            $precoSql = jaboo_tela_decimal_sql($precoUnitario, 8);
            $db->begin_transaction();
            try {
                $stmtUpd = $db->prepare('UPDATE investimentos SET valor_atual=?, quantidade=? WHERE id=? AND usuario_id=?');
                $stmtUpd->bind_param('ssii', $valorSql, $quantidadeSql, $investimentoId, $jabooUsuarioId);
                $stmtUpd->execute();
                $stmtAv = $db->prepare('INSERT INTO avaliacoes_investimento (usuario_id, investimento_id, valor_total, quantidade, preco_unitario, data_avaliacao) VALUES (?, ?, ?, ?, ?, ?)');
                $stmtAv->bind_param('iissss', $jabooUsuarioId, $investimentoId, $valorSql, $quantidadeSql, $precoSql, $dataAvaliacao);
                $stmtAv->execute();
                $db->commit();
            } catch (Throwable $e) {
                $db->rollback();
                throw $e;
            }
            jaboo_tela_flash_definir('sucesso', 'Valor atual e histórico de avaliação atualizados.');
            jaboo_tela_redirecionar('investimentos', 'investimento=' . $investimentoId);
        }

        if ($acao === 'alternar_ativo') {
            $id = (int) ($_POST['id'] ?? 0);
            $novoStatus = (int) ($_POST['novo_status'] ?? 0);
            if ($id <= 0 || !in_array($novoStatus, [0, 1], true)) throw new RuntimeException('Ação inválida.');
            $stmt = $db->prepare('UPDATE investimentos SET ativo=? WHERE id=? AND usuario_id=?');
            $stmt->bind_param('iii', $novoStatus, $id, $jabooUsuarioId);
            $stmt->execute();
            jaboo_tela_flash_definir('sucesso', $novoStatus ? 'Investimento reativado.' : 'Investimento arquivado.');
            jaboo_tela_redirecionar('investimentos');
        }
    } catch (Throwable $e) {
        $erroTela = $e->getMessage();
    }
}

$busca = trim((string) ($_GET['busca'] ?? ''));
$sqlLista = 'SELECT id, nome, tipo, instituicao, ticker, quantidade, preco_medio, valor_investido, valor_atual, data_inicio, ativo FROM investimentos WHERE usuario_id=?';
if ($busca !== '') $sqlLista .= ' AND (nome LIKE ? OR ticker LIKE ? OR instituicao LIKE ?)';
$sqlLista .= ' ORDER BY ativo DESC, nome ASC';
$stmtLista = $db->prepare($sqlLista);
if ($busca !== '') {
    $termo = '%' . $busca . '%';
    $stmtLista->bind_param('isss', $jabooUsuarioId, $termo, $termo, $termo);
} else {
    $stmtLista->bind_param('i', $jabooUsuarioId);
}
$stmtLista->execute();
$investimentos = $stmtLista->get_result()->fetch_all(MYSQLI_ASSOC);

$stmtResumo = $db->prepare('SELECT COALESCE(SUM(valor_investido),0) AS investido, COALESCE(SUM(valor_atual),0) AS atual, COUNT(*) AS quantidade FROM investimentos WHERE usuario_id=? AND ativo=1');
$stmtResumo->bind_param('i', $jabooUsuarioId);
$stmtResumo->execute();
$resumo = $stmtResumo->get_result()->fetch_assoc() ?: ['investido' => 0, 'atual' => 0, 'quantidade' => 0];
$resultadoCarteira = (float) $resumo['atual'] - (float) $resumo['investido'];
$percentualCarteira = (float) $resumo['investido'] > 0 ? ($resultadoCarteira / (float) $resumo['investido']) * 100 : 0;

$editarId = (int) ($_GET['editar'] ?? 0);
$selecionadoId = (int) ($_GET['investimento'] ?? 0);
$investimentoEdicao = null;
if ($editarId > 0) {
    $stmt = $db->prepare('SELECT * FROM investimentos WHERE id=? AND usuario_id=? LIMIT 1');
    $stmt->bind_param('ii', $editarId, $jabooUsuarioId);
    $stmt->execute();
    $investimentoEdicao = $stmt->get_result()->fetch_assoc();
}
$investimentoSelecionado = null;
if ($selecionadoId > 0) {
    $stmt = $db->prepare('SELECT * FROM investimentos WHERE id=? AND usuario_id=? LIMIT 1');
    $stmt->bind_param('ii', $selecionadoId, $jabooUsuarioId);
    $stmt->execute();
    $investimentoSelecionado = $stmt->get_result()->fetch_assoc();
}
if (!$investimentoSelecionado && !$investimentoEdicao && count($investimentos) > 0) {
    foreach ($investimentos as $item) {
        if ((int) $item['ativo'] === 1) {
            $selecionadoId = (int) $item['id'];
            $stmt = $db->prepare('SELECT * FROM investimentos WHERE id=? AND usuario_id=? LIMIT 1');
            $stmt->bind_param('ii', $selecionadoId, $jabooUsuarioId);
            $stmt->execute();
            $investimentoSelecionado = $stmt->get_result()->fetch_assoc();
            break;
        }
    }
}
$movimentacoes = [];
$avaliacoes = [];
if ($investimentoSelecionado) {
    $stmt = $db->prepare('SELECT tipo, quantidade, valor, data_movimentacao, descricao FROM movimentacoes_investimento WHERE usuario_id=? AND investimento_id=? ORDER BY data_movimentacao DESC, id DESC LIMIT 30');
    $stmt->bind_param('ii', $jabooUsuarioId, $selecionadoId);
    $stmt->execute();
    $movimentacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt = $db->prepare('SELECT valor_total, quantidade, preco_unitario, data_avaliacao FROM avaliacoes_investimento WHERE usuario_id=? AND investimento_id=? ORDER BY data_avaliacao DESC, id DESC LIMIT 20');
    $stmt->bind_param('ii', $jabooUsuarioId, $selecionadoId);
    $stmt->execute();
    $avaliacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$dadosFormulario = $investimentoEdicao ?: ['id' => 0, 'nome' => '', 'tipo' => 'renda_fixa', 'instituicao' => '', 'ticker' => '', 'quantidade' => '0', 'preco_medio' => '0', 'valor_investido' => '0', 'valor_atual' => '0', 'data_inicio' => '', 'observacoes' => ''];
?>
<div class="jaboo-tela">
    <div class="jaboo-cabecalho-tela">
        <div><h1>Investimentos</h1><p>Consulte sua carteira e acompanhe sua evolução.</p></div>
        <a class="jaboo-botao" href="#novo-investimento">Adicionar investimento</a>
    </div>
    <?php jaboo_tela_flash_exibir(); ?>
    <?php if ($erroTela !== ''): ?><div class="jaboo-alerta erro"><?= jaboo_tela_escape($erroTela) ?></div><?php endif; ?>

    <div class="jaboo-indicadores jaboo-indicadores-3">
        <article class="jaboo-indicador"><span>Total investido</span><strong><?= jaboo_tela_moeda($resumo['investido']) ?></strong><small>Valor aplicado nos ativos ativos</small></article>
        <article class="jaboo-indicador"><span>Valor atual</span><strong><?= jaboo_tela_moeda($resumo['atual']) ?></strong><small>Valor de mercado informado</small></article>
        <article class="jaboo-indicador"><span>Resultado</span><strong class="<?= $resultadoCarteira >= 0 ? 'jaboo-positivo' : 'jaboo-negativo' ?>"><?= $resultadoCarteira >= 0 ? '+' : '−' ?><?= jaboo_tela_moeda(abs($resultadoCarteira)) ?></strong><small><?= number_format($percentualCarteira, 2, ',', '.') ?>% sobre o valor investido</small></article>
    </div>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><h2>Carteira</h2><form method="get" class="jaboo-filtro-busca"><input type="hidden" name="pagina" value="investimentos"><input type="search" name="busca" value="<?= jaboo_tela_escape($busca) ?>" placeholder="Buscar investimento"><button class="jaboo-botao-secundario">Buscar</button></form></div>
        <div class="jaboo-tabela-responsiva"><table class="jaboo-tabela"><thead><tr><th>Investimento</th><th>Tipo</th><th>Valor investido</th><th>Valor atual</th><th>Resultado</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
        <?php if (!$investimentos): ?><tr><td colspan="7" class="jaboo-vazio">Nenhum investimento cadastrado.</td></tr><?php endif; ?>
        <?php foreach ($investimentos as $item): $resultado = (float) $item['valor_atual'] - (float) $item['valor_investido']; ?>
            <tr>
                <td><strong><?= jaboo_tela_escape($item['nome']) ?></strong><small class="jaboo-subtexto"><?= jaboo_tela_escape($item['ticker'] ?: ($item['instituicao'] ?: '')) ?></small></td>
                <td><?= jaboo_tela_escape(jaboo_tela_tipo_investimento($item['tipo'])) ?></td>
                <td><?= jaboo_tela_moeda($item['valor_investido']) ?></td>
                <td><?= jaboo_tela_moeda($item['valor_atual']) ?></td>
                <td class="<?= $resultado >= 0 ? 'jaboo-positivo' : 'jaboo-negativo' ?>"><?= $resultado >= 0 ? '+' : '−' ?><?= jaboo_tela_moeda(abs($resultado)) ?></td>
                <td><span class="jaboo-status <?= (int) $item['ativo'] === 1 ? 'ativo' : 'inativo' ?>"><?= (int) $item['ativo'] === 1 ? 'Ativo' : 'Arquivado' ?></span></td>
                <td class="jaboo-acoes-tabela"><a href="index.php?pagina=investimentos&investimento=<?= (int) $item['id'] ?>">Histórico</a> <a href="index.php?pagina=investimentos&editar=<?= (int) $item['id'] ?>">Editar</a>
                    <form method="post" class="jaboo-form-inline" onsubmit="return confirm('Confirma esta alteração?')"><?= jaboo_tela_csrf_input() ?><input type="hidden" name="acao" value="alternar_ativo"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="novo_status" value="<?= (int) $item['ativo'] === 1 ? 0 : 1 ?>"><button class="jaboo-link-botao" type="submit"><?= (int) $item['ativo'] === 1 ? 'Arquivar' : 'Reativar' ?></button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>

    <section class="jaboo-painel" id="novo-investimento">
        <div class="jaboo-titulo-painel"><h2><?= $investimentoEdicao ? 'Editar investimento' : 'Novo investimento' ?></h2><?php if ($investimentoEdicao): ?><a href="index.php?pagina=investimentos">Cancelar edição</a><?php endif; ?></div>
        <form method="post" class="jaboo-formulario">
            <?= jaboo_tela_csrf_input() ?><input type="hidden" name="acao" value="salvar_investimento"><input type="hidden" name="id" value="<?= (int) $dadosFormulario['id'] ?>">
            <div class="jaboo-grade-form">
                <label>Nome do investimento<input name="nome" maxlength="150" required value="<?= jaboo_tela_escape($dadosFormulario['nome']) ?>" placeholder="Ex.: Tesouro Selic"></label>
                <label>Tipo<select name="tipo" required><?php foreach ($tiposInvestimento as $tipo): ?><option value="<?= $tipo ?>" <?= $dadosFormulario['tipo'] === $tipo ? 'selected' : '' ?>><?= jaboo_tela_escape(jaboo_tela_tipo_investimento($tipo)) ?></option><?php endforeach; ?></select></label>
                <label>Instituição<input name="instituicao" maxlength="150" value="<?= jaboo_tela_escape($dadosFormulario['instituicao']) ?>" placeholder="Banco ou corretora"></label>
                <label>Ticker<input name="ticker" maxlength="30" value="<?= jaboo_tela_escape($dadosFormulario['ticker']) ?>" placeholder="Ex.: PETR4"></label>
                <label>Quantidade<input type="number" name="quantidade" min="0" step="0.00000001" required value="<?= jaboo_tela_escape((string) $dadosFormulario['quantidade']) ?>"></label>
                <label>Preço médio unitário<input type="number" name="preco_medio" min="0" step="0.00000001" required value="<?= jaboo_tela_escape((string) $dadosFormulario['preco_medio']) ?>"></label>
                <label>Valor investido (R$)<input type="number" name="valor_investido" min="0" step="0.01" required value="<?= jaboo_tela_escape((string) $dadosFormulario['valor_investido']) ?>"></label>
                <label>Valor atual (R$)<input type="number" name="valor_atual" min="0" step="0.01" required value="<?= jaboo_tela_escape((string) $dadosFormulario['valor_atual']) ?>"></label>
                <label>Data de início<input type="date" name="data_inicio" value="<?= jaboo_tela_escape((string) $dadosFormulario['data_inicio']) ?>"></label>
                <label class="jaboo-campo-largo">Observações<textarea name="observacoes" rows="3" placeholder="Informações adicionais"><?= jaboo_tela_escape((string) $dadosFormulario['observacoes']) ?></textarea></label>
            </div>
            <div class="jaboo-form-acoes"><button class="jaboo-botao" type="submit"><?= $investimentoEdicao ? 'Salvar alterações' : 'Cadastrar investimento' ?></button></div>
        </form>
    </section>

    <?php if ($investimentoSelecionado && (int) $investimentoSelecionado['ativo'] === 1): ?>
        <section class="jaboo-painel">
            <div class="jaboo-titulo-painel"><div><h2><?= jaboo_tela_escape($investimentoSelecionado['nome']) ?></h2><p>Operações e atualizações deste investimento.</p></div><span class="jaboo-status ativo">Selecionado</span></div>
            <div class="jaboo-grade-dupla">
                <form method="post" class="jaboo-formulario jaboo-form-card">
                    <h3>Registrar operação</h3><?= jaboo_tela_csrf_input() ?><input type="hidden" name="acao" value="registrar_movimentacao"><input type="hidden" name="investimento_id" value="<?= (int) $investimentoSelecionado['id'] ?>">
                    <label>Tipo de operação<select name="tipo_movimento" required><option value="aporte">Aporte</option><option value="resgate">Resgate</option><option value="dividendo">Dividendo</option><option value="juros">Juros</option><option value="taxa">Taxa</option><option value="outro">Outro</option></select></label>
                    <label>Valor da operação (R$)<input type="number" name="valor_movimento" min="0.01" step="0.01" required></label>
                    <label>Quantidade movimentada<input type="number" name="quantidade_movimento" min="0" step="0.00000001" value="0" required></label>
                    <label>Data<input type="date" name="data_movimentacao" value="<?= date('Y-m-d') ?>" required></label>
                    <label>Descrição<input name="descricao_movimentacao" maxlength="200" placeholder="Opcional"></label>
                    <button class="jaboo-botao" type="submit">Registrar operação</button>
                </form>
                <form method="post" class="jaboo-formulario jaboo-form-card">
                    <h3>Atualizar valor de mercado</h3><?= jaboo_tela_csrf_input() ?><input type="hidden" name="acao" value="atualizar_avaliacao"><input type="hidden" name="investimento_id" value="<?= (int) $investimentoSelecionado['id'] ?>">
                    <label>Valor total atual (R$)<input type="number" name="valor_atual" min="0" step="0.01" value="<?= jaboo_tela_escape((string) $investimentoSelecionado['valor_atual']) ?>" required></label>
                    <label>Quantidade atual<input type="number" name="quantidade_avaliacao" min="0" step="0.00000001" value="<?= jaboo_tela_escape((string) $investimentoSelecionado['quantidade']) ?>" required></label>
                    <label>Data da avaliação<input type="date" name="data_avaliacao" value="<?= date('Y-m-d') ?>" required></label>
                    <p class="jaboo-ajuda">A avaliação registra o valor informado e cria uma linha no histórico.</p>
                    <button class="jaboo-botao-secundario" type="submit">Salvar avaliação</button>
                </form>
            </div>
            <div class="jaboo-grade-dupla jaboo-historicos">
                <div><h3>Movimentações recentes</h3><div class="jaboo-tabela-responsiva"><table class="jaboo-tabela"><thead><tr><th>Data</th><th>Tipo</th><th>Quantidade</th><th>Valor</th></tr></thead><tbody>
                    <?php if (!$movimentacoes): ?><tr><td colspan="4" class="jaboo-vazio">Nenhuma operação registrada.</td></tr><?php endif; ?>
                    <?php foreach ($movimentacoes as $mov): ?><tr><td><?= jaboo_tela_data_br($mov['data_movimentacao']) ?></td><td><?= jaboo_tela_escape(jaboo_tela_tipo_movimento($mov['tipo'])) ?></td><td><?= number_format((float) $mov['quantidade'], 8, ',', '.') ?></td><td><?= jaboo_tela_moeda($mov['valor']) ?></td></tr><?php endforeach; ?>
                </tbody></table></div></div>
                <div><h3>Histórico de avaliações</h3><div class="jaboo-tabela-responsiva"><table class="jaboo-tabela"><thead><tr><th>Data</th><th>Valor total</th><th>Preço unitário</th></tr></thead><tbody>
                    <?php if (!$avaliacoes): ?><tr><td colspan="3" class="jaboo-vazio">Nenhuma avaliação registrada.</td></tr><?php endif; ?>
                    <?php foreach ($avaliacoes as $av): ?><tr><td><?= jaboo_tela_data_br($av['data_avaliacao']) ?></td><td><?= jaboo_tela_moeda($av['valor_total']) ?></td><td><?= jaboo_tela_moeda($av['preco_unitario']) ?></td></tr><?php endforeach; ?>
                </tbody></table></div></div>
            </div>
        </section>
    <?php endif; ?>
    <p class="jaboo-nota">Aportes e resgates atualizam quantidade e custo investido. Dividendos, juros e taxas ficam registrados como movimentações; o valor de mercado deve ser atualizado na avaliação. O JABOO não realiza transferências financeiras.</p>
</div>
