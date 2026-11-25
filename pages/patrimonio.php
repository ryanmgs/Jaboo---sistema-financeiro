<?php
require_once __DIR__ . '/jaboo_page_helpers.php';
$db = jaboo_tela_db();

$sqlContas = "SELECT c.id, c.nome, c.tipo, c.instituicao, c.cor,
    c.saldo_inicial + COALESCE(m.receitas, 0) - COALESCE(m.despesas, 0) AS saldo_atual
    FROM contas c
    LEFT JOIN (
        SELECT usuario_id, conta_id,
            SUM(CASE WHEN tipo='receita' AND status='efetivada' THEN valor ELSE 0 END) AS receitas,
            SUM(CASE WHEN tipo='despesa' AND status='efetivada' THEN valor ELSE 0 END) AS despesas
        FROM transacoes WHERE usuario_id=? GROUP BY usuario_id, conta_id
    ) m ON m.usuario_id=c.usuario_id AND m.conta_id=c.id
    WHERE c.usuario_id=? AND c.ativo=1 AND c.incluir_no_patrimonio=1
    ORDER BY saldo_atual DESC, c.nome ASC";
$stmt = $db->prepare($sqlContas);
$stmt->bind_param('ii', $jabooUsuarioId, $jabooUsuarioId);
$stmt->execute();
$contasPatrimonio = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $db->prepare('SELECT id, nome, tipo, instituicao, ticker, valor_investido, valor_atual FROM investimentos WHERE usuario_id=? AND ativo=1 ORDER BY valor_atual DESC, nome ASC');
$stmt->bind_param('i', $jabooUsuarioId);
$stmt->execute();
$investimentosPatrimonio = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalContas = 0.0;
foreach ($contasPatrimonio as $conta) $totalContas += (float) $conta['saldo_atual'];
$totalInvestimentos = 0.0;
$totalInvestido = 0.0;
foreach ($investimentosPatrimonio as $investimento) {
    $totalInvestimentos += (float) $investimento['valor_atual'];
    $totalInvestido += (float) $investimento['valor_investido'];
}
$totalPatrimonio = $totalContas + $totalInvestimentos;
$participacaoContas = $totalPatrimonio > 0.0 ? ($totalContas / $totalPatrimonio) * 100 : 0;
$participacaoInvestimentos = $totalPatrimonio > 0.0 ? ($totalInvestimentos / $totalPatrimonio) * 100 : 0;
?>
<div class="jaboo-tela">
    <div class="jaboo-cabecalho-tela"><div><h1>Patrimônio</h1><p>Entenda a composição do que você possui.</p></div></div>
    <div class="jaboo-indicadores jaboo-indicadores-3">
        <article class="jaboo-indicador"><span>Patrimônio total</span><strong><?= jaboo_tela_moeda($totalPatrimonio) ?></strong><small>Contas + investimentos</small></article>
        <article class="jaboo-indicador"><span>Contas</span><strong><?= jaboo_tela_moeda($totalContas) ?></strong><small>Saldos disponíveis incluídos no patrimônio</small></article>
        <article class="jaboo-indicador"><span>Investimentos</span><strong><?= jaboo_tela_moeda($totalInvestimentos) ?></strong><small><?= jaboo_tela_moeda($totalInvestido) ?> aplicados</small></article>
    </div>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><h2>Composição do patrimônio</h2></div>
        <div class="jaboo-barra-composicao" aria-label="Composição do patrimônio">
            <div class="contas" style="width: <?= max(0, min(100, $participacaoContas)) ?>%"></div>
            <div class="investimentos" style="width: <?= max(0, min(100, $participacaoInvestimentos)) ?>%"></div>
        </div>
        <div class="jaboo-legenda-composicao">
            <span><i class="jaboo-ponto contas"></i> Contas: <?= number_format($participacaoContas, 1, ',', '.') ?>%</span>
            <span><i class="jaboo-ponto investimentos"></i> Investimentos: <?= number_format($participacaoInvestimentos, 1, ',', '.') ?>%</span>
        </div>
        <div class="jaboo-tabela-responsiva"><table class="jaboo-tabela"><thead><tr><th>Origem</th><th>Valor</th><th>Participação</th></tr></thead><tbody>
            <tr><td>Contas</td><td><?= jaboo_tela_moeda($totalContas) ?></td><td><?= number_format($participacaoContas, 1, ',', '.') ?>%</td></tr>
            <tr><td>Investimentos</td><td><?= jaboo_tela_moeda($totalInvestimentos) ?></td><td><?= number_format($participacaoInvestimentos, 1, ',', '.') ?>%</td></tr>
            <tr class="jaboo-linha-total"><td>Total</td><td><?= jaboo_tela_moeda($totalPatrimonio) ?></td><td><?= number_format($totalPatrimonio > 0 ? 100 : 0, 1, ',', '.') ?>%</td></tr>
        </tbody></table></div>
    </section>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><div><h2>Contas consideradas</h2><p>Somente contas ativas marcadas para entrar no patrimônio.</p></div><a href="index.php?pagina=contas">Gerenciar contas</a></div>
        <div class="jaboo-tabela-responsiva"><table class="jaboo-tabela"><thead><tr><th>Conta</th><th>Instituição</th><th>Tipo</th><th>Saldo atual</th></tr></thead><tbody>
        <?php if (!$contasPatrimonio): ?><tr><td colspan="4" class="jaboo-vazio">Nenhuma conta está incluída no patrimônio.</td></tr><?php endif; ?>
        <?php foreach ($contasPatrimonio as $conta): ?><tr><td><strong><?= jaboo_tela_escape($conta['nome']) ?></strong></td><td><?= jaboo_tela_escape($conta['instituicao'] ?: '—') ?></td><td><?= jaboo_tela_escape(str_replace('_', ' ', ucfirst($conta['tipo']))) ?></td><td><?= jaboo_tela_moeda($conta['saldo_atual']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><div><h2>Investimentos considerados</h2><p>Valor de mercado informado em cada ativo.</p></div><a href="index.php?pagina=investimentos">Ver investimentos</a></div>
        <div class="jaboo-tabela-responsiva"><table class="jaboo-tabela"><thead><tr><th>Investimento</th><th>Tipo</th><th>Valor investido</th><th>Valor atual</th></tr></thead><tbody>
        <?php if (!$investimentosPatrimonio): ?><tr><td colspan="4" class="jaboo-vazio">Nenhum investimento ativo cadastrado.</td></tr><?php endif; ?>
        <?php foreach ($investimentosPatrimonio as $investimento): ?><tr><td><strong><?= jaboo_tela_escape($investimento['nome']) ?></strong><small class="jaboo-subtexto"><?= jaboo_tela_escape($investimento['ticker'] ?: ($investimento['instituicao'] ?: '')) ?></small></td><td><?= jaboo_tela_escape(jaboo_tela_tipo_investimento($investimento['tipo'])) ?></td><td><?= jaboo_tela_moeda($investimento['valor_investido']) ?></td><td><?= jaboo_tela_moeda($investimento['valor_atual']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    <p class="jaboo-nota">O patrimônio mostra uma posição acumulada. Receitas e despesas do mês aparecem nos Relatórios e não são somadas novamente aqui. Contas desativadas e contas fora do patrimônio são ignoradas.</p>
</div>
