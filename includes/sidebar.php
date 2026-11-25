<?php
$paginaAtual = $paginaAtual ?? (
    isset($_GET['pagina']) && is_string($_GET['pagina'])
        ? $_GET['pagina']
        : 'inicio'
);
?>



<aside class="sidebar" id="sidebar">

    <div class="logo-area">
        <div class="logo-icon">J</div>
        <span class="logo-name">JABOO</span>
    </div>

    <nav class="sidebar-navigation">

        <div class="nav-group">

            <span class="nav-title">PRINCIPAL</span>

            <a href="index.php?pagina=inicio"
               class="nav-link <?= $paginaAtual === 'inicio' ? 'active' : '' ?>">

                <span class="nav-icon">⌂</span>
                <span>Visão geral</span>

            </a>

            <a href="index.php?pagina=transacoes"
               class="nav-link <?= $paginaAtual === 'transacoes' ? 'active' : '' ?>">

                <span class="nav-icon">⇄</span>
                <span>Transações</span>

            </a>

            <a href="index.php?pagina=contas"
               class="nav-link <?= $paginaAtual === 'contas' ? 'active' : '' ?>">

                <span class="nav-icon">▣</span>
                <span>Contas</span>

            </a>

        </div>


        <div class="nav-group">

            <span class="nav-title">PLANEJAMENTO</span>

            <a href="index.php?pagina=orcamentos"
               class="nav-link <?= $paginaAtual === 'orcamentos' ? 'active' : '' ?>">

                <span class="nav-icon">◷</span>
                <span>Orçamentos</span>

            </a>

            <a href="index.php?pagina=metas"
               class="nav-link <?= $paginaAtual === 'metas' ? 'active' : '' ?>">

                <span class="nav-icon">◎</span>
                <span>Metas financeiras</span>

            </a>

        </div>


        <div class="nav-group">

            <span class="nav-title">PATRIMÔNIO</span>

            <a href="index.php?pagina=investimentos"
               class="nav-link <?= $paginaAtual === 'investimentos' ? 'active' : '' ?>">

                <span class="nav-icon">◇</span>
                <span>Investimentos</span>

            </a>

            <a href="index.php?pagina=patrimonio"
               class="nav-link <?= $paginaAtual === 'patrimonio' ? 'active' : '' ?>">

                <span class="nav-icon">▤</span>
                <span>Patrimônio</span>

            </a>

        </div>


        <div class="nav-group">

            <span class="nav-title">ANÁLISES</span>

            <a href="index.php?pagina=relatorios"
               class="nav-link <?= $paginaAtual === 'relatorios' ? 'active' : '' ?>">

                <span class="nav-icon">▥</span>
                <span>Relatórios</span>

            </a>

            <a href="index.php?pagina=simulacoes"
               class="nav-link <?= $paginaAtual === 'simulacoes' ? 'active' : '' ?>">

                <span class="nav-icon">⌁</span>
                <span>Simulações</span>

            </a>

        </div>

    </nav>


    <div class="sidebar-bottom">

        <a href="index.php?pagina=configuracoes"
           class="nav-link <?= $paginaAtual === 'configuracoes' ? 'active' : '' ?>">

            <span class="nav-icon">⚙</span>
            <span>Configurações</span>

        </a>

        <div class="profile-area">

            <div class="profile-avatar">U</div>

            <div class="profile-info">
                <strong>Minha conta</strong>
                <span>Usuário JABOO</span>
            </div>

        </div>

    </div>

</aside>
