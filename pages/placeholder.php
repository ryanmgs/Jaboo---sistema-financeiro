
<?php

if (basename($_SERVER["SCRIPT_FILENAME"]) === basename(__FILE__)) {
    header("Location: ../login.php");
    exit;
}

$tituloPlaceholder = $pagina["titulo"] ?? "Página";
$subtituloPlaceholder = $pagina["subtitulo"] ?? "Área do JABOO";

$paginaAtual = $paginaAtual ?? "inicio";

$tituloPlaceholder = htmlspecialchars(
    $tituloPlaceholder,
    ENT_QUOTES,
    "UTF-8"
);

$subtituloPlaceholder = htmlspecialchars(
    $subtituloPlaceholder,
    ENT_QUOTES,
    "UTF-8"
);

$descricoes = [

    "transacoes" =>
        "Aqui você poderá registrar e consultar suas receitas e despesas, acompanhar pagamentos, parcelamentos e movimentações recorrentes.",

    "contas" =>
        "Nesta área você poderá cadastrar suas contas, acompanhar saldos e consultar o histórico de movimentações.",

    "orcamentos" =>
        "Aqui você poderá definir limites de gastos, acompanhar despesas por categoria e organizar seu orçamento mensal.",

    "metas" =>
        "Nesta seção você poderá criar objetivos financeiros, acompanhar seu progresso e registrar contribuições.",

    "investimentos" =>
        "Aqui você poderá organizar sua carteira de investimentos e acompanhar sua evolução financeira.",

    "patrimonio" =>
        "Nesta área você poderá visualizar seus recursos financeiros e acompanhar a evolução do seu patrimônio.",

    "relatorios" =>
        "Aqui você poderá consultar análises das suas receitas, despesas e movimentações financeiras.",

    "simulacoes" =>
        "Nesta seção você poderá simular cenários financeiros e planejar seus próximos objetivos.",

    "configuracoes" =>
        "Aqui você poderá personalizar sua conta, preferências e experiência de utilização do JABOO."

];

$descricao = $descricoes[$paginaAtual] ??
    "Esta funcionalidade estará disponível em breve.";

?>

<div class="jaboo-placeholder">

    <div class="jaboo-placeholder-card">

        <div class="jaboo-placeholder-icon">
            <span>J</span>
        </div>

        <span class="jaboo-eyebrow">
            JABOO
        </span>

        <h2>
            <?= $tituloPlaceholder ?>
        </h2>

        <p class="jaboo-placeholder-subtitle">
            <?= $subtituloPlaceholder ?>
        </p>

        <div class="jaboo-placeholder-divider"></div>

        <h3>
            Estamos preparando esta área.
        </h3>

        <p class="jaboo-placeholder-description">
            <?= htmlspecialchars(
                $descricao,
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </p>

        <span class="jaboo-coming-soon">
            EM DESENVOLVIMENTO
        </span>

        <a href="index.php?pagina=inicio" class="jaboo-button">
            Voltar à visão geral
        </a>

    </div>

</div>
