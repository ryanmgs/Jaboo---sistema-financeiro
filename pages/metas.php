
<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/database.php";

jaboo_exigir_login();

/* =========================================
   IDENTIFICAÇÃO DO USUÁRIO
========================================= */

$usuarioLogado = jaboo_usuario_logado();

$usuarioId = 0;

if (is_array($usuarioLogado)) {
    $usuarioId = (int) (
        $usuarioLogado["id"]
        ?? $usuarioLogado["usuario_id"]
        ?? 0
    );
} elseif (is_object($usuarioLogado)) {
    $usuarioId = (int) (
        $usuarioLogado->id
        ?? $usuarioLogado->usuario_id
        ?? 0
    );
} elseif (is_numeric($usuarioLogado)) {
    $usuarioId = (int) $usuarioLogado;
}

if ($usuarioId <= 0) {
    $usuarioSessao = $_SESSION["usuario"] ?? [];

    if (is_array($usuarioSessao)) {
        $usuarioId = (int) (
            $_SESSION["usuario_id"]
            ?? $usuarioSessao["id"]
            ?? 0
        );
    } else {
        $usuarioId = (int) ($_SESSION["usuario_id"] ?? 0);
    }
}

if ($usuarioId <= 0) {
    die("Não foi possível identificar o usuário.");
}


/* =========================================
   FUNÇÕES AUXILIARES
========================================= */

function jaboo_meta_escape($valor)
{
    if (function_exists("jaboo_escape")) {
        return jaboo_escape((string) $valor);
    }

    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

function jaboo_meta_moeda($valor)
{
    return "R$ " . number_format(
        (float) $valor,
        2,
        ",",
        "."
    );
}

function jaboo_meta_formatar_valor($valor)
{
    return number_format(
        (float) $valor,
        2,
        ",",
        "."
    );
}

function jaboo_meta_converter_moeda($valor)
{
    $valor = trim((string) $valor);

    $valor = str_replace(
        ["R$", " "],
        "",
        $valor
    );

    $padrao = '/^(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$/';

    if (!preg_match($padrao, $valor)) {
        return false;
    }

    $valor = str_replace(".", "", $valor);
    $valor = str_replace(",", ".", $valor);

    if (!is_numeric($valor)) {
        return false;
    }

    $numero = (float) $valor;

    if (
        $numero <= 0 ||
        $numero > 9999999999999.99
    ) {
        return false;
    }

    return number_format($numero, 2, ".", "");
}

function jaboo_meta_data_valida($data)
{
    if (!$data) {
        return false;
    }

    $objetoData = DateTime::createFromFormat(
        "!Y-m-d",
        $data
    );

    return (
        $objetoData &&
        $objetoData->format("Y-m-d") === $data
    );
}

function jaboo_meta_data_br($data)
{
    if (!$data) {
        return "Não definida";
    }

    return date("d/m/Y", strtotime($data));
}

function jaboo_meta_redirecionar($mensagem)
{
    header(
        "Location: index.php?pagina=metas&mensagem=" .
        urlencode($mensagem)
    );

    exit;
}

function jaboo_meta_obter($conexao, $metaId, $usuarioId)
{
    $stmt = $conexao->prepare("
        SELECT *
        FROM metas_financeiras
        WHERE id = ?
        AND usuario_id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "ii",
        $metaId,
        $usuarioId
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $meta = $resultado->fetch_assoc();

    $stmt->close();

    return $meta;
}


/* =========================================
   PROCESSAMENTO DOS FORMULÁRIOS
========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    jaboo_validar_csrf();

    $acao = $_POST["acao"] ?? "";


    /* =====================================
       CRIAR META
    ===================================== */

    if ($acao === "criar_meta") {

        $nome = trim($_POST["nome"] ?? "");
        $descricao = trim($_POST["descricao"] ?? "");

        $valorObjetivo = jaboo_meta_converter_moeda(
            $_POST["valor_objetivo"] ?? ""
        );

        $dataInicio = $_POST["data_inicio"] ?? "";
        $dataLimite = $_POST["data_limite"] ?? "";

        $icone = trim($_POST["icone"] ?? "");
        $cor = $_POST["cor"] ?? "#2E7D32";

        if (
            $nome === "" ||
            strlen($nome) > 150 ||
            $valorObjetivo === false ||
            !jaboo_meta_data_valida($dataInicio)
        ) {
            jaboo_meta_redirecionar("dados_invalidos");
        }

        if (
            $dataLimite !== "" &&
            (
                !jaboo_meta_data_valida($dataLimite) ||
                $dataLimite < $dataInicio
            )
        ) {
            jaboo_meta_redirecionar("prazo_invalido");
        }

        if ($descricao === "") {
            $descricao = null;
        }

        if ($icone === "") {
            $icone = null;
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor)) {
            $cor = "#2E7D32";
        }

        $dataLimiteBanco = (
            $dataLimite !== ""
            ? $dataLimite
            : null
        );

        $stmt = $conexao->prepare("
            INSERT INTO metas_financeiras (
                usuario_id,
                nome,
                descricao,
                valor_objetivo,
                valor_atual,
                data_inicio,
                data_limite,
                icone,
                cor,
                status
            ) VALUES (
                ?, ?, ?, ?, 0.00, ?, ?, ?, ?, 'ativa'
            )
        ");

        $stmt->bind_param(
            "isssssss",
            $usuarioId,
            $nome,
            $descricao,
            $valorObjetivo,
            $dataInicio,
            $dataLimiteBanco,
            $icone,
            $cor
        );

        if (!$stmt->execute()) {
            $stmt->close();
            jaboo_meta_redirecionar("erro");
        }

        $stmt->close();

        jaboo_meta_redirecionar("meta_criada");
    }


    /* =====================================
       EDITAR META
    ===================================== */

    if ($acao === "editar_meta") {

        $metaId = (int) ($_POST["meta_id"] ?? 0);

        $nome = trim($_POST["nome"] ?? "");
        $descricao = trim($_POST["descricao"] ?? "");

        $valorObjetivo = jaboo_meta_converter_moeda(
            $_POST["valor_objetivo"] ?? ""
        );

        $dataInicio = $_POST["data_inicio"] ?? "";
        $dataLimite = $_POST["data_limite"] ?? "";

        $icone = trim($_POST["icone"] ?? "");
        $cor = $_POST["cor"] ?? "#2E7D32";

        $metaAtual = jaboo_meta_obter(
            $conexao,
            $metaId,
            $usuarioId
        );

        if (
            !$metaAtual ||
            $nome === "" ||
            strlen($nome) > 150 ||
            $valorObjetivo === false ||
            !jaboo_meta_data_valida($dataInicio)
        ) {
            jaboo_meta_redirecionar("dados_invalidos");
        }

        if (
            $dataLimite !== "" &&
            (
                !jaboo_meta_data_valida($dataLimite) ||
                $dataLimite < $dataInicio
            )
        ) {
            jaboo_meta_redirecionar("prazo_invalido");
        }

        if ($descricao === "") {
            $descricao = null;
        }

        if ($icone === "") {
            $icone = null;
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor)) {
            $cor = "#2E7D32";
        }

        $dataLimiteBanco = (
            $dataLimite !== ""
            ? $dataLimite
            : null
        );

        $novoStatus = $metaAtual["status"];

        $valorAtual = (float) $metaAtual["valor_atual"];

        if ($valorAtual >= (float) $valorObjetivo) {
            $novoStatus = "concluida";
        } elseif ($novoStatus === "concluida") {
            $novoStatus = "ativa";
        }

        $stmt = $conexao->prepare("
            UPDATE metas_financeiras
            SET
                nome = ?,
                descricao = ?,
                valor_objetivo = ?,
                data_inicio = ?,
                data_limite = ?,
                icone = ?,
                cor = ?,
                status = ?
            WHERE id = ?
            AND usuario_id = ?
        ");

        $stmt->bind_param(
            "ssssssssii",
            $nome,
            $descricao,
            $valorObjetivo,
            $dataInicio,
            $dataLimiteBanco,
            $icone,
            $cor,
            $novoStatus,
            $metaId,
            $usuarioId
        );

        if (!$stmt->execute()) {
            $stmt->close();
            jaboo_meta_redirecionar("erro");
        }

        $stmt->close();

        jaboo_meta_redirecionar("meta_editada");
    }


    /* =====================================
       REGISTRAR APORTE
    ===================================== */

    if ($acao === "adicionar_aporte") {

        $metaId = (int) ($_POST["meta_id"] ?? 0);

        $valorAporte = jaboo_meta_converter_moeda(
            $_POST["valor"] ?? ""
        );

        $dataAporte = $_POST["data_aporte"] ?? "";
        $descricaoAporte = trim(
            $_POST["descricao"] ?? ""
        );

        if (
            $valorAporte === false ||
            !jaboo_meta_data_valida($dataAporte)
        ) {
            jaboo_meta_redirecionar("aporte_invalido");
        }

        if ($descricaoAporte === "") {
            $descricaoAporte = null;
        }

        $conexao->begin_transaction();

        try {

            $stmt = $conexao->prepare("
                SELECT
                    valor_objetivo,
                    valor_atual,
                    data_inicio,
                    status
                FROM metas_financeiras
                WHERE id = ?
                AND usuario_id = ?
                FOR UPDATE
            ");

            $stmt->bind_param(
                "ii",
                $metaId,
                $usuarioId
            );

            $stmt->execute();

            $meta = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$meta) {
                throw new Exception("meta_inexistente");
            }

            if ($meta["status"] !== "ativa") {
                throw new Exception("meta_inativa");
            }

            if ($dataAporte < $meta["data_inicio"]) {
                throw new Exception("data_aporte_invalida");
            }

            $stmt = $conexao->prepare("
                INSERT INTO aportes_meta (
                    usuario_id,
                    meta_id,
                    valor,
                    data_aporte,
                    descricao
                ) VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iisss",
                $usuarioId,
                $metaId,
                $valorAporte,
                $dataAporte,
                $descricaoAporte
            );

            if (!$stmt->execute()) {
                throw new Exception("erro_aporte");
            }

            $stmt->close();

            /*
             * Atualiza o valor acumulado.
             * Ao atingir o objetivo, conclui a meta.
             */

            $stmt = $conexao->prepare("
                UPDATE metas_financeiras
                SET
                    valor_atual = valor_atual + ?,
                    status = CASE
                        WHEN valor_atual + ? >= valor_objetivo
                        THEN 'concluida'
                        ELSE status
                    END
                WHERE id = ?
                AND usuario_id = ?
            ");

            $stmt->bind_param(
                "ssii",
                $valorAporte,
                $valorAporte,
                $metaId,
                $usuarioId
            );

            if (!$stmt->execute()) {
                throw new Exception("erro_atualizacao");
            }

            $stmt->close();

            $conexao->commit();

            jaboo_meta_redirecionar("aporte_adicionado");

        } catch (Throwable $erro) {

            $conexao->rollback();

            if ($erro->getMessage() === "meta_inativa") {
                jaboo_meta_redirecionar("meta_inativa");
            }

            if ($erro->getMessage() === "data_aporte_invalida") {
                jaboo_meta_redirecionar("data_aporte_invalida");
            }

            if ($erro->getMessage() === "meta_inexistente") {
                jaboo_meta_redirecionar("meta_inexistente");
            }

            jaboo_meta_redirecionar("erro");
        }
    }


    /* =====================================
       EXCLUIR APORTE
    ===================================== */

    if ($acao === "excluir_aporte") {

        $aporteId = (int) ($_POST["aporte_id"] ?? 0);

        $conexao->begin_transaction();

        try {

            $stmt = $conexao->prepare("
                SELECT
                    a.valor,
                    a.meta_id,
                    m.valor_objetivo,
                    m.valor_atual,
                    m.status
                FROM aportes_meta a
                INNER JOIN metas_financeiras m
                    ON m.id = a.meta_id
                    AND m.usuario_id = a.usuario_id
                WHERE a.id = ?
                AND a.usuario_id = ?
                FOR UPDATE
            ");

            $stmt->bind_param(
                "ii",
                $aporteId,
                $usuarioId
            );

            $stmt->execute();

            $aporte = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$aporte) {
                throw new Exception("aporte_inexistente");
            }

            $metaId = (int) $aporte["meta_id"];
            $valorAporte = $aporte["valor"];

            $stmt = $conexao->prepare("
                DELETE FROM aportes_meta
                WHERE id = ?
                AND usuario_id = ?
            ");

            $stmt->bind_param(
                "ii",
                $aporteId,
                $usuarioId
            );

            if (!$stmt->execute()) {
                throw new Exception("erro_exclusao");
            }

            $stmt->close();

            /*
             * Subtrai o aporte removido.
             * Se a meta deixar de atingir o objetivo,
             * volta automaticamente para ativa.
             */

            $stmt = $conexao->prepare("
                UPDATE metas_financeiras
                SET
                    valor_atual = GREATEST(valor_atual - ?, 0),
                    status = CASE
                        WHEN status = 'concluida'
                        AND valor_atual - ? < valor_objetivo
                        THEN 'ativa'
                        ELSE status
                    END
                WHERE id = ?
                AND usuario_id = ?
            ");

            $stmt->bind_param(
                "ssii",
                $valorAporte,
                $valorAporte,
                $metaId,
                $usuarioId
            );

            if (!$stmt->execute()) {
                throw new Exception("erro_atualizacao");
            }

            $stmt->close();

            $conexao->commit();

            jaboo_meta_redirecionar("aporte_excluido");

        } catch (Throwable $erro) {

            $conexao->rollback();

            jaboo_meta_redirecionar("erro");
        }
    }


    /* =====================================
       ALTERAR STATUS DA META
    ===================================== */

    if ($acao === "alterar_status") {

        $metaId = (int) ($_POST["meta_id"] ?? 0);
        $novoStatusSolicitado = $_POST["novo_status"] ?? "";

        $meta = jaboo_meta_obter(
            $conexao,
            $metaId,
            $usuarioId
        );

        if (!$meta) {
            jaboo_meta_redirecionar("meta_inexistente");
        }

        $statusAtual = $meta["status"];
        $statusFinal = null;

        if (
            $novoStatusSolicitado === "pausada" &&
            $statusAtual === "ativa"
        ) {
            $statusFinal = "pausada";
        }

        elseif (
            $novoStatusSolicitado === "ativa" &&
            in_array($statusAtual, ["pausada", "cancelada"])
        ) {
            if (
                (float) $meta["valor_atual"] >=
                (float) $meta["valor_objetivo"]
            ) {
                $statusFinal = "concluida";
            } else {
                $statusFinal = "ativa";
            }
        }

        elseif (
            $novoStatusSolicitado === "cancelada" &&
            in_array($statusAtual, ["ativa", "pausada"])
        ) {
            $statusFinal = "cancelada";
        }

        if (!$statusFinal) {
            jaboo_meta_redirecionar("status_invalido");
        }

        $stmt = $conexao->prepare("
            UPDATE metas_financeiras
            SET status = ?
            WHERE id = ?
            AND usuario_id = ?
        ");

        $stmt->bind_param(
            "sii",
            $statusFinal,
            $metaId,
            $usuarioId
        );

        $stmt->execute();
        $stmt->close();

        jaboo_meta_redirecionar("status_atualizado");
    }
}


/* =========================================
   MENSAGENS DO SISTEMA
========================================= */

$mensagens = [
    "meta_criada" => [
        "tipo" => "sucesso",
        "texto" => "Meta criada com sucesso."
    ],

    "meta_editada" => [
        "tipo" => "sucesso",
        "texto" => "Meta atualizada com sucesso."
    ],

    "aporte_adicionado" => [
        "tipo" => "sucesso",
        "texto" => "Aporte registrado com sucesso."
    ],

    "aporte_excluido" => [
        "tipo" => "sucesso",
        "texto" => "Aporte removido com sucesso."
    ],

    "status_atualizado" => [
        "tipo" => "sucesso",
        "texto" => "Situação da meta atualizada."
    ],

    "dados_invalidos" => [
        "tipo" => "erro",
        "texto" => "Confira os dados informados."
    ],

    "prazo_invalido" => [
        "tipo" => "erro",
        "texto" => "A data limite precisa ser posterior à data de início."
    ],

    "aporte_invalido" => [
        "tipo" => "erro",
        "texto" => "Informe um valor e uma data válidos para o aporte."
    ],

    "data_aporte_invalida" => [
        "tipo" => "erro",
        "texto" => "A data do aporte não pode ser anterior ao início da meta."
    ],

    "meta_inativa" => [
        "tipo" => "erro",
        "texto" => "Só é possível registrar aportes em metas ativas."
    ],

    "meta_inexistente" => [
        "tipo" => "erro",
        "texto" => "A meta selecionada não foi encontrada."
    ],

    "status_invalido" => [
        "tipo" => "erro",
        "texto" => "Não foi possível alterar a situação desta meta."
    ],

    "erro" => [
        "tipo" => "erro",
        "texto" => "Ocorreu um erro ao processar a operação."
    ]
];

$mensagemAtual = $mensagens[
    $_GET["mensagem"] ?? ""
] ?? null;


/* =========================================
   META EM EDIÇÃO
========================================= */

$metaEdicao = null;

$editarId = (int) ($_GET["editar"] ?? 0);

if ($editarId > 0) {
    $metaEdicao = jaboo_meta_obter(
        $conexao,
        $editarId,
        $usuarioId
    );
}


/* =========================================
   META SELECIONADA PARA APORTES
========================================= */

$metaAporte = null;

$aporteMetaId = (int) ($_GET["aporte"] ?? 0);

if ($aporteMetaId > 0) {
    $metaAporte = jaboo_meta_obter(
        $conexao,
        $aporteMetaId,
        $usuarioId
    );
}


/* =========================================
   RESUMO GERAL
========================================= */

$stmt = $conexao->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'ativa') AS ativas,
        SUM(status = 'concluida') AS concluidas,
        COALESCE(SUM(valor_atual), 0) AS acumulado,
        COALESCE(
            SUM(
                CASE
                    WHEN status != 'cancelada'
                    THEN valor_objetivo
                    ELSE 0
                END
            ),
            0
        ) AS objetivos
    FROM metas_financeiras
    WHERE usuario_id = ?
");

$stmt->bind_param(
    "i",
    $usuarioId
);

$stmt->execute();

$resumo = $stmt->get_result()->fetch_assoc();

$stmt->close();


/* =========================================
   FILTRO DE METAS
========================================= */

$filtroStatus = $_GET["status"] ?? "todas";

$statusPermitidos = [
    "todas",
    "ativa",
    "pausada",
    "concluida",
    "cancelada"
];

if (!in_array($filtroStatus, $statusPermitidos)) {
    $filtroStatus = "todas";
}


/* =========================================
   LISTAGEM DAS METAS
========================================= */

if ($filtroStatus === "todas") {

    $stmt = $conexao->prepare("
        SELECT *
        FROM metas_financeiras
        WHERE usuario_id = ?
        ORDER BY
            CASE status
                WHEN 'ativa' THEN 1
                WHEN 'pausada' THEN 2
                WHEN 'concluida' THEN 3
                WHEN 'cancelada' THEN 4
            END,
            data_limite IS NULL,
            data_limite ASC,
            criado_em DESC
    ");

    $stmt->bind_param(
        "i",
        $usuarioId
    );

} else {

    $stmt = $conexao->prepare("
        SELECT *
        FROM metas_financeiras
        WHERE usuario_id = ?
        AND status = ?
        ORDER BY
            data_limite IS NULL,
            data_limite ASC,
            criado_em DESC
    ");

    $stmt->bind_param(
        "is",
        $usuarioId,
        $filtroStatus
    );
}

$stmt->execute();

$resultadoMetas = $stmt->get_result();

$metas = $resultadoMetas->fetch_all(MYSQLI_ASSOC);

$stmt->close();


/* =========================================
   HISTÓRICO DE APORTES
========================================= */

$historicoAportes = [];

if ($metaAporte) {

    $stmt = $conexao->prepare("
        SELECT *
        FROM aportes_meta
        WHERE meta_id = ?
        AND usuario_id = ?
        ORDER BY data_aporte DESC, id DESC
    ");

    $stmt->bind_param(
        "ii",
        $aporteMetaId,
        $usuarioId
    );

    $stmt->execute();

    $historicoAportes = $stmt
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
}


/* =========================================
   CONFIGURAÇÃO DOS FORMULÁRIOS
========================================= */

$mostrarFormularioNova = (
    isset($_GET["nova"]) &&
    $_GET["nova"] === "1"
);

$modoEdicao = (bool) $metaEdicao;

$valorFormulario = $metaEdicao
    ? jaboo_meta_formatar_valor(
        $metaEdicao["valor_objetivo"]
    )
    : "";

$dataFormularioInicio = $metaEdicao
    ? $metaEdicao["data_inicio"]
    : date("Y-m-d");

$dataFormularioLimite = $metaEdicao
    ? ($metaEdicao["data_limite"] ?? "")
    : "";

?>


<section class="metas-page">

    <!-- CABEÇALHO -->

    <div class="metas-cabecalho">

        <div>
            <h2>Metas financeiras</h2>

            <p>
                Organize seus objetivos e acompanhe seu progresso.
            </p>
        </div>

        <a
            href="index.php?pagina=metas&nova=1#formulario-meta"
            class="metas-botao-principal"
        >
            <span>+</span>
            Nova meta
        </a>

    </div>


    <!-- MENSAGEM -->

    <?php if ($mensagemAtual): ?>

        <div class="metas-alerta metas-alerta-<?= jaboo_meta_escape($mensagemAtual["tipo"]) ?>">
            <?= jaboo_meta_escape($mensagemAtual["texto"]) ?>
        </div>

    <?php endif; ?>


    <!-- RESUMO -->

    <div class="metas-resumo">

        <div class="metas-resumo-card">

            <span class="metas-resumo-label">
                Metas ativas
            </span>

            <strong>
                <?= (int) ($resumo["ativas"] ?? 0) ?>
            </strong>

            <small>
                Em andamento
            </small>

        </div>


        <div class="metas-resumo-card">

            <span class="metas-resumo-label">
                Valor acumulado
            </span>

            <strong>
                <?= jaboo_meta_moeda($resumo["acumulado"] ?? 0) ?>
            </strong>

            <small>
                Somatório dos valores atuais
            </small>

        </div>


        <div class="metas-resumo-card">

            <span class="metas-resumo-label">
                Objetivos cadastrados
            </span>

            <strong>
                <?= (int) ($resumo["total"] ?? 0) ?>
            </strong>

            <small>
                Incluindo todas as situações
            </small>

        </div>


        <div class="metas-resumo-card">

            <span class="metas-resumo-label">
                Metas concluídas
            </span>

            <strong>
                <?= (int) ($resumo["concluidas"] ?? 0) ?>
            </strong>

            <small>
                Objetivos alcançados
            </small>

        </div>

    </div>


    <!-- FORMULÁRIO DE CRIAÇÃO / EDIÇÃO -->

    <?php if ($mostrarFormularioNova || $metaEdicao): ?>

        <div class="metas-formulario" id="formulario-meta">

            <div class="metas-formulario-topo">

                <div>
                    <h3>
                        <?= $modoEdicao ? "Editar meta" : "Nova meta" ?>
                    </h3>

                    <p>
                        Preencha os dados do seu objetivo financeiro.
                    </p>
                </div>

                <a
                    href="index.php?pagina=metas"
                    class="metas-fechar"
                    title="Fechar"
                >
                    ×
                </a>

            </div>


            <form method="POST" action="index.php?pagina=metas#formulario-meta">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= jaboo_meta_escape(jaboo_csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="acao"
                    value="<?= $modoEdicao ? "editar_meta" : "criar_meta" ?>"
                >

                <?php if ($modoEdicao): ?>

                    <input
                        type="hidden"
                        name="meta_id"
                        value="<?= (int) $metaEdicao["id"] ?>"
                    >

                <?php endif; ?>


                <div class="metas-form-grid">

                    <div class="metas-campo metas-campo-largo">

                        <label>Nome da meta</label>

                        <input
                            type="text"
                            name="nome"
                            maxlength="150"
                            placeholder="Ex: Comprar um notebook"
                            value="<?= jaboo_meta_escape($metaEdicao["nome"] ?? "") ?>"
                            required
                        >

                    </div>


                    <div class="metas-campo">

                        <label>Valor desejado</label>

                        <div class="metas-input-moeda">
                            <span>R$</span>

                            <input
                                type="text"
                                name="valor_objetivo"
                                class="metas-valor-input"
                                data-metas-moeda
                                placeholder="0,00"
                                value="<?= jaboo_meta_escape($valorFormulario) ?>"
                                required
                            >
                        </div>

                    </div>


                    <div class="metas-campo">

                        <label>Data de início</label>

                        <input
                            type="date"
                            name="data_inicio"
                            value="<?= jaboo_meta_escape($dataFormularioInicio) ?>"
                            required
                        >

                    </div>


                    <div class="metas-campo">

                        <label>Data limite</label>

                        <input
                            type="date"
                            name="data_limite"
                            value="<?= jaboo_meta_escape($dataFormularioLimite) ?>"
                        >

                        <small>
                            Campo opcional.
                        </small>

                    </div>


                    <div class="metas-campo">

                        <label>Ícone</label>

                        <select name="icone">

                            <?php
                            $icones = [
                                "🎯" => "Objetivo",
                                "💻" => "Tecnologia",
                                "🏠" => "Casa",
                                "🚗" => "Veículo",
                                "✈️" => "Viagem",
                                "🎓" => "Estudos",
                                "💰" => "Economia",
                                "❤️" => "Pessoal"
                            ];

                            $iconeAtual = $metaEdicao["icone"] ?? "🎯";
                            ?>

                            <?php foreach ($icones as $icone => $nomeIcone): ?>

                                <option
                                    value="<?= jaboo_meta_escape($icone) ?>"
                                    <?= $iconeAtual === $icone ? "selected" : "" ?>
                                >
                                    <?= jaboo_meta_escape($icone) ?>
                                    <?= jaboo_meta_escape($nomeIcone) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="metas-campo">

                        <label>Cor da meta</label>

                        <input
                            type="color"
                            name="cor"
                            value="<?= jaboo_meta_escape($metaEdicao["cor"] ?? "#2E7D32") ?>"
                            class="metas-input-cor"
                        >

                    </div>


                    <div class="metas-campo metas-campo-largo">

                        <label>Descrição</label>

                        <textarea
                            name="descricao"
                            rows="3"
                            placeholder="Escreva detalhes sobre esse objetivo..."
                        ><?= jaboo_meta_escape($metaEdicao["descricao"] ?? "") ?></textarea>

                    </div>

                </div>


                <div class="metas-form-acoes">

                    <a
                        href="index.php?pagina=metas"
                        class="metas-botao-secundario"
                    >
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="metas-botao-principal"
                    >
                        <?= $modoEdicao ? "Salvar alterações" : "Criar meta" ?>
                    </button>

                </div>

            </form>

        </div>

    <?php endif; ?>


    <!-- FORMULÁRIO DE APORTE -->

    <?php if ($metaAporte): ?>

        <?php
        $objetivoAporte = (float) $metaAporte["valor_objetivo"];
        $atualAporte = (float) $metaAporte["valor_atual"];

        $progressoAporte = $objetivoAporte > 0
            ? min(100, ($atualAporte / $objetivoAporte) * 100)
            : 0;
        ?>

        <div class="metas-formulario" id="formulario-aporte">

            <div class="metas-formulario-topo">

                <div>
                    <h3>Registrar aporte</h3>

                    <p>
                        Meta:
                        <strong>
                            <?= jaboo_meta_escape($metaAporte["nome"]) ?>
                        </strong>
                    </p>
                </div>

                <a
                    href="index.php?pagina=metas"
                    class="metas-fechar"
                    title="Fechar"
                >
                    ×
                </a>

            </div>


            <div class="metas-aporte-progresso">

                <div class="metas-aporte-progresso-texto">
                    <span>Acumulado</span>

                    <strong>
                        <?= jaboo_meta_moeda($atualAporte) ?>
                        /
                        <?= jaboo_meta_moeda($objetivoAporte) ?>
                    </strong>
                </div>

                <div class="metas-progresso">
                    <div
                        class="metas-progresso-preenchido"
                        style="
                            width: <?= $progressoAporte ?>%;
                            background: <?= jaboo_meta_escape($metaAporte["cor"]) ?>;
                        "
                    ></div>
                </div>

            </div>


            <?php if ($metaAporte["status"] === "ativa"): ?>

                <form
                    method="POST"
                    action="index.php?pagina=metas#formulario-aporte"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= jaboo_meta_escape(jaboo_csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="acao"
                        value="adicionar_aporte"
                    >

                    <input
                        type="hidden"
                        name="meta_id"
                        value="<?= (int) $metaAporte["id"] ?>"
                    >


                    <div class="metas-form-grid">

                        <div class="metas-campo">

                            <label>Valor do aporte</label>

                            <div class="metas-input-moeda">
                                <span>R$</span>

                                <input
                                    type="text"
                                    name="valor"
                                    class="metas-valor-input"
                                    data-metas-moeda
                                    placeholder="0,00"
                                    required
                                >
                            </div>

                        </div>


                        <div class="metas-campo">

                            <label>Data do aporte</label>

                            <input
                                type="date"
                                name="data_aporte"
                                value="<?= date("Y-m-d") ?>"
                                min="<?= jaboo_meta_escape($metaAporte["data_inicio"]) ?>"
                                required
                            >

                        </div>


                        <div class="metas-campo metas-campo-largo">

                            <label>Descrição</label>

                            <input
                                type="text"
                                name="descricao"
                                maxlength="200"
                                placeholder="Ex: Economia do mês"
                            >

                            <small>
                                Campo opcional.
                            </small>

                        </div>

                    </div>


                    <div class="metas-form-acoes">

                        <button
                            type="submit"
                            class="metas-botao-principal"
                        >
                            Registrar aporte
                        </button>

                    </div>

                </form>

            <?php else: ?>

                <div class="metas-alerta metas-alerta-erro">
                    Esta meta não está ativa e não pode receber novos aportes.
                </div>

            <?php endif; ?>


            <!-- HISTÓRICO -->

            <div class="metas-historico" id="historico-meta">

                <h4>Histórico de aportes</h4>

                <?php if (empty($historicoAportes)): ?>

                    <div class="metas-vazio-historico">
                        Nenhum aporte registrado nesta meta.
                    </div>

                <?php else: ?>

                    <div class="metas-tabela-wrapper">

                        <table class="metas-tabela">

                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Valor</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($historicoAportes as $aporteItem): ?>

                                    <tr>

                                        <td>
                                            <?= jaboo_meta_data_br($aporteItem["data_aporte"]) ?>
                                        </td>

                                        <td>
                                            <?= jaboo_meta_escape($aporteItem["descricao"] ?: "Aporte") ?>
                                        </td>

                                        <td class="metas-valor-tabela">
                                            <?= jaboo_meta_moeda($aporteItem["valor"]) ?>
                                        </td>

                                        <td>

                                            <form
                                                method="POST"
                                                action="index.php?pagina=metas&aporte=<?= (int) $metaAporte["id"] ?>#historico-meta"
                                                onsubmit="return confirm('Deseja realmente excluir este aporte? O valor será retirado da meta.');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= jaboo_meta_escape(jaboo_csrf_token()) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="acao"
                                                    value="excluir_aporte"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="aporte_id"
                                                    value="<?= (int) $aporteItem["id"] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="metas-botao-excluir"
                                                >
                                                    Excluir
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>


    <!-- FILTROS -->

    <div class="metas-listagem-cabecalho">

        <div>
            <h3>Suas metas</h3>

            <p>
                Acompanhe seus objetivos financeiros.
            </p>
        </div>

        <div class="metas-filtros">

            <?php
            $filtros = [
                "todas" => "Todas",
                "ativa" => "Ativas",
                "pausada" => "Pausadas",
                "concluida" => "Concluídas",
                "cancelada" => "Canceladas"
            ];
            ?>

            <?php foreach ($filtros as $valorFiltro => $nomeFiltro): ?>

                <a
                    href="index.php?pagina=metas&status=<?= $valorFiltro ?>"
                    class="<?= $filtroStatus === $valorFiltro ? "ativo" : "" ?>"
                >
                    <?= $nomeFiltro ?>
                </a>

            <?php endforeach; ?>

        </div>

    </div>


    <!-- LISTA DE METAS -->

    <?php if (empty($metas)): ?>

        <div class="metas-vazio">

            <div class="metas-vazio-icone">
                🎯
            </div>

            <h3>Nenhuma meta encontrada</h3>

            <p>
                Crie uma meta financeira para começar a acompanhar seus objetivos.
            </p>

            <a
                href="index.php?pagina=metas&nova=1#formulario-meta"
                class="metas-botao-principal"
            >
                Criar primeira meta
            </a>

        </div>

    <?php else: ?>

        <div class="metas-lista">

            <?php foreach ($metas as $meta): ?>

                <?php

                $objetivo = (float) $meta["valor_objetivo"];
                $atual = (float) $meta["valor_atual"];

                $percentual = $objetivo > 0
                    ? ($atual / $objetivo) * 100
                    : 0;

                $larguraProgresso = min(100, $percentual);

                $faltante = max(0, $objetivo - $atual);

                $statusClasses = [
                    "ativa" => "metas-status-ativa",
                    "pausada" => "metas-status-pausada",
                    "concluida" => "metas-status-concluida",
                    "cancelada" => "metas-status-cancelada"
                ];

                $statusNomes = [
                    "ativa" => "Em andamento",
                    "pausada" => "Pausada",
                    "concluida" => "Concluída",
                    "cancelada" => "Cancelada"
                ];

                $status = $meta["status"];

                ?>

                <article class="metas-card">

                    <div class="metas-card-topo">

                        <div class="metas-card-identificacao">

                            <div
                                class="metas-card-icone"
                                style="
                                    background: <?= jaboo_meta_escape($meta["cor"]) ?>1A;
                                    color: <?= jaboo_meta_escape($meta["cor"]) ?>;
                                "
                            >
                                <?= jaboo_meta_escape($meta["icone"] ?: "🎯") ?>
                            </div>

                            <div>

                                <h3>
                                    <?= jaboo_meta_escape($meta["nome"]) ?>
                                </h3>

                                <?php if (!empty($meta["descricao"])): ?>

                                    <p>
                                        <?= jaboo_meta_escape($meta["descricao"]) ?>
                                    </p>

                                <?php endif; ?>

                            </div>

                        </div>


                        <span class="metas-status <?= $statusClasses[$status] ?? "" ?>">
                            <?= $statusNomes[$status] ?? "Indefinida" ?>
                        </span>

                    </div>


                    <div class="metas-valores">

                        <div>

                            <span>Valor acumulado</span>

                            <strong>
                                <?= jaboo_meta_moeda($atual) ?>
                            </strong>

                        </div>

                        <div>

                            <span>Objetivo</span>

                            <strong>
                                <?= jaboo_meta_moeda($objetivo) ?>
                            </strong>

                        </div>

                    </div>


                    <div class="metas-progresso-area">

                        <div class="metas-progresso-texto">

                            <span>Progresso</span>

                            <strong>
                                <?= number_format($percentual, 1, ",", ".") ?>%
                            </strong>

                        </div>

                        <div class="metas-progresso">

                            <div
                                class="metas-progresso-preenchido"
                                style="
                                    width: <?= $larguraProgresso ?>%;
                                    background: <?= jaboo_meta_escape($meta["cor"]) ?>;
                                "
                            ></div>

                        </div>

                        <div class="metas-faltante">

                            <?php if ($faltante > 0): ?>

                                Faltam
                                <strong>
                                    <?= jaboo_meta_moeda($faltante) ?>
                                </strong>
                                para alcançar o objetivo.

                            <?php else: ?>

                                <strong>
                                    Objetivo alcançado!
                                </strong>

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="metas-card-detalhes">

                        <span>
                            <small>Início</small>
                            <?= jaboo_meta_data_br($meta["data_inicio"]) ?>
                        </span>

                        <span>
                            <small>Prazo</small>
                            <?= jaboo_meta_data_br($meta["data_limite"]) ?>
                        </span>

                    </div>


                    <div class="metas-card-acoes">

                        <a
                            href="index.php?pagina=metas&editar=<?= (int) $meta["id"] ?>#formulario-meta"
                            class="metas-botao-secundario"
                        >
                            Editar
                        </a>

                        <a
                            href="index.php?pagina=metas&aporte=<?= (int) $meta["id"] ?>#formulario-aporte"
                            class="metas-botao-secundario"
                        >
                            Aportes
                        </a>

                        <?php if ($status === "ativa"): ?>

                            <a
                                href="index.php?pagina=metas&aporte=<?= (int) $meta["id"] ?>#formulario-aporte"
                                class="metas-botao-principal"
                            >
                                + Aportar
                            </a>

                            <form method="POST" action="index.php?pagina=metas">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= jaboo_meta_escape(jaboo_csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="acao"
                                    value="alterar_status"
                                >

                                <input
                                    type="hidden"
                                    name="meta_id"
                                    value="<?= (int) $meta["id"] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="novo_status"
                                    value="pausada"
                                >

                                <button
                                    type="submit"
                                    class="metas-botao-texto"
                                >
                                    Pausar
                                </button>

                            </form>

                        <?php elseif ($status === "pausada" || $status === "cancelada"): ?>

                            <form method="POST" action="index.php?pagina=metas">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= jaboo_meta_escape(jaboo_csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="acao"
                                    value="alterar_status"
                                >

                                <input
                                    type="hidden"
                                    name="meta_id"
                                    value="<?= (int) $meta["id"] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="novo_status"
                                    value="ativa"
                                >

                                <button
                                    type="submit"
                                    class="metas-botao-principal"
                                >
                                    Retomar
                                </button>

                            </form>

                        <?php endif; ?>


                        <?php if ($status === "ativa" || $status === "pausada"): ?>

                            <form
                                method="POST"
                                action="index.php?pagina=metas"
                                onsubmit="return confirm('Deseja realmente cancelar esta meta?');"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= jaboo_meta_escape(jaboo_csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="acao"
                                    value="alterar_status"
                                >

                                <input
                                    type="hidden"
                                    name="meta_id"
                                    value="<?= (int) $meta["id"] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="novo_status"
                                    value="cancelada"
                                >

                                <button
                                    type="submit"
                                    class="metas-botao-texto metas-texto-perigo"
                                >
                                    Cancelar
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>


<!-- MÁSCARA DE MOEDA -->

<script>
(function () {

    function formatarMoeda(input) {

        let numeros = input.value.replace(/\D/g, "");

        if (numeros === "") {
            input.value = "";
            return;
        }

        numeros = numeros.replace(/^0+(?=\d)/, "");

        numeros = numeros.padStart(3, "0");

        const centavos = numeros.slice(-2);

        let reais = numeros.slice(0, -2);

        reais = reais.replace(/\B(?=(\d{3})+(?!\d))/g, ".");

        input.value = reais + "," + centavos;
    }

    document.querySelectorAll("[data-metas-moeda]").forEach(function (input) {

        input.addEventListener("input", function () {
            formatarMoeda(input);
        });

        input.addEventListener("focus", function () {
            if (input.value === "0,00") {
                input.value = "";
            }
        });

    });

})();
</script>
