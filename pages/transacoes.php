
<?php

// ==========================================
// JABOO - MÓDULO DE TRANSAÇÕES
// ==========================================

if (basename($_SERVER["SCRIPT_FILENAME"] ?? "") === basename(__FILE__)) {
    header("Location: ../index.php?pagina=transacoes");
    exit;
}

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/database.php";

jaboo_exigir_login();

$usuarioId = (int) $_SESSION["usuario_id"];


// ==========================================
// FUNÇÕES AUXILIARES
// ==========================================

function jaboo_formatar_moeda_transacao($valor): string {

    $valor = (string) $valor;

    $partes = explode(".", $valor, 2);

    $inteiros = $partes[0];

    $centavos = str_pad(
        $partes[1] ?? "0",
        2,
        "0"
    );

    $inteirosFormatados = preg_replace(
        '/\B(?=(\d{3})+(?!\d))/',
        ".",
        $inteiros
    );

    return $inteirosFormatados . "," . substr($centavos, 0, 2);

}


function jaboo_validar_data_transacao($data): bool {

    if ($data === "") {
        return true;
    }

    $dataObjeto = DateTime::createFromFormat("!Y-m-d", $data);

    return $dataObjeto &&
        $dataObjeto->format("Y-m-d") === $data;

}


// ==========================================
// MENSAGENS
// ==========================================

$mensagem = $_SESSION["transacoes_mensagem"] ?? "";

$tipoMensagem = $_SESSION["transacoes_tipo_mensagem"] ?? "sucesso";

unset(
    $_SESSION["transacoes_mensagem"],
    $_SESSION["transacoes_tipo_mensagem"]
);

$erroFormulario = "";


// ==========================================
// VALORES PADRÃO DO FORMULÁRIO
// ==========================================

$valoresFormulario = [

    "id" => 0,
    "conta_id" => "",
    "categoria_id" => "",
    "descricao" => "",
    "tipo" => "despesa",
    "valor" => "",
    "status" => "efetivada",
    "data_competencia" => date("Y-m-d"),
    "data_vencimento" => "",
    "data_pagamento" => date("Y-m-d"),
    "observacoes" => ""

];

$mostrarFormulario = isset($_GET["novo"]);


// ==========================================
// REDIRECIONAMENTO
// ==========================================

$redirecionar = function ($texto, $tipo = "sucesso") {

    $_SESSION["transacoes_mensagem"] = $texto;

    $_SESSION["transacoes_tipo_mensagem"] = $tipo;

    header("Location: index.php?pagina=transacoes");

    exit;

};


// ==========================================
// CARREGAR TRANSAÇÃO PARA EDIÇÃO
// ==========================================

$idEdicao = isset($_GET["editar"])
    ? (int) $_GET["editar"]
    : 0;

if (
    $idEdicao > 0 &&
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {

    $consultaEdicao = $conexao->prepare(
        "SELECT
            id,
            conta_id,
            categoria_id,
            descricao,
            tipo,
            valor,
            status,
            data_competencia,
            data_vencimento,
            data_pagamento,
            observacoes
         FROM transacoes
         WHERE id = ?
         AND usuario_id = ?
         LIMIT 1"
    );

    $consultaEdicao->bind_param(
        "ii",
        $idEdicao,
        $usuarioId
    );

    $consultaEdicao->execute();

    $transacaoEdicao = $consultaEdicao
        ->get_result()
        ->fetch_assoc();

    $consultaEdicao->close();


    if ($transacaoEdicao) {

        $valoresFormulario = [

            "id" => (int) $transacaoEdicao["id"],

            "conta_id" => (string) $transacaoEdicao["conta_id"],

            "categoria_id" => $transacaoEdicao["categoria_id"] !== null
                ? (string) $transacaoEdicao["categoria_id"]
                : "",

            "descricao" => $transacaoEdicao["descricao"],

            "tipo" => $transacaoEdicao["tipo"],

            "valor" => jaboo_formatar_moeda_transacao(
                $transacaoEdicao["valor"]
            ),

            "status" => $transacaoEdicao["status"],

            "data_competencia" => $transacaoEdicao["data_competencia"],

            "data_vencimento" => $transacaoEdicao["data_vencimento"] ?? "",

            "data_pagamento" => $transacaoEdicao["data_pagamento"] ?? "",

            "observacoes" => $transacaoEdicao["observacoes"] ?? ""

        ];

        $mostrarFormulario = true;

    } else {

        $erroFormulario = "Transação não encontrada.";

        $mostrarFormulario = true;

    }

}


// ==========================================
// PROCESSAMENTO DOS FORMULÁRIOS
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    jaboo_validar_csrf();

    $acao = $_POST["acao"] ?? "";

    try {

        // ==================================
        // CANCELAR TRANSAÇÃO
        // ==================================

        if ($acao === "cancelar") {

            $id = (int) ($_POST["transacao_id"] ?? 0);

            if ($id <= 0) {

                $redirecionar(
                    "Transação inválida.",
                    "erro"
                );

            }

            $verificacao = $conexao->prepare(
                "SELECT id
                 FROM transacoes
                 WHERE id = ?
                 AND usuario_id = ?
                 LIMIT 1"
            );

            $verificacao->bind_param(
                "ii",
                $id,
                $usuarioId
            );

            $verificacao->execute();

            $existe = $verificacao
                ->get_result()
                ->fetch_assoc();

            $verificacao->close();

            if (!$existe) {

                $redirecionar(
                    "Transação não encontrada.",
                    "erro"
                );

            }

            $cancelamento = $conexao->prepare(
                "UPDATE transacoes
                 SET status = 'cancelada'
                 WHERE id = ?
                 AND usuario_id = ?"
            );

            $cancelamento->bind_param(
                "ii",
                $id,
                $usuarioId
            );

            $cancelamento->execute();

            $cancelamento->close();

            $redirecionar(
                "Transação cancelada com sucesso."
            );

        }


        // ==================================
        // CADASTRAR OU EDITAR
        // ==================================

        elseif ($acao === "salvar") {

            $id = (int) ($_POST["transacao_id"] ?? 0);

            $contaId = (int) ($_POST["conta_id"] ?? 0);

            $categoriaInformada = trim(
                (string) ($_POST["categoria_id"] ?? "")
            );

            $categoriaId = $categoriaInformada !== ""
                ? (int) $categoriaInformada
                : null;

            $descricao = trim($_POST["descricao"] ?? "");

            $tipo = $_POST["tipo"] ?? "despesa";

            $valorInformado = trim(
                (string) ($_POST["valor"] ?? "")
            );

            $status = $_POST["status"] ?? "efetivada";

            $dataCompetencia = trim(
                $_POST["data_competencia"] ?? ""
            );

            $dataVencimento = trim(
                $_POST["data_vencimento"] ?? ""
            );

            $dataPagamento = trim(
                $_POST["data_pagamento"] ?? ""
            );

            $observacoes = trim(
                $_POST["observacoes"] ?? ""
            );


            // Converte moeda brasileira para DECIMAL

            $valorSemMilhar = str_replace(
                ".",
                "",
                $valorInformado
            );

            $valorBanco = str_replace(
                ",",
                ".",
                $valorSemMilhar
            );


            // Preservar dados do formulário

            $valoresFormulario = [

                "id" => $id,
                "conta_id" => (string) $contaId,

                "categoria_id" => $categoriaId !== null
                    ? (string) $categoriaId
                    : "",

                "descricao" => $descricao,
                "tipo" => $tipo,
                "valor" => $valorInformado,
                "status" => $status,
                "data_competencia" => $dataCompetencia,
                "data_vencimento" => $dataVencimento,
                "data_pagamento" => $dataPagamento,
                "observacoes" => $observacoes

            ];

            $mostrarFormulario = true;


            // ==================================
            // VERIFICAR TRANSAÇÃO ORIGINAL
            // ==================================

            $registroOriginal = null;

            if ($id > 0) {

                $consultaOriginal = $conexao->prepare(
                    "SELECT id, conta_id, categoria_id
                     FROM transacoes
                     WHERE id = ?
                     AND usuario_id = ?
                     LIMIT 1"
                );

                $consultaOriginal->bind_param(
                    "ii",
                    $id,
                    $usuarioId
                );

                $consultaOriginal->execute();

                $registroOriginal = $consultaOriginal
                    ->get_result()
                    ->fetch_assoc();

                $consultaOriginal->close();

                if (!$registroOriginal) {

                    $erroFormulario = "Transação não encontrada.";

                }

            }


            // ==================================
            // VALIDAR DESCRIÇÃO
            // ==================================

            $tamanhoDescricao = function_exists("mb_strlen")
                ? mb_strlen($descricao, "UTF-8")
                : strlen($descricao);

            if (
                $erroFormulario === "" &&
                ($tamanhoDescricao < 1 || $tamanhoDescricao > 200)
            ) {

                $erroFormulario = "A descrição deve ter entre 1 e 200 caracteres.";

            }


            // ==================================
            // VALIDAR TIPO
            // ==================================

            if (
                $erroFormulario === "" &&
                !in_array($tipo, ["receita", "despesa"], true)
            ) {

                $erroFormulario = "Selecione um tipo válido.";

            }


            // ==================================
            // VALIDAR STATUS
            // ==================================

            if (
                $erroFormulario === "" &&
                !in_array(
                    $status,
                    ["pendente", "efetivada", "cancelada"],
                    true
                )
            ) {

                $erroFormulario = "Selecione uma situação válida.";

            }


            // ==================================
            // VALIDAR VALOR
            // ==================================

            if (
                $erroFormulario === "" &&
                !preg_match(
                    '/^(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?$/',
                    $valorInformado
                )
            ) {

                $erroFormulario = "Digite um valor válido. Exemplo: 1.234,56.";

            }

            if (
                $erroFormulario === "" &&
                !preg_match(
                    '/^\d{1,13}(?:\.\d{1,2})?$/',
                    $valorBanco
                )
            ) {

                $erroFormulario = "O valor ultrapassa o limite permitido.";

            }

            if (
                $erroFormulario === "" &&
                preg_match('/^0+(?:\.0+)?$/', $valorBanco)
            ) {

                $erroFormulario = "O valor precisa ser maior que zero.";

            }


            // ==================================
            // VALIDAR DATAS
            // ==================================

            if (
                $erroFormulario === "" &&
                !jaboo_validar_data_transacao($dataCompetencia)
            ) {

                $erroFormulario = "Data de competência inválida.";

            }

            if (
                $erroFormulario === "" &&
                $dataCompetencia === ""
            ) {

                $erroFormulario = "Informe a data da movimentação.";

            }

            if (
                $erroFormulario === "" &&
                !jaboo_validar_data_transacao($dataVencimento)
            ) {

                $erroFormulario = "Data de vencimento inválida.";

            }

            if (
                $erroFormulario === "" &&
                !jaboo_validar_data_transacao($dataPagamento)
            ) {

                $erroFormulario = "Data de pagamento inválida.";

            }


            // ==================================
            // VALIDAR OBSERVAÇÕES
            // ==================================

            if (
                $erroFormulario === "" &&
                strlen($observacoes) > 5000
            ) {

                $erroFormulario = "As observações devem ter até 5.000 caracteres.";

            }


            // ==================================
            // VALIDAR CONTA
            // ==================================

            if (
                $erroFormulario === "" &&
                $contaId <= 0
            ) {

                $erroFormulario = "Selecione uma conta.";

            }

            if ($erroFormulario === "" && $contaId > 0) {

                $consultaConta = $conexao->prepare(
                    "SELECT id, ativo
                     FROM contas
                     WHERE id = ?
                     AND usuario_id = ?
                     LIMIT 1"
                );

                $consultaConta->bind_param(
                    "ii",
                    $contaId,
                    $usuarioId
                );

                $consultaConta->execute();

                $contaEncontrada = $consultaConta
                    ->get_result()
                    ->fetch_assoc();

                $consultaConta->close();

                if (!$contaEncontrada) {

                    $erroFormulario = "A conta selecionada não pertence ao seu usuário.";

                } elseif (
                    !(int) $contaEncontrada["ativo"] &&
                    !(
                        $registroOriginal &&
                        (int) $registroOriginal["conta_id"] === $contaId
                    )
                ) {

                    $erroFormulario = "Não é possível utilizar uma conta desativada.";

                }

            }


            // ==================================
            // VALIDAR CATEGORIA
            // ==================================

            if (
                $erroFormulario === "" &&
                $categoriaId !== null &&
                $categoriaId <= 0
            ) {

                $erroFormulario = "Categoria inválida.";

            }

            if (
                $erroFormulario === "" &&
                $categoriaId !== null
            ) {

                $consultaCategoria = $conexao->prepare(
                    "SELECT id, ativo
                     FROM categorias
                     WHERE id = ?
                     AND usuario_id = ?
                     AND tipo = ?
                     LIMIT 1"
                );

                $consultaCategoria->bind_param(
                    "iis",
                    $categoriaId,
                    $usuarioId,
                    $tipo
                );

                $consultaCategoria->execute();

                $categoriaEncontrada = $consultaCategoria
                    ->get_result()
                    ->fetch_assoc();

                $consultaCategoria->close();

                if (!$categoriaEncontrada) {

                    $erroFormulario = "A categoria não existe ou não corresponde ao tipo da transação.";

                } elseif (
                    !(int) $categoriaEncontrada["ativo"] &&
                    !(
                        $registroOriginal &&
                        $registroOriginal["categoria_id"] !== null &&
                        (int) $registroOriginal["categoria_id"] === $categoriaId
                    )
                ) {

                    $erroFormulario = "Não é possível utilizar uma categoria desativada.";

                }

            }


            // ==================================
            // SALVAR NO BANCO
            // ==================================

            if ($erroFormulario === "") {

                $dataVencimentoBanco = $dataVencimento !== ""
                    ? $dataVencimento
                    : null;

                $dataPagamentoBanco = $dataPagamento !== ""
                    ? $dataPagamento
                    : null;

                $observacoesBanco = $observacoes !== ""
                    ? $observacoes
                    : null;


                // ==================================
                // EDITAR
                // ==================================

                if ($id > 0) {

                    $atualizacao = $conexao->prepare(
                        "UPDATE transacoes
                         SET conta_id = ?,
                             categoria_id = ?,
                             descricao = ?,
                             tipo = ?,
                             valor = ?,
                             status = ?,
                             data_competencia = ?,
                             data_vencimento = ?,
                             data_pagamento = ?,
                             observacoes = ?
                         WHERE id = ?
                         AND usuario_id = ?"
                    );

                    $atualizacao->bind_param(
                        "iissssssssii",
                        $contaId,
                        $categoriaId,
                        $descricao,
                        $tipo,
                        $valorBanco,
                        $status,
                        $dataCompetencia,
                        $dataVencimentoBanco,
                        $dataPagamentoBanco,
                        $observacoesBanco,
                        $id,
                        $usuarioId
                    );

                    $atualizacao->execute();
                    $atualizacao->close();

                    $redirecionar(
                        "Transação atualizada com sucesso."
                    );

                } else {

                    // ==================================
                    // CADASTRAR
                    // ==================================

                    $insercao = $conexao->prepare(
                        "INSERT INTO transacoes (
                            usuario_id,
                            conta_id,
                            categoria_id,
                            descricao,
                            tipo,
                            valor,
                            status,
                            data_competencia,
                            data_vencimento,
                            data_pagamento,
                            observacoes
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    $insercao->bind_param(
                        "iiissssssss",
                        $usuarioId,
                        $contaId,
                        $categoriaId,
                        $descricao,
                        $tipo,
                        $valorBanco,
                        $status,
                        $dataCompetencia,
                        $dataVencimentoBanco,
                        $dataPagamentoBanco,
                        $observacoesBanco
                    );

                    $insercao->execute();
                    $insercao->close();

                    $redirecionar(
                        "Transação cadastrada com sucesso."
                    );

                }

            }

        }

    } catch (mysqli_sql_exception $erroBanco) {

        error_log(
            "Erro no módulo de transações JABOO: " .
            $erroBanco->getMessage()
        );

        $erroFormulario = "Não foi possível concluir a operação. Verifique os dados e tente novamente.";

        $mostrarFormulario = true;

    }

}


// ==========================================
// CARREGAR CONTAS
// ==========================================

$consultaContas = $conexao->prepare(
    "SELECT id, nome, ativo
     FROM contas
     WHERE usuario_id = ?
     ORDER BY ativo DESC, nome ASC"
);

$consultaContas->bind_param(
    "i",
    $usuarioId
);

$consultaContas->execute();

$resultadoContas = $consultaContas->get_result();

$contas = [];

while ($conta = $resultadoContas->fetch_assoc()) {
    $contas[] = $conta;
}

$consultaContas->close();


// ==========================================
// CARREGAR CATEGORIAS
// ==========================================

$consultaCategorias = $conexao->prepare(
    "SELECT id, nome, tipo, ativo
     FROM categorias
     WHERE usuario_id = ?
     ORDER BY ativo DESC, tipo ASC, nome ASC"
);

$consultaCategorias->bind_param(
    "i",
    $usuarioId
);

$consultaCategorias->execute();

$resultadoCategorias = $consultaCategorias->get_result();

$categorias = [];

while ($categoria = $resultadoCategorias->fetch_assoc()) {
    $categorias[] = $categoria;
}

$consultaCategorias->close();


// ==========================================
// FILTROS
// ==========================================

$filtroTipo = $_GET["tipo"] ?? "";

if (!in_array($filtroTipo, ["receita", "despesa"], true)) {
    $filtroTipo = "";
}

$filtroStatus = $_GET["status"] ?? "";

if (!in_array($filtroStatus, ["pendente", "efetivada", "cancelada"], true)) {
    $filtroStatus = "";
}

$filtroConta = max(0, (int) ($_GET["conta"] ?? 0));

$busca = trim($_GET["busca"] ?? "");

if (strlen($busca) > 100) {
    $busca = substr($busca, 0, 100);
}

$dataInicio = trim($_GET["data_inicio"] ?? "");
$dataFim = trim($_GET["data_fim"] ?? "");

if (!jaboo_validar_data_transacao($dataInicio)) {
    $dataInicio = "";
}

if (!jaboo_validar_data_transacao($dataFim)) {
    $dataFim = "";
}

if ($dataInicio !== "" && $dataFim !== "" && $dataInicio > $dataFim) {
    $dataInicio = "";
    $dataFim = "";
}

$buscaLike = "%" . $busca . "%";


// ==========================================
// RESUMO DO MÊS
// ==========================================

$primeiroDiaMes = date("Y-m-01");
$ultimoDiaMes = date("Y-m-t");

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
        ) AS despesas,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'pendente'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS pendentes

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

$resumo = $resumoConsulta
    ->get_result()
    ->fetch_assoc();

$resumoConsulta->close();

$receitasMes = jaboo_formatar_moeda_transacao($resumo["receitas"]);

$despesasMes = jaboo_formatar_moeda_transacao($resumo["despesas"]);

$pendentesMes = (int) $resumo["pendentes"];


// ==========================================
// HISTÓRICO
// ==========================================

$consultaHistorico = $conexao->prepare(
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

     AND (? = '' OR t.tipo = ?)

     AND (? = '' OR t.status = ?)

     AND (? = 0 OR t.conta_id = ?)

     AND (? = '' OR t.descricao LIKE ?)

     AND (? = '' OR t.data_competencia >= ?)

     AND (? = '' OR t.data_competencia <= ?)

     ORDER BY t.data_competencia DESC, t.id DESC

     LIMIT 100"
);

$consultaHistorico->bind_param(
    "issssiissssss",
    $usuarioId,
    $filtroTipo,
    $filtroTipo,
    $filtroStatus,
    $filtroStatus,
    $filtroConta,
    $filtroConta,
    $busca,
    $buscaLike,
    $dataInicio,
    $dataInicio,
    $dataFim,
    $dataFim
);

$consultaHistorico->execute();

$resultadoHistorico = $consultaHistorico->get_result();

$transacoes = [];

while ($transacao = $resultadoHistorico->fetch_assoc()) {
    $transacoes[] = $transacao;
}

$consultaHistorico->close();


// ==========================================
// NOMES DOS TIPOS E STATUS
// ==========================================

$nomesTipos = [
    "receita" => "Receita",
    "despesa" => "Despesa"
];

$nomesStatus = [
    "pendente" => "Pendente",
    "efetivada" => "Efetivada",
    "cancelada" => "Cancelada"
];

$mostrarFormulario = $mostrarFormulario || $erroFormulario !== "";

$contasAtivas = count(
    array_filter(
        $contas,
        fn($conta) => (int) $conta["ativo"] === 1
    )
);

?>

<div class="transacoes-page">


    <!-- CABEÇALHO DA PÁGINA -->

    <section class="transacoes-heading">

        <div>

            <span class="transacoes-eyebrow">
                ORGANIZAÇÃO FINANCEIRA
            </span>

            <h2>Minhas transações</h2>

            <p>
                Acompanhe suas receitas e despesas.
            </p>

        </div>

    </section>


    <!-- MENSAGENS -->

    <?php if ($mensagem !== ""): ?>

        <div class="transacoes-alert <?= $tipoMensagem === "erro" ? "erro" : "sucesso" ?>">

            <?= htmlspecialchars($mensagem, ENT_QUOTES, "UTF-8") ?>

        </div>

    <?php endif; ?>


    <?php if ($erroFormulario !== ""): ?>

        <div class="transacoes-alert erro">

            <?= htmlspecialchars($erroFormulario, ENT_QUOTES, "UTF-8") ?>

        </div>

    <?php endif; ?>


    <!-- RESUMO -->

    <section class="transacoes-resumo">

        <article class="transacoes-resumo-card">

            <span class="transacoes-resumo-label">
                Receitas efetivadas no mês
            </span>

            <strong class="transacoes-valor-positivo">
                R$ <?= $receitasMes ?>
            </strong>

        </article>


        <article class="transacoes-resumo-card">

            <span class="transacoes-resumo-label">
                Despesas efetivadas no mês
            </span>

            <strong class="transacoes-valor-negativo">
                R$ <?= $despesasMes ?>
            </strong>

        </article>


        <article class="transacoes-resumo-card">

            <span class="transacoes-resumo-label">
                Transações pendentes no mês
            </span>

            <strong>
                <?= $pendentesMes ?>
            </strong>

        </article>

    </section>


    <!-- FORMULÁRIO -->

    <?php if ($mostrarFormulario): ?>

        <section class="transacoes-form-section" id="formulario-transacao">

            <div class="transacoes-section-heading">

                <div>

                    <h3>
                        <?= $valoresFormulario["id"] > 0
                            ? "Editar transação"
                            : "Nova transação"
                        ?>
                    </h3>

                    <p>
                        Preencha os dados da movimentação financeira.
                    </p>

                </div>

                <a
                    href="index.php?pagina=transacoes"
                    class="transacoes-close-link"
                >
                    Fechar
                </a>

            </div>


            <?php if ($contasAtivas === 0): ?>

                <div class="transacoes-alert erro">
                    Você ainda não possui contas ativas.
                    <a href="index.php?pagina=contas">
                        Cadastre uma conta primeiro.
                    </a>
                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="index.php?pagina=transacoes"
                class="transacoes-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(jaboo_csrf_token(), ENT_QUOTES, "UTF-8") ?>"
                >

                <input
                    type="hidden"
                    name="acao"
                    value="salvar"
                >

                <input
                    type="hidden"
                    name="transacao_id"
                    value="<?= (int) $valoresFormulario["id"] ?>"
                >


                <div class="transacoes-form-grid">

                    <!-- TIPO -->

                    <div class="transacoes-input-group">

                        <label for="tipo">
                            Tipo de movimentação
                        </label>

                        <select id="tipo" name="tipo" required>

                            <option
                                value="receita"
                                <?= $valoresFormulario["tipo"] === "receita" ? "selected" : "" ?>
                            >
                                Receita
                            </option>

                            <option
                                value="despesa"
                                <?= $valoresFormulario["tipo"] === "despesa" ? "selected" : "" ?>
                            >
                                Despesa
                            </option>

                        </select>

                    </div>


                    <!-- STATUS -->

                    <div class="transacoes-input-group">

                        <label for="status">
                            Situação
                        </label>

                        <select id="status" name="status" required>

                            <option
                                value="efetivada"
                                <?= $valoresFormulario["status"] === "efetivada" ? "selected" : "" ?>
                            >
                                Efetivada
                            </option>

                            <option
                                value="pendente"
                                <?= $valoresFormulario["status"] === "pendente" ? "selected" : "" ?>
                            >
                                Pendente
                            </option>

                            <option
                                value="cancelada"
                                <?= $valoresFormulario["status"] === "cancelada" ? "selected" : "" ?>
                            >
                                Cancelada
                            </option>

                        </select>

                    </div>


                    <!-- DESCRIÇÃO -->

                    <div class="transacoes-input-group">

                        <label for="descricao">
                            Descrição
                        </label>

                        <input
                            type="text"
                            id="descricao"
                            name="descricao"
                            placeholder="Ex.: Compra no supermercado"
                            maxlength="200"
                            value="<?= htmlspecialchars($valoresFormulario["descricao"], ENT_QUOTES, "UTF-8") ?>"
                            required
                        >

                    </div>


                    <!-- VALOR -->

                    <div class="transacoes-input-group">

                        <label for="valor">
                            Valor (R$)
                        </label>

                        <input
                            type="text"
                            id="valor"
                            name="valor"
                            placeholder="0,00"
                            inputmode="decimal"
                            maxlength="40"
                            autocomplete="off"
                            value="<?= htmlspecialchars($valoresFormulario["valor"], ENT_QUOTES, "UTF-8") ?>"
                            required
                        >

                        <small class="transacoes-input-hint">
                            Exemplo: 1.234,56.
                        </small>

                    </div>


                    <!-- CONTA -->

                    <div class="transacoes-input-group">

                        <label for="conta_id">
                            Conta
                        </label>

                        <select id="conta_id" name="conta_id" required>

                            <option value="">
                                Selecione uma conta
                            </option>

                            <?php foreach ($contas as $conta): ?>

                                <?php

                                $contaAtiva = (int) $conta["ativo"] === 1;

                                $contaSelecionada =
                                    (int) $valoresFormulario["conta_id"] ===
                                    (int) $conta["id"];

                                if (!$contaAtiva && !$contaSelecionada) {
                                    continue;
                                }

                                ?>

                                <option
                                    value="<?= (int) $conta["id"] ?>"
                                    <?= $contaSelecionada ? "selected" : "" ?>
                                >

                                    <?= htmlspecialchars($conta["nome"], ENT_QUOTES, "UTF-8") ?>

                                    <?= !$contaAtiva ? " (desativada)" : "" ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- CATEGORIA -->

                    <div class="transacoes-input-group">

                        <label for="categoria_id">
                            Categoria
                        </label>

                        <select id="categoria_id" name="categoria_id">

                            <option value="" data-tipo="todos">
                                Sem categoria
                            </option>

                            <?php foreach ($categorias as $categoria): ?>

                                <?php

                                $categoriaAtiva = (int) $categoria["ativo"] === 1;

                                $categoriaSelecionada =
                                    (int) $valoresFormulario["categoria_id"] ===
                                    (int) $categoria["id"];

                                if (!$categoriaAtiva && !$categoriaSelecionada) {
                                    continue;
                                }

                                ?>

                                <option
                                    value="<?= (int) $categoria["id"] ?>"
                                    data-tipo="<?= htmlspecialchars($categoria["tipo"], ENT_QUOTES, "UTF-8") ?>"
                                    <?= $categoriaSelecionada ? "selected" : "" ?>
                                >

                                    <?= htmlspecialchars($categoria["nome"], ENT_QUOTES, "UTF-8") ?>

                                    <?= !$categoriaAtiva ? " (inativa)" : "" ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- DATA DE COMPETÊNCIA -->

                    <div class="transacoes-input-group">

                        <label for="data_competencia">
                            Data da movimentação
                        </label>

                        <input
                            type="date"
                            id="data_competencia"
                            name="data_competencia"
                            value="<?= htmlspecialchars($valoresFormulario["data_competencia"], ENT_QUOTES, "UTF-8") ?>"
                            required
                        >

                    </div>


                    <!-- VENCIMENTO -->

                    <div class="transacoes-input-group">

                        <label for="data_vencimento">
                            Data de vencimento
                        </label>

                        <input
                            type="date"
                            id="data_vencimento"
                            name="data_vencimento"
                            value="<?= htmlspecialchars($valoresFormulario["data_vencimento"], ENT_QUOTES, "UTF-8") ?>"
                        >

                    </div>


                    <!-- PAGAMENTO -->

                    <div class="transacoes-input-group">

                        <label for="data_pagamento">
                            Data de pagamento ou recebimento
                        </label>

                        <input
                            type="date"
                            id="data_pagamento"
                            name="data_pagamento"
                            value="<?= htmlspecialchars($valoresFormulario["data_pagamento"], ENT_QUOTES, "UTF-8") ?>"
                        >

                    </div>

                </div>


                <!-- OBSERVAÇÕES -->

                <div class="transacoes-input-group">

                    <label for="observacoes">
                        Observações
                    </label>

                    <textarea
                        id="observacoes"
                        name="observacoes"
                        rows="3"
                        maxlength="5000"
                        placeholder="Informações adicionais sobre esta movimentação..."
                    ><?= htmlspecialchars($valoresFormulario["observacoes"], ENT_QUOTES, "UTF-8") ?></textarea>

                </div>


                <!-- AÇÕES -->

                <div class="transacoes-form-actions">

                    <button
                        type="submit"
                        class="transacoes-submit-button"
                        <?= $contasAtivas === 0 && $valoresFormulario["id"] === 0 ? "disabled" : "" ?>
                    >

                        <?= $valoresFormulario["id"] > 0
                            ? "Salvar alterações"
                            : "Cadastrar transação"
                        ?>

                    </button>

                    <a
                        href="index.php?pagina=transacoes"
                        class="transacoes-cancel-button"
                    >
                        Cancelar
                    </a>

                </div>

            </form>

        </section>

    <?php endif; ?>


    <!-- FILTROS E HISTÓRICO -->

    <section class="transacoes-historico">

        <div class="transacoes-section-heading">

            <div>

                <h3>Histórico de transações</h3>

                <p>
                    Consulte e filtre suas movimentações.
                </p>

            </div>

        </div>


        <!-- FILTROS -->

        <form
            method="GET"
            action="index.php"
            class="transacoes-filtros"
        >

            <input type="hidden" name="pagina" value="transacoes">


            <div class="transacoes-filtro-grupo">

                <label for="busca">
                    Buscar
                </label>

                <input
                    type="search"
                    id="busca"
                    name="busca"
                    placeholder="Descrição..."
                    value="<?= htmlspecialchars($busca, ENT_QUOTES, "UTF-8") ?>"
                >

            </div>


            <div class="transacoes-filtro-grupo">

                <label for="filtro_tipo">
                    Tipo
                </label>

                <select id="filtro_tipo" name="tipo">

                    <option value="">Todos</option>

                    <option
                        value="receita"
                        <?= $filtroTipo === "receita" ? "selected" : "" ?>
                    >
                        Receitas
                    </option>

                    <option
                        value="despesa"
                        <?= $filtroTipo === "despesa" ? "selected" : "" ?>
                    >
                        Despesas
                    </option>

                </select>

            </div>


            <div class="transacoes-filtro-grupo">

                <label for="filtro_status">
                    Situação
                </label>

                <select id="filtro_status" name="status">

                    <option value="">Todas</option>

                    <option
                        value="efetivada"
                        <?= $filtroStatus === "efetivada" ? "selected" : "" ?>
                    >
                        Efetivadas
                    </option>

                    <option
                        value="pendente"
                        <?= $filtroStatus === "pendente" ? "selected" : "" ?>
                    >
                        Pendentes
                    </option>

                    <option
                        value="cancelada"
                        <?= $filtroStatus === "cancelada" ? "selected" : "" ?>
                    >
                        Canceladas
                    </option>

                </select>

            </div>


            <div class="transacoes-filtro-grupo">

                <label for="filtro_conta">
                    Conta
                </label>

                <select id="filtro_conta" name="conta">

                    <option value="0">
                        Todas
                    </option>

                    <?php foreach ($contas as $conta): ?>

                        <option
                            value="<?= (int) $conta["id"] ?>"
                            <?= $filtroConta === (int) $conta["id"] ? "selected" : "" ?>
                        >

                            <?= htmlspecialchars($conta["nome"], ENT_QUOTES, "UTF-8") ?>

                            <?= !(int) $conta["ativo"] ? " (desativada)" : "" ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="transacoes-filtro-grupo">

                <label for="data_inicio">
                    De
                </label>

                <input
                    type="date"
                    id="data_inicio"
                    name="data_inicio"
                    value="<?= htmlspecialchars($dataInicio, ENT_QUOTES, "UTF-8") ?>"
                >

            </div>


            <div class="transacoes-filtro-grupo">

                <label for="data_fim">
                    Até
                </label>

                <input
                    type="date"
                    id="data_fim"
                    name="data_fim"
                    value="<?= htmlspecialchars($dataFim, ENT_QUOTES, "UTF-8") ?>"
                >

            </div>


            <div class="transacoes-filtro-actions">

                <button type="submit">
                    Filtrar
                </button>

                <a href="index.php?pagina=transacoes">
                    Limpar
                </a>

            </div>

        </form>


        <!-- TABELA -->

        <?php if (count($transacoes) > 0): ?>

            <div class="transacoes-tabela-wrapper">

                <table class="transacoes-tabela">

                    <thead>

                        <tr>

                            <th>Descrição</th>
                            <th>Conta / Categoria</th>
                            <th>Data</th>
                            <th>Situação</th>
                            <th>Valor</th>
                            <th>Ações</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($transacoes as $transacao): ?>

                            <?php

                            $idTransacao = (int) $transacao["id"];

                            $classeTipo = $transacao["tipo"];

                            $classeStatus = $transacao["status"];

                            $sinal = $transacao["tipo"] === "receita"
                                ? "+"
                                : "−";

                            $valorFormatado = jaboo_formatar_moeda_transacao(
                                $transacao["valor"]
                            );

                            $dataFormatada = date(
                                "d/m/Y",
                                strtotime($transacao["data_competencia"])
                            );

                            ?>


                            <tr class="<?= $transacao["status"] === "cancelada" ? "transacao-cancelada" : "" ?>">

                                <td>

                                    <div class="transacao-descricao">

                                        <strong>
                                            <?= htmlspecialchars($transacao["descricao"], ENT_QUOTES, "UTF-8") ?>
                                        </strong>

                                        <span>
                                            <?= $nomesTipos[$transacao["tipo"]] ?>
                                        </span>

                                    </div>

                                </td>


                                <td>

                                    <div class="transacao-conta">

                                        <strong>
                                            <?= htmlspecialchars($transacao["conta_nome"], ENT_QUOTES, "UTF-8") ?>
                                        </strong>

                                        <span>

                                            <?= $transacao["categoria_nome"]
                                                ? htmlspecialchars($transacao["categoria_nome"], ENT_QUOTES, "UTF-8")
                                                : "Sem categoria"
                                            ?>

                                        </span>

                                    </div>

                                </td>


                                <td>

                                    <span class="transacao-data">
                                        <?= $dataFormatada ?>
                                    </span>

                                </td>


                                <td>

                                    <span class="transacao-status <?= $classeStatus ?>">

                                        <?= $nomesStatus[$transacao["status"]] ?>

                                    </span>

                                </td>


                                <td>

                                    <strong class="transacao-valor <?= $classeTipo ?>">

                                        <?= $sinal ?> R$ <?= $valorFormatado ?>

                                    </strong>

                                </td>


                                <td>

                                    <div class="transacao-acoes">

                                        <a
                                            href="index.php?pagina=transacoes&editar=<?= $idTransacao ?>#formulario-transacao"
                                            class="transacao-editar"
                                        >
                                            Editar
                                        </a>


                                        <?php if ($transacao["status"] !== "cancelada"): ?>

                                            <form
                                                method="POST"
                                                action="index.php?pagina=transacoes"
                                                onsubmit="return confirm('Deseja realmente cancelar esta transação?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars(jaboo_csrf_token(), ENT_QUOTES, "UTF-8") ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="acao"
                                                    value="cancelar"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="transacao_id"
                                                    value="<?= $idTransacao ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="transacao-cancelar"
                                                >
                                                    Cancelar
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <p class="transacoes-limite-aviso">
                Exibindo até 100 transações. Utilize os filtros para localizar movimentações específicas.
            </p>


        <?php else: ?>

            <div class="transacoes-empty">

                <div class="transacoes-empty-icon">
                    ↔
                </div>

                <h4>Nenhuma transação encontrada</h4>

                <p>
                    Cadastre uma receita ou despesa para começar a acompanhar suas movimentações.
                </p>

            </div>

        <?php endif; ?>

    </section>

</div>


<!-- ==========================================
     JAVASCRIPT
========================================== -->

<script>

// ==========================================
// FORMATAÇÃO DE VALORES
// ==========================================

(function () {

    const campoValor = document.getElementById("valor");

    if (!campoValor) {
        return;
    }

    const LIMITE_DIGITOS = 15;

    function formatarValor() {

        let digitos = campoValor.value.replace(/\D/g, "");

        digitos = digitos.replace(/^0+/, "");

        if (digitos === "") {

            campoValor.value = "";
            campoValor.setCustomValidity("");

            return;

        }

        if (digitos.length > LIMITE_DIGITOS) {

            campoValor.setCustomValidity(
                "O valor ultrapassa o limite permitido."
            );

        } else {

            campoValor.setCustomValidity("");

        }

        digitos = digitos.padStart(3, "0");

        const reais = digitos.slice(0, -2);

        const centavos = digitos.slice(-2);

        const reaisFormatados = reais.replace(
            /\B(?=(\d{3})+(?!\d))/g,
            "."
        );

        campoValor.value = reaisFormatados + "," + centavos;

        campoValor.setSelectionRange(
            campoValor.value.length,
            campoValor.value.length
        );

    }

    campoValor.addEventListener("input", formatarValor);

})();


// ==========================================
// FILTRO DE CATEGORIAS POR TIPO
// ==========================================

(function () {

    const campoTipo = document.getElementById("tipo");

    const campoCategoria = document.getElementById("categoria_id");

    if (!campoTipo || !campoCategoria) {
        return;
    }

    function atualizarCategorias() {

        const tipoSelecionado = campoTipo.value;

        Array.from(campoCategoria.options).forEach(function (opcao) {

            if (opcao.value === "") {
                opcao.hidden = false;
                return;
            }

            const compativel =
                opcao.dataset.tipo === tipoSelecionado;

            opcao.hidden = !compativel;

            if (!compativel && opcao.selected) {
                campoCategoria.value = "";
            }

        });

    }

    campoTipo.addEventListener(
        "change",
        atualizarCategorias
    );

    atualizarCategorias();

})();

</script>
