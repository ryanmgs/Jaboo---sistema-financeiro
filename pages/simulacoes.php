<?php
require_once __DIR__ . '/jaboo_page_helpers.php';
$db = jaboo_tela_db();
$erroTela = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        jaboo_tela_validar_csrf();
        $acao = (string) ($_POST['acao'] ?? '');
        if ($acao === 'salvar_simulacao') {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $valorInicial = jaboo_tela_decimal($_POST['valor_inicial'] ?? '0');
            $aporteMensal = jaboo_tela_decimal($_POST['aporte_mensal'] ?? '0');
            $taxa = jaboo_tela_decimal($_POST['taxa_juros_mensal'] ?? '0');
            $periodo = (int) ($_POST['periodo_meses'] ?? 0);
            $tipoTaxa = (string) ($_POST['tipo_taxa'] ?? 'estimada');
            $observacoes = trim((string) ($_POST['observacoes'] ?? ''));
            if ($nome === '' || mb_strlen($nome) > 150) throw new RuntimeException('Informe um nome para a simulação.');
            if (!is_finite($valorInicial) || $valorInicial < 0 || !is_finite($aporteMensal) || $aporteMensal < 0) throw new RuntimeException('O valor inicial e o aporte mensal não podem ser negativos.');
            if (!is_finite($taxa) || $taxa < -100 || $taxa > 1000) throw new RuntimeException('A taxa mensal deve estar entre -100% e 1.000%.');
            if ($periodo < 1 || $periodo > 1200) throw new RuntimeException('O prazo deve estar entre 1 e 1.200 meses.');
            if (!in_array($tipoTaxa, ['fixa', 'estimada'], true)) throw new RuntimeException('Selecione o tipo de taxa.');

            $saldo = $valorInicial;
            $taxaDecimal = $taxa / 100;
            for ($mes = 1; $mes <= $periodo; $mes++) {
                $saldo = ($saldo * (1 + $taxaDecimal)) + $aporteMensal;
                if (!is_finite($saldo) || $saldo > 9999999999999.99) throw new RuntimeException('O resultado ultrapassou o limite de cálculo suportado. Reduza a taxa ou o prazo.');
            }
            $totalAportado = $valorInicial + ($aporteMensal * $periodo);
            $rendimento = $saldo - $totalAportado;
            $inicialSql = jaboo_tela_decimal_sql($valorInicial, 2);
            $aporteSql = jaboo_tela_decimal_sql($aporteMensal, 2);
            $taxaSql = jaboo_tela_decimal_sql($taxa, 6);
            $finalSql = jaboo_tela_decimal_sql($saldo, 2);
            $totalSql = jaboo_tela_decimal_sql($totalAportado, 2);
            $rendimentoSql = jaboo_tela_decimal_sql($rendimento, 2);
            $observacoes = $observacoes === '' ? null : $observacoes;
            $stmt = $db->prepare('INSERT INTO simulacoes_investimento (usuario_id, nome, valor_inicial, aporte_mensal, taxa_juros_mensal, periodo_meses, tipo_taxa, valor_final_estimado, total_aportado, rendimento_estimado, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('issssisssss', $jabooUsuarioId, $nome, $inicialSql, $aporteSql, $taxaSql, $periodo, $tipoTaxa, $finalSql, $totalSql, $rendimentoSql, $observacoes);
            $stmt->execute();
            $novoId = (int) $db->insert_id;
            jaboo_tela_flash_definir('sucesso', 'Simulação calculada e salva. Ela não altera suas contas nem seus investimentos reais.');
            jaboo_tela_redirecionar('simulacoes', 'ver=' . $novoId);
        }

        if ($acao === 'excluir_simulacao') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('Simulação inválida.');
            $stmt = $db->prepare('DELETE FROM simulacoes_investimento WHERE id=? AND usuario_id=?');
            $stmt->bind_param('ii', $id, $jabooUsuarioId);
            $stmt->execute();
            jaboo_tela_flash_definir('sucesso', 'Simulação removida.');
            jaboo_tela_redirecionar('simulacoes');
        }
    } catch (Throwable $e) {
        $erroTela = $e->getMessage();
    }
}

$stmt = $db->prepare('SELECT id, nome, valor_inicial, aporte_mensal, taxa_juros_mensal, periodo_meses, tipo_taxa, valor_final_estimado, total_aportado, rendimento_estimado, observacoes, criado_em FROM simulacoes_investimento WHERE usuario_id=? ORDER BY criado_em DESC, id DESC LIMIT 30');
$stmt->bind_param('i', $jabooUsuarioId);
$stmt->execute();
$simulacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$simulacaoSelecionada = null;
$verId = (int) ($_GET['ver'] ?? 0);
foreach ($simulacoes as $simulacao) {
    if ($verId === (int) $simulacao['id']) { $simulacaoSelecionada = $simulacao; break; }
}
if (!$simulacaoSelecionada && $simulacoes) $simulacaoSelecionada = $simulacoes[0];
?>
<div class="jaboo-tela">
    <div class="jaboo-cabecalho-tela"><div><h1>Simulações</h1><p>Teste possibilidades para seus objetivos financeiros.</p></div></div>
    <?php jaboo_tela_flash_exibir(); ?>
    <?php if ($erroTela !== ''): ?><div class="jaboo-alerta erro"><?= jaboo_tela_escape($erroTela) ?></div><?php endif; ?>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><div><h2>Simulação de crescimento</h2><p>Faça uma projeção com valor inicial, aportes e taxa mensal.</p></div></div>
        <form method="post" class="jaboo-formulario">
            <?= jaboo_tela_csrf_input() ?><input type="hidden" name="acao" value="salvar_simulacao">
            <div class="jaboo-grade-form">
                <label class="jaboo-campo-largo">Nome da simulação<input name="nome" maxlength="150" required placeholder="Ex.: Reserva de emergência"></label>
                <label>Valor inicial (R$)<input type="number" name="valor_inicial" min="0" step="0.01" value="1000" required></label>
                <label>Aporte mensal (R$)<input type="number" name="aporte_mensal" min="0" step="0.01" value="200" required></label>
                <label>Taxa de juros mensal (%)<input type="number" name="taxa_juros_mensal" min="-100" max="1000" step="0.000001" value="0.7" required></label>
                <label>Prazo (meses)<input type="number" name="periodo_meses" min="1" max="1200" value="24" required></label>
                <label>Tipo de taxa<select name="tipo_taxa"><option value="estimada">Estimada</option><option value="fixa">Fixa</option></select></label>
                <label class="jaboo-campo-largo">Observações<textarea name="observacoes" rows="2" placeholder="Opcional"></textarea></label>
            </div>
            <div class="jaboo-form-acoes"><button class="jaboo-botao" type="submit">Calcular e salvar simulação</button></div>
        </form>
    </section>

    <?php if ($simulacaoSelecionada): ?>
        <?php
        $saldoProjecao = (float) $simulacaoSelecionada['valor_inicial'];
        $taxaProjecao = (float) $simulacaoSelecionada['taxa_juros_mensal'] / 100;
        $pontosProjecao = [];
        $pontosProjecao[] = ['mes' => 0, 'saldo' => $saldoProjecao];
        for ($i = 1; $i <= (int) $simulacaoSelecionada['periodo_meses']; $i++) {
            $saldoProjecao = $saldoProjecao * (1 + $taxaProjecao) + (float) $simulacaoSelecionada['aporte_mensal'];
            if ($i === 1 || $i % max(1, (int) ceil((int) $simulacaoSelecionada['periodo_meses'] / 12)) === 0 || $i === (int) $simulacaoSelecionada['periodo_meses']) {
                $pontosProjecao[] = ['mes' => $i, 'saldo' => $saldoProjecao];
            }
        }
        ?>
        <section class="jaboo-painel">
            <div class="jaboo-titulo-painel"><div><h2><?= jaboo_tela_escape($simulacaoSelecionada['nome']) ?></h2><p>Resultado da simulação selecionada.</p></div></div>
            <div class="jaboo-resumo-simulacao">
                <p><strong>Valor inicial:</strong> <?= jaboo_tela_moeda($simulacaoSelecionada['valor_inicial']) ?></p>
                <p><strong>Aporte mensal:</strong> <?= jaboo_tela_moeda($simulacaoSelecionada['aporte_mensal']) ?></p>
                <p><strong>Prazo:</strong> <?= (int) $simulacaoSelecionada['periodo_meses'] ?> meses</p>
                <p><strong>Taxa <?= $simulacaoSelecionada['tipo_taxa'] === 'fixa' ? 'fixa' : 'estimada' ?>:</strong> <?= number_format((float) $simulacaoSelecionada['taxa_juros_mensal'], 4, ',', '.') ?>% ao mês</p>
            </div>
            <div class="jaboo-indicadores jaboo-indicadores-3">
                <article class="jaboo-indicador"><span>Total aportado</span><strong><?= jaboo_tela_moeda($simulacaoSelecionada['total_aportado']) ?></strong><small>Valor colocado por você</small></article>
                <article class="jaboo-indicador"><span>Saldo estimado</span><strong><?= jaboo_tela_moeda($simulacaoSelecionada['valor_final_estimado']) ?></strong><small>Projeção ilustrativa</small></article>
                <article class="jaboo-indicador"><span>Rendimento estimado</span><strong class="<?= (float) $simulacaoSelecionada['rendimento_estimado'] >= 0 ? 'jaboo-positivo' : 'jaboo-negativo' ?>"><?= jaboo_tela_moeda($simulacaoSelecionada['rendimento_estimado']) ?></strong><small>Não é garantia de retorno</small></article>
            </div>
            <h3 class="jaboo-subtitulo">Evolução projetada</h3>
            <div class="jaboo-tabela-responsiva"><table class="jaboo-tabela"><thead><tr><th>Mês</th><th>Saldo estimado</th></tr></thead><tbody>
                <?php foreach ($pontosProjecao as $ponto): ?><tr><td><?= (int) $ponto['mes'] === 0 ? 'Início' : 'Mês ' . (int) $ponto['mes'] ?></td><td><?= jaboo_tela_moeda($ponto['saldo']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </section>
    <?php endif; ?>

    <section class="jaboo-painel">
        <div class="jaboo-titulo-painel"><h2>Simulações salvas</h2><span class="jaboo-contagem"><?= count($simulacoes) ?> de até 30 registros</span></div>
        <div class="jaboo-tabela-responsiva"><table class="jaboo-tabela"><thead><tr><th>Nome</th><th>Prazo</th><th>Taxa mensal</th><th>Total aportado</th><th>Saldo estimado</th><th>Ações</th></tr></thead><tbody>
        <?php if (!$simulacoes): ?><tr><td colspan="6" class="jaboo-vazio">Você ainda não salvou simulações.</td></tr><?php endif; ?>
        <?php foreach ($simulacoes as $sim): ?><tr><td><strong><?= jaboo_tela_escape($sim['nome']) ?></strong><small class="jaboo-subtexto"><?= date('d/m/Y', strtotime($sim['criado_em'])) ?></small></td><td><?= (int) $sim['periodo_meses'] ?> meses</td><td><?= number_format((float) $sim['taxa_juros_mensal'], 4, ',', '.') ?>%</td><td><?= jaboo_tela_moeda($sim['total_aportado']) ?></td><td><?= jaboo_tela_moeda($sim['valor_final_estimado']) ?></td><td class="jaboo-acoes-tabela"><a href="index.php?pagina=simulacoes&ver=<?= (int) $sim['id'] ?>">Visualizar</a><form method="post" class="jaboo-form-inline" onsubmit="return confirm('Excluir esta simulação?')"><?= jaboo_tela_csrf_input() ?><input type="hidden" name="acao" value="excluir_simulacao"><input type="hidden" name="id" value="<?= (int) $sim['id'] ?>"><button class="jaboo-link-botao jaboo-link-perigo" type="submit">Excluir</button></form></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    <p class="jaboo-nota">A simulação considera capitalização mensal e aportes feitos ao final de cada mês. É uma estimativa matemática, não uma promessa de rendimento, recomendação de investimento ou alteração dos seus registros reais.</p>
</div>
