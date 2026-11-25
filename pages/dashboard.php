
<?php

// ==========================================
// JABOO - VISÃO GERAL
// ==========================================

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/database.php";

jaboo_exigir_login();

$usuarioId = (int) $_SESSION["usuario_id"];


// ==========================================
// FORMATAÇÃO MONETÁRIA
// ==========================================

function jaboo_formatar_moeda_dashboard($valor): string {

    $valor = (string) $valor;

    $negativo = substr($valor, 0, 1) === "-";

    $valor = ltrim($valor, "-");

    $partes = explode(".", $valor, 2);

    $inteiros = preg_replace('/\D/', "", $partes[0]);

    $centavos = preg_replace(
        '/\D/',
        "",
        $partes[1] ?? "00"
    );

    $inteiros = $inteiros !== "" ? $inteiros : "0";

    $centavos = substr(str_pad($centavos, 2, "0"), 0, 2);

    $inteiros = preg_replace(
        '/\B(?=(\d{3})+(?!\d))/',
        ".",
        $inteiros
    );

    return ($negativo ? "-" : "") .
        $inteiros . "," . $centavos;

}


// ==========================================
// DADOS DO USUÁRIO
// ==========================================

$nomeUsuario = htmlspecialchars(
    $_SESSION["usuario_nome"] ?? "Usuário",
    ENT_QUOTES,
    "UTF-8"
);


// ==========================================
// PERÍODO DO MÊS ATUAL
// ==========================================

$primeiroDiaMes = date("Y-m-01");

$ultimoDiaMes = date("Y-m-t");


// ==========================================
// RECEITAS E DESPESAS DO MÊS
// ==========================================

$resumoConsulta = $conexao->prepare(

    "SELECT

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'receita'
                    AND status = 'efetivada'
                    THEN valor
                    ELSE 0
                END
            ),
            0
        ) AS receitas,

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'despesa'
                    AND status = 'efetivada'
                    THEN valor
                    ELSE 0
                END
            ),
            0
        ) AS despesas

     FROM transacoes

     WHERE usuario_id = ?

     AND data_competencia BETWEEN ? AND ?"

);

$resumoConsulta->bind_param(
    "iss",
    $usuarioId,
    $primeiroDiaMes,
    $ultimoDiaMes
);

$resumoConsulta->execute();

$resumoMes = $resumoConsulta
    ->get_result()
    ->fetch_assoc();

$resumoConsulta->close();


$receitasMes = jaboo_formatar_moeda_dashboard(
    $resumoMes["receitas"]
);

$despesasMes = jaboo_formatar_moeda_dashboard(
    $resumoMes["despesas"]
);


// ==========================================
// PATRIMÔNIO EM CONTAS
// ==========================================

$patrimonioConsulta = $conexao->prepare(

    "SELECT

        COALESCE(
            SUM(
                c.saldo_inicial
                + COALESCE(m.total_receitas, 0)
                - COALESCE(m.total_despesas, 0)
            ),
            0
        ) AS patrimonio

     FROM contas c

     LEFT JOIN (

        SELECT
            usuario_id,
            conta_id,

            SUM(
                CASE
                    WHEN tipo = 'receita' THEN valor
                    ELSE 0
                END
            ) AS total_receitas,

            SUM(
                CASE
                    WHEN tipo = 'despesa' THEN valor
                    ELSE 0
                END
            ) AS total_despesas

        FROM transacoes

        WHERE status = 'efetivada'

        GROUP BY usuario_id, conta_id

     ) m

        ON m.usuario_id = c.usuario_id
        AND m.conta_id = c.id

     WHERE c.usuario_id = ?

     AND c.ativo = 1

     AND c.incluir_no_patrimonio = 1"

);

$patrimonioConsulta->bind_param(
    "i",
    $usuarioId
);

$patrimonioConsulta->execute();

$patrimonioResultado = $patrimonioConsulta
    ->get_result()
    ->fetch_assoc();

$patrimonioConsulta->close();


$patrimonioContas = jaboo_formatar_moeda_dashboard(
    $patrimonioResultado["patrimonio"]
);


// ==========================================
// MOVIMENTAÇÕES RECENTES
// ==========================================

$consultaRecentes = $conexao->prepare(

    "SELECT

        t.id,
        t.descricao,
        t.tipo,
        t.valor,
        t.status,
        t.data_competencia,

        c.nome AS conta_nome,

        cat.nome AS categoria_nome

     FROM transacoes t

     INNER JOIN contas c

        ON c.id = t.conta_id
        AND c.usuario_id = t.usuario_id

     LEFT JOIN categorias cat

        ON cat.id = t.categoria_id
        AND cat.usuario_id = t.usuario_id

     WHERE t.usuario_id = ?

     ORDER BY
        t.data_competencia DESC,
        t.id DESC

     LIMIT 5"

);

$consultaRecentes->bind_param(
    "i",
    $usuarioId
);

$consultaRecentes->execute();

$resultadoRecentes = $consultaRecentes->get_result();

$movimentacoesRecentes = [];

while ($movimentacao = $resultadoRecentes->fetch_assoc()) {

    $movimentacoesRecentes[] = $movimentacao;

}

$consultaRecentes->close();


// ==========================================
// NOMES DOS STATUS
// ==========================================

$statusNomes = [

    "efetivada" => "Efetivada",
    "pendente" => "Pendente",
    "cancelada" => "Cancelada"

];

?>

<div class="jaboo-dashboard">


    <!-- =================================
         BOAS-VINDAS
    ================================== -->

    <section class="jaboo-welcome">

        <div class="jaboo-welcome-content">

            <span class="jaboo-eyebrow">
                SEU ESPAÇO FINANCEIRO
            </span>

            <h2>
                Olá, <?= $nomeUsuario ?>!
            </h2>

            <p>
                Aqui você acompanha e organiza sua vida financeira.
            </p>

        </div>

        <a
            href="index.php?pagina=transacoes"
            class="jaboo-button"
        >
            Ver transações
        </a>

    </section>


    <!-- =================================
         RESUMO FINANCEIRO
    ================================== -->

    <section class="jaboo-summary">

        <div class="jaboo-section-heading">

            <div>

                <h3>Resumo financeiro</h3>

                <p>
                    Acompanhe os principais indicadores da sua conta.
                </p>

            </div>

        </div>


        <div class="jaboo-summary-grid">


            <!-- PATRIMÔNIO -->

            <article class="jaboo-summary-card">

                <span class="jaboo-card-label">
                    Patrimônio em contas
                </span>

                <strong class="jaboo-card-value">

                    R$ <?= $patrimonioContas ?>

                </strong>

                <span class="jaboo-card-description">
                    Contas ativas incluídas no patrimônio
                </span>

            </article>


            <!-- RECEITAS -->

            <article class="jaboo-summary-card">

                <span class="jaboo-card-label">
                    Receitas do mês
                </span>

                <strong class="jaboo-card-value jaboo-green">

                    R$ <?= $receitasMes ?>

                </strong>

                <span class="jaboo-card-description">
                    Entradas efetivadas neste mês
                </span>

            </article>


            <!-- DESPESAS -->

            <article class="jaboo-summary-card">

                <span class="jaboo-card-label">
                    Despesas do mês
                </span>

                <strong class="jaboo-card-value jaboo-red">

                    R$ <?= $despesasMes ?>

                </strong>

                <span class="jaboo-card-description">
                    Saídas efetivadas neste mês
                </span>

            </article>

        </div>


        <p class="jaboo-data-notice">
            Os valores são calculados com base nas transações efetivadas.
            Transações pendentes e canceladas não alteram os saldos.
            O patrimônio apresentado ainda não inclui investimentos.
        </p>

    </section>


    <!-- =================================
         CONTEÚDO INFERIOR
    ================================== -->

    <div class="jaboo-dashboard-grid">


        <!-- MOVIMENTAÇÕES RECENTES -->

        <section class="jaboo-panel jaboo-transactions-panel">

            <div class="jaboo-panel-header">

                <div>

                    <h3>Movimentações recentes</h3>

                    <p>
                        Suas últimas receitas e despesas.
                    </p>

                </div>

                <a href="index.php?pagina=transacoes">
                    Ver todas
                </a>

            </div>


            <?php if (count($movimentacoesRecentes) > 0): ?>

                <div class="transaction-list">

                    <?php foreach ($movimentacoesRecentes as $movimentacao): ?>

                        <?php

                        $tipoMovimentacao = $movimentacao["tipo"];

                        $classeMovimentacao = $tipoMovimentacao === "receita"
                            ? "positive"
                            : "negative";

                        $sinal = $tipoMovimentacao === "receita"
                            ? "+"
                            : "−";

                        $icone = $tipoMovimentacao === "receita"
                            ? "↑"
                            : "↓";

                        $valorFormatado = jaboo_formatar_moeda_dashboard(
                            $movimentacao["valor"]
                        );

                        $dataFormatada = date(
                            "d/m/Y",
                            strtotime($movimentacao["data_competencia"])
                        );

                        $categoriaNome = $movimentacao["categoria_nome"]
                            ?: "Sem categoria";

                        $statusNome = $statusNomes[$movimentacao["status"]]
                            ?? "Desconhecido";

                        ?>

                        <div class="transaction-item">

                            <div class="transaction-icon">

                                <?= $icone ?>

                            </div>


                            <div class="transaction-info">

                                <strong>

                                    <?= htmlspecialchars(
                                        $movimentacao["descricao"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </strong>

                                <span>

                                    <?= htmlspecialchars(
                                        $movimentacao["conta_nome"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                    ·

                                    <?= htmlspecialchars(
                                        $categoriaNome,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                    ·

                                    <?= $dataFormatada ?>

                                    ·

                                    <?= $statusNome ?>

                                </span>

                            </div>


                            <strong class="<?= $classeMovimentacao ?>">

                                <?= $sinal ?> R$ <?= $valorFormatado ?>

                            </strong>

                        </div>

                    <?php endforeach; ?>

                </div>


            <?php else: ?>

                <div class="jaboo-empty-state">

                    <div class="jaboo-empty-icon">
                        +
                    </div>

                    <h4>Nenhuma movimentação encontrada</h4>

                    <p>
                        Quando você registrar receitas ou despesas,
                        elas aparecerão aqui.
                    </p>

                    <a href="index.php?pagina=transacoes&novo=1#formulario-transacao">
                        Registrar primeira transação
                    </a>

                </div>

            <?php endif; ?>

        </section>


        <!-- COLUNA LATERAL -->

        <div class="jaboo-side-panels">


            <!-- ORÇAMENTOS -->

            <section class="jaboo-panel">

                <div class="jaboo-panel-header">

                    <div>

                        <h3>Orçamentos</h3>

                        <p>
                            Controle seus gastos.
                        </p>

                    </div>

                    <a href="index.php?pagina=orcamentos">
                        Ver
                    </a>

                </div>

                <div class="jaboo-simple-empty">

                    <p>
                        Seus orçamentos aparecerão aqui quando
                        você começar a criá-los.
                    </p>

                </div>

            </section>


            <!-- METAS -->

            <section class="jaboo-panel">

                <div class="jaboo-panel-header">

                    <div>

                        <h3>Metas financeiras</h3>

                        <p>
                            Acompanhe seus objetivos.
                        </p>

                    </div>

                    <a href="index.php?pagina=metas">
                        Ver
                    </a>

                </div>

                <div class="jaboo-simple-empty">

                    <p>
                        Suas metas financeiras aparecerão aqui
                        quando forem cadastradas.
                    </p>

                </div>

            </section>


        </div>

    </div>

</div>
