
<?php

// Permite redirecionamentos mesmo quando os includes já geraram HTML
ob_start();

require_once __DIR__ . "/includes/auth.php";

jaboo_exigir_login();

// ==========================================
// CAMINHOS DOS ARQUIVOS
// ==========================================

$baseUrl = rtrim(
    str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')),
    '/'
);

$cssArquivo = __DIR__ . '/assets/style.css';
$jsArquivo = __DIR__ . '/assets/script.js';

$cssVersao = is_file($cssArquivo)
    ? (string) filemtime($cssArquivo)
    : '1';

$jsVersao = is_file($jsArquivo)
    ? (string) filemtime($jsArquivo)
    : '1';

$cssUrl = $baseUrl . '/assets/style.css?v=' . $cssVersao;
$jsUrl = $baseUrl . '/assets/script.js?v=' . $jsVersao;


// ==========================================
// PÁGINAS DISPONÍVEIS
// ==========================================

$paginas = [

    'inicio' => [
        'titulo' => 'Visão geral',
        'subtitulo' => 'Acompanhe sua vida financeira.',
        'arquivo' => 'pages/dashboard.php'
    ],

    'transacoes' => [
        'titulo' => 'Transações',
        'subtitulo' => 'Organize suas receitas e despesas.',
        'arquivo' => 'pages/transacoes.php'
    ],

    'contas' => [
        'titulo' => 'Contas',
        'subtitulo' => 'Acompanhe os saldos das suas contas.',
        'arquivo' => 'pages/contas.php'
    ],

    'orcamentos' => [
        'titulo' => 'Orçamentos',
        'subtitulo' => 'Planeje seus gastos mensais.',
        'arquivo' => 'pages/orcamentos.php'
    ],

    'metas' => [
        'titulo' => 'Metas financeiras',
        'subtitulo' => 'Acompanhe seus objetivos e organize suas economias.',
        'arquivo' => 'pages/metas.php'
    ],

    'investimentos' => [
        'titulo' => 'Investimentos',
        'subtitulo' => 'Acompanhe sua carteira de investimentos.',
        'arquivo' => 'pages/investimentos.php'
    ],

    'patrimonio' => [
        'titulo' => 'Patrimônio',
        'subtitulo' => 'Visualize seu patrimônio financeiro.',
        'arquivo' => 'pages/patrimonio.php'
    ],

    'relatorios' => [
        'titulo' => 'Relatórios',
        'subtitulo' => 'Analise suas movimentações financeiras.',
        'arquivo' => 'pages/relatorios.php'
    ],

    'simulacoes' => [
        'titulo' => 'Simulações',
        'subtitulo' => 'Explore diferentes cenários financeiros.',
        'arquivo' => 'pages/simulacoes.php'
    ],

    'configuracoes' => [
        'titulo' => 'Configurações',
        'subtitulo' => 'Personalize sua experiência no JABOO.',
        'arquivo' => 'pages/configuracoes.php'
    ],

    'categorias' => [
        'titulo' => 'Categorias',
        'subtitulo' => 'Organize suas categorias financeiras.',
        'arquivo' => 'pages/categorias.php'
    ]

];


// ==========================================
// IDENTIFICA A PÁGINA SOLICITADA
// ==========================================

$paginaSolicitada = $_GET['pagina'] ?? 'inicio';

if (
    !is_string($paginaSolicitada) ||
    !isset($paginas[$paginaSolicitada])
) {
    $paginaSolicitada = 'inicio';
}

$paginaAtual = $paginaSolicitada;
$pagina = $paginas[$paginaAtual];

$arquivoPagina = __DIR__ . '/' . $pagina['arquivo'];

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($pagina['titulo'], ENT_QUOTES, 'UTF-8') ?> | JABOO
    </title>

    <!-- CSS PRINCIPAL: CARREGADO UMA ÚNICA VEZ -->

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') ?>"
    >

</head>

<body>

    <div class="app-container">

        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">

            <?php include __DIR__ . '/includes/topbar.php'; ?>

            <section class="page-content">

                <?php

                if (is_file($arquivoPagina)) {

                    include $arquivoPagina;

                } else {

                    echo '<div class="alerta">
                        A página solicitada não foi encontrada.
                    </div>';

                }

                ?>

            </section>

            <footer class="footer">
                JABOO · Organização financeira simplificada
            </footer>

        </main>

    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- JAVASCRIPT PRINCIPAL -->

    <script src="<?= htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8') ?>"></script>

</body>

</html>
