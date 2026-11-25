<?php
require_once __DIR__ . '/jaboo_page_helpers.php';
$db = jaboo_tela_db();
$mes = (int) ($_GET['mes'] ?? date('n'));
$ano = (int) ($_GET['ano'] ?? date('Y'));
$mes = max(1, min(12, $mes));
$ano = max(2000, min(2100, $ano));
$inicio = sprintf('%04d-%02d-01', $ano, $mes);
$fim = date('Y-m-t', strtotime($inicio));
$nomeMes = [1=>'Janeiro', 2=>'Fevereiro', 3=>'Março', 4=>'Abril', 5=>'Maio', 6=>'Junho', 7=>'Julho', 8=>'Agosto', 9=>'Setembro', 10=>'Outubro', 11=>'Novembro', 12=>'Dezembro'][$mes];

if (($_GET['exportar'] ?? '') === 'csv') {
    $stmt = $db->prepare("SELECT t.descricao, t.tipo, COALESCE(c.nome, 'Sem categoria') AS categoria, t.valor, t.data_competencia, t.data_pagamento, t.status FROM transacoes t LEFT JOIN categorias c ON c.id=t.categoria_id AND c.usuario_id=t.usuario_id WHERE t.usuario_id=? AND t.status='efetivada' AND t.data_competencia BETWEEN ? AND ? ORDER BY t.data_competencia ASC, t.id ASC");
    $stmt->bind_param('iss', $jabooUsuarioId, $inicio, $fim);
    $stmt->execute();
    $linhas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="jaboo-relatorio-' . $ano . '-' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '.csv"');
    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, ['Relatório JABOO', $nomeMes . ' de ' . $ano], ';');
    fputcsv($saida, ['Descrição', 'Tipo', 'Categoria', 'Valor (R$)', 'Data de competência', 'Data de pagamento', 'Status'], ';');
    foreach ($linhas as $linha) {
        fputcsv($saida, [$linha['descricao'], $linha['tipo'] === 'receita' ? 'Receita' : 'Despesa', $linha['categoria'], number_format((float) $linha['valor'], 2, ',', '.'), $linha['data_competencia'], $linha['data_pagamento'] ?? '', 'Efetivada'], ';');
    }
    fclose($saida);
    exit;
}

$stmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END),0) AS receitas, COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END),0) AS despesas, COUNT(*) AS lancamentos FROM transacoes WHERE usuario_id=? AND status='efetivada' AND data_competencia BETWEEN ? AND ?");
$stmt->bind_param('iss', $jabooUsuarioId, $inicio, $fim);
$stmt->execute();
$resumo = $stmt->get_result()->fetch_assoc() ?: ['receitas'=>0, 'despesas'=>0, 'lancamentos'=>0];
$resultado = (float) $resumo['receitas'] - (float) $resumo['despesas'];

$stmt = $db->prepare("SELECT t.tipo, COALESCE(c.nome, 'Sem categoria') AS categoria, COALESCE(SUM(t.valor),0) AS total, COUNT(*) AS quantidade FROM transacoes t LEFT JOIN categorias c ON c.id=t.categoria_id AND c.usuario_id=t.usuario_id WHERE t.usuario_id=? AND t.status='efetivada' AND t.data_competencia BETWEEN ? AND ? GROUP BY t.tipo, c.id, c.nome ORDER BY t.tipo ASC, total DESC");
$stmt->bind_param('iss', $jabooUsuarioId, $inicio, $fim);
$stmt->execute();
$categoriasRelatorio = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $db->prepare("SELECT t.descricao, t.tipo, t.valor, t.data_competencia, COALESCE(c.nome, 'Sem categoria') AS categoria, a.nome AS conta FROM transacoes t LEFT JOIN categorias c ON c.id=t.categoria_id AND c.usuario_id=t.usuario_id INNER JOIN contas a ON a.id=t.conta_id AND a.usuario_id=t.usuario_id WHERE t.usuario_id=? AND t.status='efetivada' AND t.data_competencia BETWEEN ? AND ? ORDER BY t.data_competencia DESC, t.id DESC LIMIT 50");
$stmt->bind_param('iss', $jabooUsuarioId, $inicio, $fim);
$stmt->execute();
$movimentacoesRelatorio = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$inicioMesAnterior = date('Y-m-01', strtotime($inicio . ' -1 month'));
$fimMesAnterior = date('Y-m-t', strtotime($inicioMesAnterior));
$stmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END),0) AS receitas, COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END),0) AS despesas FROM transacoes WHERE usuario_id=? AND status='efetivada' AND data_competencia BETWEEN ? AND ?");
$stmt->bind_param('iss', $jabooUsuarioId, $inicioMesAnterior, $fimMesAnterior);
$stmt->execute();
$anterior = $stmt->get_result()->fetch_assoc() ?: ['receitas'=>0, 'despesas'=>0];
?>
<div class="jaboo-tela">
    <div class="jaboo-cabecalho-tela"><div><h1>Relatórios</h1><p>Analise suas movimentações por período e categoria.</p></div></div>
    <section class="jaboo-painel jaboo-painel-filtro">
        <div class="jaboo-titulo-painel"><h2>Período do relatório</h2></div>
        <form method="get" class="jaboo-filtros"><input type="hidden" name="pagina" value="relatorios">
            <label>Mês<select name="mes"><?php foreach ($nomeMes ? range(1,12) : [] as $numero): ?><option value="<?= $numero ?>" <?= $mes === $numero ? 'selected' : '' ?>><?= jaboo_tela_escape([1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'][$numero]) ?></option><?php endforeach; ?></select></label>
            <label>Ano<input type="number" name="ano" min="2000" max="2100" value="<?= $ano ?>"></label>
            <button class="jaboo-botao" type="submit">Aplicar período</button>
            <a class="jaboo-botao-secundario" href="index.php?pagina=relatorios&mes=<?= $mes ?>&ano=<?= $ano ?>&exportar=csv">Exportar planilha (CSV)</a>
        </form>
    </section>

    <div class="jaboo-indicadores jaboo-indicadores-3">
        <article class="jaboo-indicador"><span>Receitas</span><strong class="jaboo-positivo"><?= jaboo_tela_moeda($resumo['receitas']) ?></strong><small>No período</small></article>
        <article class="jaboo-indicador"><span>Despesas</span><strong class="jaboo-negativo"><?= jaboo_tela_moeda($resumo['despesas']) ?></strong><small>No período</small></article>
        <article class="jaboo-indicador"><span>Resultado</span><strong class="<?= $resultado >= 0 ? 'jaboo-positivo' : 'jaboo-negativo' ?>"><?= jaboo_tela_moeda($resultado) ?></strong><small><?= (int) $resumo['lancamentos'] ?> lançamentos efetivados</small></article>
    </div>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><div><h2>Resumo por categoria</h2><p><?= jaboo_tela_escape($nomeMes) ?> de <?= $ano ?> · comparação com o mês anterior: receitas <?= jaboo_tela_moeda($anterior['receitas']) ?> e despesas <?= jaboo_tela_moeda($anterior['despesas']) ?></p></div></div>
        <div class="jaboo-tabela-responsiva"><table class="jaboo-tabela"><thead><tr><th>Tipo</th><th>Categoria</th><th>Lançamentos</th><th>Total</th></tr></thead><tbody>
        <?php if (!$categoriasRelatorio): ?><tr><td colspan="4" class="jaboo-vazio">Não há transações efetivadas neste período.</td></tr><?php endif; ?>
        <?php foreach ($categoriasRelatorio as $linha): ?><tr><td><span class="jaboo-status <?= $linha['tipo'] === 'receita' ? 'ativo' : 'inativo' ?>"><?= $linha['tipo'] === 'receita' ? 'Receita' : 'Despesa' ?></span></td><td><?= jaboo_tela_escape($linha['categoria']) ?></td><td><?= (int) $linha['quantidade'] ?></td><td><?= jaboo_tela_moeda($linha['total']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><h2>Movimentações do período</h2><span class="jaboo-contagem"><?= count($movimentacoesRelatorio) ?> de até 50 registros</span></div>
        <div class="jaboo-tabela-responsiva"><table class="jaboo-tabela"><thead><tr><th>Descrição</th><th>Categoria</th><th>Conta</th><th>Data</th><th>Tipo</th><th>Valor</th></tr></thead><tbody>
        <?php if (!$movimentacoesRelatorio): ?><tr><td colspan="6" class="jaboo-vazio">Nenhuma movimentação encontrada.</td></tr><?php endif; ?>
        <?php foreach ($movimentacoesRelatorio as $mov): ?><tr><td><strong><?= jaboo_tela_escape($mov['descricao']) ?></strong></td><td><?= jaboo_tela_escape($mov['categoria']) ?></td><td><?= jaboo_tela_escape($mov['conta']) ?></td><td><?= jaboo_tela_data_br($mov['data_competencia']) ?></td><td><?= $mov['tipo'] === 'receita' ? 'Receita' : 'Despesa' ?></td><td class="<?= $mov['tipo'] === 'receita' ? 'jaboo-positivo' : 'jaboo-negativo' ?>"><?= $mov['tipo'] === 'receita' ? '+' : '−' ?><?= jaboo_tela_moeda($mov['valor']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    <p class="jaboo-nota">Os relatórios consideram somente transações efetivadas e usam a data de competência. Lançamentos pendentes ou cancelados não entram nos totais.</p>
</div>
