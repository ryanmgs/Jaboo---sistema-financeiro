
<?php

// ==========================================
// JABOO - MÓDULO DE ORÇAMENTOS
// ==========================================

// Impede acesso direto ao arquivo
if (basename($_SERVER["SCRIPT_FILENAME"] ?? "") === basename(__FILE__)) {
    header("Location: ../index.php?pagina=orcamentos");
    exit;
}

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/database.php";

jaboo_exigir_login();

$usuarioId = (int) $_SESSION["usuario_id"];


// ==========================================
// FORMATAÇÃO MONETÁRIA
// ==========================================

function jaboo_formatar_moeda_orcamentos($valor): string {

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

    $centavos = substr(
        str_pad($centavos, 2, "0"),
        0,
        2
    );

    $inteiros = preg_replace(
        '/\B(?=(\d{3})+(?!\d))/',
        ".",
        $inteiros
    );

    return ($negativo ? "-" : "") .
        $inteiros . "," . $centavos;

}


// ==========================================
// MENSAGENS
// ==========================================

$mensagem = $_SESSION["orcamentos_mensagem"] ?? "";

$tipoMensagem = $_SESSION["orcamentos_tipo_mensagem"] ?? "sucesso";

unset(
    $_SESSION["orcamentos_mensagem"],
    $_SESSION["orcamentos_tipo_mensagem"]
);

$erroFormulario = "";


// ==========================================
// PERÍODO SELECIONADO
// ==========================================

$mesSelecionado = (int) ($_GET["mes"] ?? date("n"));

$anoSelecionado = (int) ($_GET["ano"] ?? date("Y"));

if ($mesSelecionado < 1 || $mesSelecionado > 12) {
    $mesSelecionado = (int) date("n");
}

if ($anoSelecionado < 1 || $anoSelecionado > 65535) {
    $anoSelecionado = (int) date("Y");
}

$dataInicio = sprintf(
    "%04d-%02d-01",
    $anoSelecionado,
    $mesSelecionado
);

$dataFim = date(
    "Y-m-t",
    strtotime($dataInicio)
);


// ==========================================
// VALORES PADRÃO DO FORMULÁRIO
// ==========================================

$valoresFormulario = [

    "id" => 0,
    "categoria_id" => "",
    "nome" => "",
    "valor_limite" => "",
    "ano" => $anoSelecionado,
    "mes" => $mesSelecionado,
    "alerta_percentual" => 80

];

$mostrarFormulario = isset($_GET["novo"]);


// ==========================================
// REDIRECIONAMENTO COM MENSAGEM
// ==========================================

$redirecionar = function ($texto, $tipo = "sucesso", $mes = null, $ano = null) use ($mesSelecionado, $anoSelecionado) {

    $_SESSION["orcamentos_mensagem"] = $texto;

    $_SESSION["orcamentos_tipo_mensagem"] = $tipo;

    $mes = $mes ?? $mesSelecionado;
    $ano = $ano ?? $anoSelecionado;

    header(
        "Location: index.php?pagina=orcamentos&mes=" .
        (int) $mes .
        "&ano=" .
        (int) $ano
    );

    exit;

};


// ==========================================
// PROCESSAMENTO DOS FORMULÁRIOS
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    jaboo_validar_csrf();

    $acao = $_POST["acao"] ?? "";

    try {

        // ==================================
        // CADASTRAR OU EDITAR ORÇAMENTO
        // ==================================

        if ($acao === "salvar") {

            $id = (int) ($_POST["orcamento_id"] ?? 0);

            $categoriaId = (int) ($_POST["categoria_id"] ?? 0);

            $nome = trim($_POST["nome"] ?? "");

            $valorInformado = trim(
                (string) ($_POST["valor_limite"] ?? "")
            );

            $ano = (int) ($_POST["ano"] ?? 0);

            $mes = (int) ($_POST["mes"] ?? 0);

            $alertaPercentual = (int) (
                $_POST["alerta_percentual"] ?? 80
            );


            // Converte valor brasileiro para DECIMAL
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


            $valoresFormulario = [

                "id" => $id,
                "categoria_id" => (string) $categoriaId,
                "nome" => $nome,
                "valor_limite" => $valorInformado,
                "ano" => $ano,
                "mes" => $mes,
                "alerta_percentual" => $alertaPercentual

            ];

            $mostrarFormulario = true;


            // ==================================
            // VALIDAR NOME
            // ==================================

            $tamanhoNome = function_exists("mb_strlen")
                ? mb_strlen($nome, "UTF-8")
                : strlen($nome);

            if ($tamanhoNome < 1 || $tamanhoNome > 150) {

                $erroFormulario = "Digite um nome válido para o orçamento.";

            }


            // ==================================
            // VALIDAR CATEGORIA
            // ==================================

            if (
                $erroFormulario === "" &&
                $categoriaId <= 0
            ) {

                $erroFormulario = "Selecione uma categoria.";

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

                $erroFormulario = "Digite um limite válido. Exemplo: 1.234,56.";

            }

            if (
                $erroFormulario === "" &&
                !preg_match(
                    '/^\d{1,13}(?:\.\d{1,2})?$/',
                    $valorBanco
                )
            ) {

                $erroFormulario = "O valor ultrapassa o limite permitido pelo banco de dados.";

            }

            if (
                $erroFormulario === "" &&
                preg_match('/^0+(?:\.0+)?$/', $valorBanco)
            ) {

                $erroFormulario = "O limite do orçamento precisa ser maior que zero.";

            }


            // ==================================
            // VALIDAR ANO E MÊS
            // ==================================

            if (
                $erroFormulario === "" &&
                ($ano < 1 || $ano > 65535)
            ) {

                $erroFormulario = "Informe um ano válido.";

            }

            if (
                $erroFormulario === "" &&
                ($mes < 1 || $mes > 12)
            ) {

                $erroFormulario = "Selecione um mês válido.";

            }


            // ==================================
            // VALIDAR PERCENTUAL DE ALERTA
            // ==================================

            if (
                $erroFormulario === "" &&
                ($alertaPercentual < 1 || $alertaPercentual > 100)
            ) {

                $erroFormulario = "O percentual de alerta deve estar entre 1 e 100.";

            }


            // ==================================
            // VERIFICAR ORÇAMENTO ORIGINAL
            // ==================================

            $registroOriginal = null;

            if (
                $erroFormulario === "" &&
                $id > 0
            ) {

                $consultaOriginal = $conexao->prepare(
                    "SELECT id, categoria_id
                     FROM orcamentos
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

                    $erroFormulario = "Orçamento não encontrado.";

                }

            }


            // ==================================
            // VERIFICAR CATEGORIA NO BANCO
            // ==================================

            if ($erroFormulario === "") {

                $consultaCategoria = $conexao->prepare(
                    "SELECT id, ativo
                     FROM categorias
                     WHERE id = ?
                     AND usuario_id = ?
                     AND tipo = 'despesa'
                     LIMIT 1"
                );

                $consultaCategoria->bind_param(
                    "ii",
                    $categoriaId,
                    $usuarioId
                );

                $consultaCategoria->execute();

                $categoriaEncontrada = $consultaCategoria
                    ->get_result()
                    ->fetch_assoc();

                $consultaCategoria->close();


                if (!$categoriaEncontrada) {

                    $erroFormulario = "A categoria selecionada não é uma categoria de despesa válida.";

                } elseif (
                    !(int) $categoriaEncontrada["ativo"] &&
                    !(
                        $registroOriginal &&
                        (int) $registroOriginal["categoria_id"] === $categoriaId
                    )
                ) {

                    $erroFormulario = "Não é possível utilizar uma categoria desativada em um novo orçamento.";

                }

            }


            // ==================================
            // VERIFICAR ORÇAMENTO DUPLICADO
            // ==================================

            if ($erroFormulario === "") {

                $consultaDuplicada = $conexao->prepare(
                    "SELECT id
                     FROM orcamentos
                     WHERE usuario_id = ?
                     AND categoria_id = ?
                     AND ano = ?
                     AND mes = ?
                     AND id <> ?
                     LIMIT 1"
                );

                $consultaDuplicada->bind_param(
                    "iiiii",
                    $usuarioId,
                    $categoriaId,
                    $ano,
                    $mes,
                    $id
                );

                $consultaDuplicada->execute();

                $duplicado = $consultaDuplicada
                    ->get_result()
                    ->fetch_assoc();

                $consultaDuplicada->close();


                if ($duplicado) {

                    $erroFormulario =
                        "Já existe um orçamento para esta categoria neste mês. Edite ou reative o orçamento existente.";

                }

            }


            // ==================================
            // SALVAR ORÇAMENTO
            // ==================================

            if ($erroFormulario === "") {

                // Atualizar
                if ($id > 0) {

                    $atualizacao = $conexao->prepare(
                        "UPDATE orcamentos
                         SET categoria_id = ?,
                             nome = ?,
                             valor_limite = ?,
                             ano = ?,
                             mes = ?,
                             alerta_percentual = ?
                         WHERE id = ?
                         AND usuario_id = ?"
                    );

                    $atualizacao->bind_param(
                        "issiiiii",
                        $categoriaId,
                        $nome,
                        $valorBanco,
                        $ano,
                        $mes,
                        $alertaPercentual,
                        $id,
                        $usuarioId
                    );

                    $atualizacao->execute();

                    $atualizacao->close();

                    $redirecionar(
                        "Orçamento atualizado com sucesso.",
                        "sucesso",
                        $mes,
                        $ano
                    );

                } else {

                    // Cadastrar
                    $insercao = $conexao->prepare(
                        "INSERT INTO orcamentos (
                            usuario_id,
                            categoria_id,
                            nome,
                            valor_limite,
                            ano,
                            mes,
                            alerta_percentual
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    $insercao->bind_param(
                        "iissiii",
                        $usuarioId,
                        $categoriaId,
                        $nome,
                        $valorBanco,
                        $ano,
                        $mes,
                        $alertaPercentual
                    );

                    $insercao->execute();

                    $insercao->close();

                    $redirecionar(
                        "Orçamento cadastrado com sucesso.",
                        "sucesso",
                        $mes,
                        $ano
                    );

                }

            }

        }


        // ==================================
        // ATIVAR OU DESATIVAR ORÇAMENTO
        // ==================================

        elseif ($acao === "alterar_status") {

            $id = (int) ($_POST["orcamento_id"] ?? 0);

            $novoStatus = (int) ($_POST["novo_status"] ?? -1);

            if (
                $id <= 0 ||
                !in_array($novoStatus, [0, 1], true)
            ) {

                $redirecionar(
                    "Solicitação inválida.",
                    "erro"
                );

            }


            $atualizacao = $conexao->prepare(
                "UPDATE orcamentos
                 SET ativo = ?
                 WHERE id = ?
                 AND usuario_id = ?"
            );

            $atualizacao->bind_param(
                "iii",
                $novoStatus,
                $id,
                $usuarioId
            );

            $atualizacao->execute();

            $alteradas = $atualizacao->affected_rows;

            $atualizacao->close();


            if ($alteradas === 0) {

                // Confere se o orçamento existe mesmo sem alteração
                $consulta = $conexao->prepare(
                    "SELECT id
                     FROM orcamentos
                     WHERE id = ?
                     AND usuario_id = ?
                     LIMIT 1"
                );

                $consulta->bind_param(
                    "ii",
                    $id,
                    $usuarioId
                );

                $consulta->execute();

                $existe = $consulta
                    ->get_result()
                    ->fetch_assoc();

                $consulta->close();

                if (!$existe) {

                    $redirecionar(
                        "Orçamento não encontrado.",
                        "erro"
                    );

                }

            }


            $texto = $novoStatus === 1
                ? "Orçamento reativado com sucesso."
                : "Orçamento desativado com sucesso.";

            $redirecionar($texto);

        }

    } catch (mysqli_sql_exception $erroBanco) {

        error_log(
            "Erro no módulo de orçamentos JABOO: " .
            $erroBanco->getMessage()
        );

        if ((int) $erroBanco->getCode() === 1062) {

            $erroFormulario =
                "Já existe um orçamento para esta categoria neste mês.";

        } else {

            $erroFormulario =
                "Não foi possível concluir a operação. Tente novamente.";

        }

        $mostrarFormulario = true;

    }

}


// ==========================================
// CARREGAR ORÇAMENTO PARA EDIÇÃO
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
            categoria_id,
            nome,
            valor_limite,
            ano,
            mes,
            alerta_percentual
         FROM orcamentos
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

    $orcamentoEncontrado = $consultaEdicao
        ->get_result()
        ->fetch_assoc();

    $consultaEdicao->close();


    if ($orcamentoEncontrado) {

        $valoresFormulario = [

            "id" => (int) $orcamentoEncontrado["id"],

            "categoria_id" => (string) $orcamentoEncontrado["categoria_id"],

            "nome" => $orcamentoEncontrado["nome"],

            "valor_limite" => jaboo_formatar_moeda_orcamentos(
                $orcamentoEncontrado["valor_limite"]
            ),

            "ano" => (int) $orcamentoEncontrado["ano"],

            "mes" => (int) $orcamentoEncontrado["mes"],

            "alerta_percentual" => (int) $orcamentoEncontrado["alerta_percentual"]

        ];

        $mostrarFormulario = true;

    } else {

        $erroFormulario = "Orçamento não encontrado.";

        $mostrarFormulario = true;

    }

}


// ==========================================
// CONSULTAR CATEGORIAS DE DESPESA
// ==========================================

$consultaCategorias = $conexao->prepare(
    "SELECT
        id,
        nome,
        cor,
        ativo
     FROM categorias
     WHERE usuario_id = ?
     AND tipo = 'despesa'
     ORDER BY ativo DESC, nome ASC"
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
// RESUMO DOS ORÇAMENTOS DO PERÍODO
// ==========================================

$resumoConsulta = $conexao->prepare(

    "SELECT

        COUNT(o.id) AS quantidade,

        COALESCE(SUM(o.valor_limite), 0) AS total_limites,

        COALESCE(
            SUM(COALESCE(g.total_gasto, 0)),
            0
        ) AS total_gastos,

        COALESCE(
            SUM(
                o.valor_limite -
                COALESCE(g.total_gasto, 0)
            ),
            0
        ) AS total_disponivel

     FROM orcamentos o

     LEFT JOIN (

        SELECT
            categoria_id,
            SUM(valor) AS total_gasto

        FROM transacoes

        WHERE usuario_id = ?
        AND tipo = 'despesa'
        AND status = 'efetivada'
        AND data_competencia BETWEEN ? AND ?

        GROUP BY categoria_id

     ) g

        ON g.categoria_id = o.categoria_id

     WHERE o.usuario_id = ?
     AND o.ano = ?
     AND o.mes = ?
     AND o.ativo = 1"

);

$resumoConsulta->bind_param(
    "issiii",
    $usuarioId,
    $dataInicio,
    $dataFim,
    $usuarioId,
    $anoSelecionado,
    $mesSelecionado
);

$resumoConsulta->execute();

$resumo = $resumoConsulta
    ->get_result()
    ->fetch_assoc();

$resumoConsulta->close();


$totalOrcamentos = (int) $resumo["quantidade"];

$totalLimites = jaboo_formatar_moeda_orcamentos(
    $resumo["total_limites"]
);

$totalGastos = jaboo_formatar_moeda_orcamentos(
    $resumo["total_gastos"]
);

$totalDisponivel = jaboo_formatar_moeda_orcamentos(
    $resumo["total_disponivel"]
);


// ==========================================
// CONSULTAR ORÇAMENTOS E GASTOS
// ==========================================

$consultaOrcamentos = $conexao->prepare(

    "SELECT

        o.id,
        o.categoria_id,
        o.nome,
        o.valor_limite,
        o.ano,
        o.mes,
        o.alerta_percentual,
        o.ativo,

        c.nome AS categoria_nome,
        c.cor AS categoria_cor,

        COALESCE(g.total_gasto, 0) AS total_gasto,

        (
            o.valor_limite -
            COALESCE(g.total_gasto, 0)
        ) AS valor_disponivel

     FROM orcamentos o

     INNER JOIN categorias c

        ON c.id = o.categoria_id
        AND c.usuario_id = o.usuario_id

     LEFT JOIN (

        SELECT
            categoria_id,
            SUM(valor) AS total_gasto

        FROM transacoes

        WHERE usuario_id = ?
        AND tipo = 'despesa'
        AND status = 'efetivada'
        AND data_competencia BETWEEN ? AND ?

        GROUP BY categoria_id

     ) g

        ON g.categoria_id = o.categoria_id

     WHERE o.usuario_id = ?
     AND o.ano = ?
     AND o.mes = ?

     ORDER BY o.ativo DESC, o.nome ASC"

);

$consultaOrcamentos->bind_param(
    "issiii",
    $usuarioId,
    $dataInicio,
    $dataFim,
    $usuarioId,
    $anoSelecionado,
    $mesSelecionado
);

$consultaOrcamentos->execute();

$resultadoOrcamentos = $consultaOrcamentos->get_result();

$orcamentos = [];

while ($orcamento = $resultadoOrcamentos->fetch_assoc()) {

    $orcamentos[] = $orcamento;

}

$consultaOrcamentos->close();


// ==========================================
// MESES DO ANO
// ==========================================

$meses = [

    1 => "Janeiro",
    2 => "Fevereiro",
    3 => "Março",
    4 => "Abril",
    5 => "Maio",
    6 => "Junho",
    7 => "Julho",
    8 => "Agosto",
    9 => "Setembro",
    10 => "Outubro",
    11 => "Novembro",
    12 => "Dezembro"

];


// ==========================================
// ESTADOS DOS ORÇAMENTOS
// ==========================================

$nomesSituacao = [

    "normal" => "Dentro do limite",
    "alerta" => "Próximo do limite",
    "excedido" => "Limite ultrapassado"

];

?>

<div class="orcamentos-page">


    <!-- ==================================
         CABEÇALHO
    =================================== -->

    <section class="orcamentos-heading">

        <div>

            <span class="orcamentos-eyebrow">
                PLANEJAMENTO FINANCEIRO
            </span>

            <h2>Meus orçamentos</h2>

            <p>
                Organize seus gastos e acompanhe os limites mensais.
            </p>

        </div>

        <a
            href="index.php?pagina=orcamentos&novo=1&mes=<?= $mesSelecionado ?>&ano=<?= $anoSelecionado ?>#formulario-orcamento"
            class="orcamentos-add-button"
        >

            <span>+</span>

            Novo orçamento

        </a>

    </section>


    <!-- MENSAGENS -->

    <?php if ($mensagem !== ""): ?>

        <div class="orcamentos-alert <?= $tipoMensagem === "erro" ? "erro" : "sucesso" ?>">

            <?= htmlspecialchars(
                $mensagem,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>


    <?php if ($erroFormulario !== ""): ?>

        <div class="orcamentos-alert erro">

            <?= htmlspecialchars(
                $erroFormulario,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ==================================
         FILTRO DE PERÍODO
    =================================== -->

    <section class="orcamentos-periodo">

        <form method="GET" action="index.php">

            <input
                type="hidden"
                name="pagina"
                value="orcamentos"
            >

            <div class="orcamentos-periodo-grupo">

                <label for="mes">Mês</label>

                <select id="mes" name="mes">

                    <?php foreach ($meses as $numero => $nomeMes): ?>

                        <option
                            value="<?= $numero ?>"
                            <?= $mesSelecionado === $numero ? "selected" : "" ?>
                        >

                            <?= $nomeMes ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="orcamentos-periodo-grupo">

                <label for="ano">Ano</label>

                <input
                    type="number"
                    id="ano"
                    name="ano"
                    min="1"
                    max="65535"
                    value="<?= $anoSelecionado ?>"
                    required
                >

            </div>


            <button type="submit">
                Consultar período
            </button>

        </form>

        <span class="orcamentos-periodo-label">
            <?= $meses[$mesSelecionado] ?> de <?= $anoSelecionado ?>
        </span>

    </section>


    <!-- ==================================
         RESUMO
    =================================== -->

    <section class="orcamentos-resumo">

        <article class="orcamentos-resumo-card">

            <span>Orçamentos ativos</span>

            <strong>
                <?= $totalOrcamentos ?>
            </strong>

            <small>
                Categorias com limite definido
            </small>

        </article>


        <article class="orcamentos-resumo-card">

            <span>Total planejado</span>

            <strong>
                R$ <?= $totalLimites ?>
            </strong>

            <small>
                Soma dos limites do período
            </small>

        </article>


        <article class="orcamentos-resumo-card">

            <span>Total utilizado</span>

            <strong>
                R$ <?= $totalGastos ?>
            </strong>

            <small>
                Despesas efetivadas nas categorias orçadas
            </small>

        </article>


        <article class="orcamentos-resumo-card">

            <span>Disponível nos orçamentos</span>

            <strong class="<?= (float) $resumo["total_disponivel"] < 0 ? "orcamento-texto-negativo" : "orcamento-texto-positivo" ?>">

                R$ <?= $totalDisponivel ?>

            </strong>

            <small>
                Limites menos despesas efetivadas
            </small>

        </article>

    </section>


    <!-- ==================================
         FORMULÁRIO
    =================================== -->

    <?php if ($mostrarFormulario): ?>

        <section
            class="orcamentos-form-section"
            id="formulario-orcamento"
        >

            <div class="orcamentos-section-heading">

                <div>

                    <h3>
                        <?= $valoresFormulario["id"] > 0
                            ? "Editar orçamento"
                            : "Novo orçamento"
                        ?>
                    </h3>

                    <p>
                        Defina um limite de gastos para uma categoria.
                    </p>

                </div>

                <a
                    href="index.php?pagina=orcamentos&mes=<?= $mesSelecionado ?>&ano=<?= $anoSelecionado ?>"
                    class="orcamentos-close"
                >
                    Fechar
                </a>

            </div>


            <?php if (count($categorias) === 0): ?>

                <div class="orcamentos-alert erro">

                    Você ainda não possui categorias de despesas cadastradas.

                    Cadastre uma categoria antes de criar um orçamento.

                </div>

            <?php else: ?>


                <form
                    method="POST"
                    action="index.php?pagina=orcamentos"
                    class="orcamentos-form"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            jaboo_csrf_token(),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="acao"
                        value="salvar"
                    >

                    <input
                        type="hidden"
                        name="orcamento_id"
                        value="<?= (int) $valoresFormulario["id"] ?>"
                    >


                    <div class="orcamentos-form-grid">


                        <!-- CATEGORIA -->

                        <div class="orcamentos-input-group">

                            <label for="categoria_id">
                                Categoria de despesa
                            </label>

                            <select
                                id="categoria_id"
                                name="categoria_id"
                                required
                            >

                                <option value="">
                                    Selecione uma categoria
                                </option>

                                <?php foreach ($categorias as $categoria): ?>

                                    <?php

                                    $categoriaSelecionada =
                                        (int) $valoresFormulario["categoria_id"] ===
                                        (int) $categoria["id"];

                                    $categoriaInativa =
                                        !(int) $categoria["ativo"];

                                    ?>

                                    <?php if ($categoriaInativa && !$categoriaSelecionada): ?>
                                        <?php continue; ?>
                                    <?php endif; ?>

                                    <option
                                        value="<?= (int) $categoria["id"] ?>"
                                        <?= $categoriaSelecionada ? "selected" : "" ?>
                                    >

                                        <?= htmlspecialchars(
                                            $categoria["nome"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                        <?= $categoriaInativa ? " (inativa)" : "" ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- NOME -->

                        <div class="orcamentos-input-group">

                            <label for="nome">
                                Nome do orçamento
                            </label>

                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                placeholder="Ex.: Limite mensal de alimentação"
                                maxlength="150"
                                value="<?= htmlspecialchars(
                                    $valoresFormulario["nome"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- VALOR -->

                        <div class="orcamentos-input-group">

                            <label for="valor_limite">
                                Limite mensal (R$)
                            </label>

                            <input
                                type="text"
                                id="valor_limite"
                                name="valor_limite"
                                placeholder="0,00"
                                inputmode="decimal"
                                maxlength="40"
                                autocomplete="off"
                                value="<?= htmlspecialchars(
                                    $valoresFormulario["valor_limite"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                required
                            >

                            <small>
                                Exemplo: 1.000,00.
                            </small>

                        </div>


                        <!-- MÊS -->

                        <div class="orcamentos-input-group">

                            <label for="form_mes">
                                Mês
                            </label>

                            <select
                                id="form_mes"
                                name="mes"
                                required
                            >

                                <?php foreach ($meses as $numero => $nomeMes): ?>

                                    <option
                                        value="<?= $numero ?>"
                                        <?= (int) $valoresFormulario["mes"] === $numero ? "selected" : "" ?>
                                    >

                                        <?= $nomeMes ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- ANO -->

                        <div class="orcamentos-input-group">

                            <label for="form_ano">
                                Ano
                            </label>

                            <input
                                type="number"
                                id="form_ano"
                                name="ano"
                                min="1"
                                max="65535"
                                value="<?= (int) $valoresFormulario["ano"] ?>"
                                required
                            >

                        </div>


                        <!-- ALERTA -->

                        <div class="orcamentos-input-group">

                            <label for="alerta_percentual">
                                Alertar quando atingir
                            </label>

                            <select
                                id="alerta_percentual"
                                name="alerta_percentual"
                                required
                            >

                                <?php foreach ([50, 60, 70, 75, 80, 85, 90, 95, 100] as $percentual): ?>

                                    <option
                                        value="<?= $percentual ?>"
                                        <?= (int) $valoresFormulario["alerta_percentual"] === $percentual ? "selected" : "" ?>
                                    >

                                        <?= $percentual ?>% do limite

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <div class="orcamentos-form-actions">

                        <button
                            type="submit"
                            class="orcamentos-submit-button"
                        >

                            <?= $valoresFormulario["id"] > 0
                                ? "Salvar alterações"
                                : "Criar orçamento"
                            ?>

                        </button>

                        <a
                            href="index.php?pagina=orcamentos&mes=<?= $mesSelecionado ?>&ano=<?= $anoSelecionado ?>"
                            class="orcamentos-cancel-button"
                        >
                            Cancelar
                        </a>

                    </div>

                </form>

            <?php endif; ?>

        </section>

    <?php endif; ?>


    <!-- ==================================
         LISTA DE ORÇAMENTOS
    =================================== -->

    <section class="orcamentos-list-section">

        <div class="orcamentos-section-heading">

            <div>

                <h3>Orçamentos de <?= $meses[$mesSelecionado] ?></h3>

                <p>
                    Acompanhe o consumo de cada categoria.
                </p>

            </div>

            <span class="orcamentos-counter">
                <?= count($orcamentos) ?> registros
            </span>

        </div>


        <?php if (count($orcamentos) > 0): ?>

            <div class="orcamentos-list">

                <?php foreach ($orcamentos as $orcamento): ?>

                    <?php

                    $limite = (float) $orcamento["valor_limite"];

                    $gasto = (float) $orcamento["total_gasto"];

                    $disponivel = (float) $orcamento["valor_disponivel"];

                    $percentual = $limite > 0
                        ? ($gasto / $limite) * 100
                        : 0;

                    $larguraBarra = min(
                        100,
                        max(0, $percentual)
                    );

                    if ($percentual > 100) {

                        $situacao = "excedido";

                    } elseif (
                        $percentual >= (int) $orcamento["alerta_percentual"]
                    ) {

                        $situacao = "alerta";

                    } else {

                        $situacao = "normal";

                    }

                    $corCategoria = preg_match(
                        '/^#[0-9A-Fa-f]{6}$/',
                        $orcamento["categoria_cor"] ?? ""
                    ) ? $orcamento["categoria_cor"] : "#2E7D32";

                    $limiteFormatado = jaboo_formatar_moeda_orcamentos(
                        $orcamento["valor_limite"]
                    );

                    $gastoFormatado = jaboo_formatar_moeda_orcamentos(
                        $orcamento["total_gasto"]
                    );

                    $disponivelFormatado = jaboo_formatar_moeda_orcamentos(
                        $orcamento["valor_disponivel"]
                    );

                    ?>


                    <article class="orcamento-item <?= !$orcamento["ativo"] ? "orcamento-inativo" : "" ?>">

                        <div class="orcamento-item-topo">

                            <div class="orcamento-item-identificacao">

                                <div
                                    class="orcamento-categoria-icon"
                                    style="--categoria-cor: <?= htmlspecialchars(
                                        $corCategoria,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                >
                                    <span>●</span>
                                </div>


                                <div>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $orcamento["nome"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars(
                                            $orcamento["categoria_nome"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </span>

                                </div>

                            </div>


                            <?php if (!$orcamento["ativo"]): ?>

                                <span class="orcamento-status-inativo">
                                    Desativado
                                </span>

                            <?php else: ?>

                                <span class="orcamento-situacao <?= $situacao ?>">

                                    <?= $nomesSituacao[$situacao] ?>

                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="orcamento-valores">

                            <div>

                                <span>Gasto</span>

                                <strong>
                                    R$ <?= $gastoFormatado ?>
                                </strong>

                            </div>


                            <div>

                                <span>Limite</span>

                                <strong>
                                    R$ <?= $limiteFormatado ?>
                                </strong>

                            </div>


                            <div>

                                <span>Disponível</span>

                                <strong class="<?= $disponivel < 0 ? "orcamento-texto-negativo" : "orcamento-texto-positivo" ?>">

                                    R$ <?= $disponivelFormatado ?>

                                </strong>

                            </div>

                        </div>


                        <div class="orcamento-progresso-topo">

                            <span>
                                <?= number_format($percentual, 1, ",", ".") ?>% utilizado
                            </span>

                            <span>
                                Alerta em <?= (int) $orcamento["alerta_percentual"] ?>%
                            </span>

                        </div>


                        <div class="orcamento-progresso">

                            <div
                                class="orcamento-progresso-preenchido <?= $situacao ?>"
                                style="width: <?= number_format($larguraBarra, 2, ".", "") ?>%"
                            ></div>

                        </div>


                        <div class="orcamento-item-rodape">

                            <span>
                                <?= (int) $orcamento["mes"] ?>/<?= (int) $orcamento["ano"] ?>
                            </span>


                            <div class="orcamento-acoes">

                                <a
                                    href="index.php?pagina=orcamentos&editar=<?= (int) $orcamento["id"] ?>&mes=<?= $mesSelecionado ?>&ano=<?= $anoSelecionado ?>#formulario-orcamento"
                                    class="orcamento-editar"
                                >
                                    Editar
                                </a>


                                <form
                                    method="POST"
                                    action="index.php?pagina=orcamentos&mes=<?= $mesSelecionado ?>&ano=<?= $anoSelecionado ?>"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            jaboo_csrf_token(),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="acao"
                                        value="alterar_status"
                                    >

                                    <input
                                        type="hidden"
                                        name="orcamento_id"
                                        value="<?= (int) $orcamento["id"] ?>"
                                    >


                                    <?php if ($orcamento["ativo"]): ?>

                                        <input
                                            type="hidden"
                                            name="novo_status"
                                            value="0"
                                        >

                                        <button
                                            type="submit"
                                            class="orcamento-desativar"
                                        >
                                            Desativar
                                        </button>

                                    <?php else: ?>

                                        <input
                                            type="hidden"
                                            name="novo_status"
                                            value="1"
                                        >

                                        <button
                                            type="submit"
                                            class="orcamento-reativar"
                                        >
                                            Reativar
                                        </button>

                                    <?php endif; ?>

                                </form>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>


        <?php else: ?>

            <div class="orcamentos-empty">

                <div class="orcamentos-empty-icon">
                    %
                </div>

                <h4>Nenhum orçamento neste período</h4>

                <p>
                    Crie um orçamento para começar a controlar seus gastos mensais.
                </p>

                <?php if (!$mostrarFormulario): ?>

                    <a
                        href="index.php?pagina=orcamentos&novo=1&mes=<?= $mesSelecionado ?>&ano=<?= $anoSelecionado ?>#formulario-orcamento"
                    >
                        Criar primeiro orçamento
                    </a>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </section>

</div>


<!-- ==========================================
     FORMATAÇÃO AUTOMÁTICA DO VALOR
========================================== -->

<script>

(function () {

    const campo = document.getElementById("valor_limite");

    if (!campo) {
        return;
    }

    const LIMITE_DIGITOS = 15;

    function formatarValor() {

        let digitos = campo.value.replace(/\D/g, "");

        digitos = digitos.replace(/^0+/, "");

        if (digitos === "") {

            campo.value = "";

            campo.setCustomValidity("");

            return;

        }

        if (digitos.length > LIMITE_DIGITOS) {

            campo.setCustomValidity(
                "O limite ultrapassa o valor permitido pelo banco de dados."
            );

        } else {

            campo.setCustomValidity("");

        }

        digitos = digitos.padStart(3, "0");

        const reais = digitos.slice(0, -2);

        const centavos = digitos.slice(-2);

        const reaisFormatados = reais.replace(
            /\B(?=(\d{3})+(?!\d))/g,
            "."
        );

        campo.value = reaisFormatados + "," + centavos;

        campo.setSelectionRange(
            campo.value.length,
            campo.value.length
        );

    }

    campo.addEventListener("focus", function () {

        if (campo.value === "0,00") {
            campo.select();
        }

    });

    campo.addEventListener("input", formatarValor);

    campo.addEventListener("blur", function () {

        if (campo.value.trim() === "") {

            campo.value = "";

            campo.setCustomValidity("");

        }

    });

})();

</script>
