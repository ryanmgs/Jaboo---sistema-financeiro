<?php
// Garante que as informações da página estejam disponíveis.
if (!isset($pagina) || !is_array($pagina)) {
    $pagina = [];
}

// Valores padrão caso alguma informação não tenha sido definida.
$pagina['titulo'] = $pagina['titulo'] ?? 'Visão geral';
$pagina['subtitulo'] = $pagina['subtitulo'] ?? 'Seu resumo financeiro em um só lugar.';
?>


<header class="topbar">

    <div class="topbar-left">

        <button
            type="button"
            class="menu-toggle"
            id="menuToggle"
            aria-label="Abrir menu"
        >
            ☰
        </button>

        <div>

            <h1>
                <?= htmlspecialchars(
                    $pagina['titulo'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h1>

            <p>
                <?= htmlspecialchars(
                    $pagina['subtitulo'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

        </div>

    </div>


    <div class="topbar-actions">

        <!-- ÚNICO BOTÃO DE NOVA TRANSAÇÃO -->

        <a
            href="index.php?pagina=transacoes&novo=1#formulario-transacao"
            class="primary-button"
        >

            <span>+</span>

            Nova transação

        </a>


        <!-- NOTIFICAÇÕES -->

        <button
            type="button"
            class="notification-button"
            title="Notificações em desenvolvimento"
            aria-label="Notificações"
            disabled
        >

            <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
                stroke-linecap="round"
                stroke-linejoin="round"
            >

                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>

                <path d="M10 21h4"/>

            </svg>

        </button>

    </div>

</header>
