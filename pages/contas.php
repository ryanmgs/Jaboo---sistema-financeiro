
<?php

// ==========================================
// JABOO - MÓDULO DE CONTAS
// ==========================================

if (basename($_SERVER["SCRIPT_FILENAME"] ?? "") === basename(__FILE__)) {
    header("Location: ../index.php?pagina=contas");
    exit;
}

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/database.php";

jaboo_exigir_login();

$usuarioId = (int) $_SESSION["usuario_id"];


// ==========================================
// FUNÇÃO DE FORMATAÇÃO MONETÁRIA
// ==========================================

function jaboo_formatar_moeda_contas($valor): string {

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
// MENSAGENS
// ==========================================

$mensagem = $_SESSION["contas_mensagem"] ?? "";

$tipoMensagem = $_SESSION["contas_tipo_mensagem"] ?? "sucesso";

unset(
    $_SESSION["contas_mensagem"],
    $_SESSION["contas_tipo_mensagem"]
);

$erroFormulario = "";


// ==========================================
// VALORES PADRÃO DO FORMULÁRIO
// ==========================================

$valoresFormulario = [

    "id" => 0,
    "nome" => "",
    "tipo" => "banco",
    "instituicao" => "",
    "saldo_inicial" => "0,00",
    "cor" => "#2E7D32",
    "incluir_no_patrimonio" => 1

];


// ==========================================
// REDIRECIONAMENTO COM MENSAGEM
// ==========================================

$redirecionar = function ($texto, $tipo = "sucesso") {

    $_SESSION["contas_mensagem"] = $texto;

    $_SESSION["contas_tipo_mensagem"] = $tipo;

    header("Location: index.php?pagina=contas");

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
        // CADASTRAR OU EDITAR CONTA
        // ==================================

        if ($acao === "salvar") {

            $id = (int) ($_POST["conta_id"] ?? 0);

            $nome = trim($_POST["nome"] ?? "");

            $tipo = $_POST["tipo"] ?? "banco";

            $instituicao = trim($_POST["instituicao"] ?? "");

            $saldoInformado = trim(
                (string) ($_POST["saldo_inicial"] ?? "0,00")
            );

            // Converte formato brasileiro para DECIMAL
            $saldoSemMilhar = str_replace(
                ".",
                "",
                $saldoInformado
            );

            $saldoBanco = str_replace(
                ",",
                ".",
                $saldoSemMilhar
            );

            $cor = trim($_POST["cor"] ?? "#2E7D32");

            $incluirPatrimonio = isset(
                $_POST["incluir_no_patrimonio"]
            ) ? 1 : 0;


            $valoresFormulario = [

                "id" => $id,
                "nome" => $nome,
                "tipo" => $tipo,
                "instituicao" => $instituicao,
                "saldo_inicial" => $saldoInformado,
                "cor" => $cor,
                "incluir_no_patrimonio" => $incluirPatrimonio

            ];


            // ==================================
            // VALIDAR NOME
            // ==================================

            $tamanhoNome = function_exists("mb_strlen")
                ? mb_strlen($nome, "UTF-8")
                : strlen($nome);

            if ($tamanhoNome < 1 || $tamanhoNome > 100) {

                $erroFormulario = "Digite um nome válido para a conta.";

            }


            // ==================================
            // VALIDAR TIPO
            // ==================================

            $tiposPermitidos = [
                "banco",
                "dinheiro",
                "carteira_digital",
                "outros"
            ];

            if (
                $erroFormulario === "" &&
                !in_array($tipo, $tiposPermitidos, true)
            ) {

                $erroFormulario = "Selecione um tipo de conta válido.";

            }


            // ==================================
            // VALIDAR INSTITUIÇÃO
            // ==================================

            if (
                $erroFormulario === "" &&
                strlen($instituicao) > 100
            ) {

                $erroFormulario = "O nome da instituição deve ter até 100 caracteres.";

            }


            // ==================================
            // VALIDAR FORMATO DO SALDO
            // ==================================

            if (
                $erroFormulario === "" &&
                !preg_match(
                    '/^-?(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?$/',
                    $saldoInformado
                )
            ) {

                $erroFormulario = "Digite um saldo válido. Exemplo: 1.234,56.";

            }


            // ==================================
            // VALIDAR LIMITE DECIMAL(15,2)
            // ==================================

            if (
                $erroFormulario === "" &&
                !preg_match(
                    '/^-?\d{1,13}(?:\.\d{1,2})?$/',
                    $saldoBanco
                )
            ) {

                $erroFormulario = "O saldo ultrapassa o limite permitido. Utilize até 13 dígitos inteiros e 2 casas decimais.";

            }


            // ==================================
            // VALIDAR COR
            // ==================================

            if (
                $erroFormulario === "" &&
                !preg_match('/^#[0-9A-Fa-f]{6}$/', $cor)
            ) {

                $erroFormulario = "Selecione uma cor válida.";

            }


            // ==================================
            // SALVAR
            // ==================================

            if ($erroFormulario === "") {

                $instituicaoBanco = $instituicao !== ""
                    ? $instituicao
                    : null;


                // ==================================
                // EDITAR CONTA
                // ==================================

                if ($id > 0) {

                    $verificacao = $conexao->prepare(
                        "SELECT id
                         FROM contas
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

                    $contaExiste = $verificacao
                        ->get_result()
                        ->fetch_assoc();

                    $verificacao->close();


                    if (!$contaExiste) {

                        $erroFormulario = "Conta não encontrada.";

                    } else {

                        $atualizacao = $conexao->prepare(
                            "UPDATE contas
                             SET nome = ?,
                                 tipo = ?,
                                 instituicao = ?,
                                 saldo_inicial = ?,
                                 cor = ?,
                                 incluir_no_patrimonio = ?
                             WHERE id = ?
                             AND usuario_id = ?"
                        );

                        $atualizacao->bind_param(
                            "sssssiii",
                            $nome,
                            $tipo,
                            $instituicaoBanco,
                            $saldoBanco,
                            $cor,
                            $incluirPatrimonio,
                            $id,
                            $usuarioId
                        );

                        $atualizacao->execute();

                        $atualizacao->close();

                        $redirecionar(
                            "Conta atualizada com sucesso."
                        );

                    }

                } else {

                    // ==================================
                    // CADASTRAR CONTA
                    // ==================================

                    $insercao = $conexao->prepare(
                        "INSERT INTO contas (
                            usuario_id,
                            nome,
                            tipo,
                            instituicao,
                            saldo_inicial,
                            cor,
                            incluir_no_patrimonio
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    $insercao->bind_param(
                        "isssssi",
                        $usuarioId,
                        $nome,
                        $tipo,
                        $instituicaoBanco,
                        $saldoBanco,
                        $cor,
                        $incluirPatrimonio
                    );

                    $insercao->execute();

                    $insercao->close();

                    $redirecionar(
                        "Conta cadastrada com sucesso."
                    );

                }

            }

        }


        // ==================================
        // ATIVAR OU DESATIVAR CONTA
        // ==================================

        elseif ($acao === "alterar_status") {

            $id = (int) ($_POST["conta_id"] ?? 0);

            $novoStatus = (int) ($_POST["novo_status"] ?? 0);

            if (
                $id <= 0 ||
                !in_array($novoStatus, [0, 1], true)
            ) {

                $redirecionar(
                    "Solicitação inválida.",
                    "erro"
                );

            }


            $verificacao = $conexao->prepare(
                "SELECT id
                 FROM contas
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

            $contaExiste = $verificacao
                ->get_result()
                ->fetch_assoc();

            $verificacao->close();


            if (!$contaExiste) {

                $redirecionar(
                    "Conta não encontrada.",
                    "erro"
                );

            }


            $atualizacao = $conexao->prepare(
                "UPDATE contas
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

            $atualizacao->close();


            $texto = $novoStatus === 1
                ? "Conta reativada com sucesso."
                : "Conta desativada com sucesso.";

            $redirecionar($texto);

        }

    } catch (mysqli_sql_exception $erroBanco) {

        error_log(
            "Erro no módulo de contas JABOO: " .
            $erroBanco->getMessage()
        );

        $erroFormulario = "Não foi possível concluir a operação. Tente novamente.";

    }

}


// ==========================================
// CARREGAR CONTA PARA EDIÇÃO
// ==========================================

$contaEdicao = isset($_GET["editar"])
    ? (int) $_GET["editar"]
    : 0;

if (
    $contaEdicao > 0 &&
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {

    $consultaEdicao = $conexao->prepare(
        "SELECT
            id,
            nome,
            tipo,
            instituicao,
            saldo_inicial,
            cor,
            incluir_no_patrimonio

         FROM contas

         WHERE id = ?
         AND usuario_id = ?

         LIMIT 1"
    );

    $consultaEdicao->bind_param(
        "ii",
        $contaEdicao,
        $usuarioId
    );

    $consultaEdicao->execute();

    $contaEncontrada = $consultaEdicao
        ->get_result()
        ->fetch_assoc();

    $consultaEdicao->close();


    if ($contaEncontrada) {

        $valoresFormulario = [

            "id" => (int) $contaEncontrada["id"],

            "nome" => $contaEncontrada["nome"],

            "tipo" => $contaEncontrada["tipo"],

            "instituicao" => $contaEncontrada["instituicao"] ?? "",

            "saldo_inicial" => jaboo_formatar_moeda_contas(
                $contaEncontrada["saldo_inicial"]
            ),

            "cor" => $contaEncontrada["cor"],

            "incluir_no_patrimonio" =>
                (int) $contaEncontrada["incluir_no_patrimonio"]

        ];

    } else {

        $mensagem = "Conta não encontrada.";

        $tipoMensagem = "erro";

    }

}


// ==========================================
// CONSULTAR RESUMO DOS SALDOS ATUAIS
// ==========================================

$resumoConsulta = $conexao->prepare(

    "SELECT
        COUNT(*) AS quantidade,

        COALESCE(
            SUM(
                c.saldo_inicial
                + COALESCE(m.total_receitas, 0)
                - COALESCE(m.total_despesas, 0)
            ),
            0
        ) AS saldo_atual_total

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
     AND c.ativo = 1"

);

$resumoConsulta->bind_param(
    "i",
    $usuarioId
);

$resumoConsulta->execute();

$resumo = $resumoConsulta
    ->get_result()
    ->fetch_assoc();

$resumoConsulta->close();


$totalContas = (int) $resumo["quantidade"];

$totalSaldoAtual = jaboo_formatar_moeda_contas(
    $resumo["saldo_atual_total"]
);


// ==========================================
// CONSULTAR TODAS AS CONTAS E SALDOS
// ==========================================

$consultaContas = $conexao->prepare(

    "SELECT

        c.id,
        c.nome,
        c.tipo,
        c.instituicao,
        c.saldo_inicial,
        c.cor,
        c.incluir_no_patrimonio,
        c.ativo,

        COALESCE(m.total_receitas, 0) AS total_receitas,

        COALESCE(m.total_despesas, 0) AS total_despesas,

        (
            c.saldo_inicial
            + COALESCE(m.total_receitas, 0)
            - COALESCE(m.total_despesas, 0)
        ) AS saldo_atual

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

     ORDER BY c.ativo DESC, c.nome ASC"

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
// TIPOS DE CONTA
// ==========================================

$tiposNomes = [

    "banco" => "Conta bancária",
    "dinheiro" => "Dinheiro em espécie",
    "carteira_digital" => "Carteira digital",
    "outros" => "Outros"

];

$tiposIcones = [

    "banco" => "🏦",
    "dinheiro" => "💵",
    "carteira_digital" => "📱",
    "outros" => "💼"

];

?>

<div class="contas-page">


    <!-- CABEÇALHO -->

    <section class="contas-heading">

        <div>

            <span class="contas-eyebrow">
                ORGANIZAÇÃO FINANCEIRA
            </span>

            <h2>Minhas contas</h2>

            <p>
                Gerencie suas contas e acompanhe seus saldos.
            </p>

        </div>

        <a href="#formulario-conta" class="contas-add-button">

            <span>+</span>

            Adicionar conta

        </a>

    </section>


    <!-- MENSAGENS -->

    <?php if ($mensagem !== ""): ?>

        <div class="contas-alert <?= $tipoMensagem === "erro" ? "erro" : "sucesso" ?>">

            <?= htmlspecialchars($mensagem, ENT_QUOTES, "UTF-8") ?>

        </div>

    <?php endif; ?>


    <?php if ($erroFormulario !== ""): ?>

        <div class="contas-alert erro">

            <?= htmlspecialchars($erroFormulario, ENT_QUOTES, "UTF-8") ?>

        </div>

    <?php endif; ?>


    <!-- RESUMO -->

    <section class="contas-resumo">

        <article class="contas-resumo-card">

            <div class="contas-resumo-icon">
                <span>▣</span>
            </div>

            <div>

                <span class="contas-resumo-label">
                    Contas ativas
                </span>

                <strong>
                    <?= $totalContas ?>
                </strong>

            </div>

        </article>


        <article class="contas-resumo-card">

            <div class="contas-resumo-icon">
                <span>R$</span>
            </div>

            <div>

                <span class="contas-resumo-label">
                    Saldo atual das contas ativas
                </span>

                <strong>
                    R$ <?= $totalSaldoAtual ?>
                </strong>

            </div>

        </article>

    </section>


    <!-- FORMULÁRIO -->

    <section class="contas-form-section" id="formulario-conta">

        <div class="contas-section-heading">

            <div>

                <h3>
                    <?= $valoresFormulario["id"] > 0
                        ? "Editar conta"
                        : "Adicionar nova conta"
                    ?>
                </h3>

                <p>
                    Informe os dados da conta financeira.
                </p>

            </div>

        </div>


        <form
            method="POST"
            action="index.php?pagina=contas"
            class="contas-form"
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
                name="conta_id"
                value="<?= (int) $valoresFormulario["id"] ?>"
            >


            <div class="contas-form-grid">


                <div class="contas-input-group">

                    <label for="nome">
                        Nome da conta
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        placeholder="Ex.: Conta principal"
                        maxlength="100"
                        value="<?= htmlspecialchars($valoresFormulario["nome"], ENT_QUOTES, "UTF-8") ?>"
                        required
                    >

                </div>


                <div class="contas-input-group">

                    <label for="tipo">
                        Tipo de conta
                    </label>

                    <select id="tipo" name="tipo" required>

                        <?php foreach ($tiposNomes as $valor => $nomeTipo): ?>

                            <option
                                value="<?= $valor ?>"
                                <?= $valoresFormulario["tipo"] === $valor ? "selected" : "" ?>
                            >

                                <?= $nomeTipo ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="contas-input-group">

                    <label for="instituicao">
                        Instituição
                    </label>

                    <input
                        type="text"
                        id="instituicao"
                        name="instituicao"
                        placeholder="Ex.: Banco ou instituição"
                        maxlength="100"
                        value="<?= htmlspecialchars($valoresFormulario["instituicao"], ENT_QUOTES, "UTF-8") ?>"
                    >

                </div>


                <div class="contas-input-group">

                    <label for="saldo_inicial">
                        Saldo inicial (R$)
                    </label>

                    <input
                        type="text"
                        id="saldo_inicial"
                        name="saldo_inicial"
                        placeholder="0,00"
                        inputmode="decimal"
                        maxlength="40"
                        autocomplete="off"
                        value="<?= htmlspecialchars($valoresFormulario["saldo_inicial"], ENT_QUOTES, "UTF-8") ?>"
                        required
                    >

                    <small class="contas-input-hint">
                        Utilize o formato brasileiro: 1.234,56.
                    </small>

                </div>


                <div class="contas-input-group">

                    <label for="cor">
                        Cor de identificação
                    </label>

                    <div class="contas-color-wrapper">

                        <input
                            type="color"
                            id="cor"
                            name="cor"
                            value="<?= htmlspecialchars($valoresFormulario["cor"], ENT_QUOTES, "UTF-8") ?>"
                        >

                        <span>
                            Escolha uma cor para identificar a conta.
                        </span>

                    </div>

                </div>

            </div>


            <!-- PATRIMÔNIO -->

            <div class="contas-checkbox-group">

                <input
                    type="checkbox"
                    id="incluir_no_patrimonio"
                    name="incluir_no_patrimonio"
                    value="1"
                    <?= $valoresFormulario["incluir_no_patrimonio"] ? "checked" : "" ?>
                >

                <label for="incluir_no_patrimonio">
                    Incluir esta conta no patrimônio total.
                </label>

            </div>


            <!-- BOTÕES -->

            <div class="contas-form-actions">

                <button type="submit" class="contas-submit-button">

                    <?= $valoresFormulario["id"] > 0
                        ? "Salvar alterações"
                        : "Cadastrar conta"
                    ?>

                </button>

                <?php if ($valoresFormulario["id"] > 0): ?>

                    <a
                        href="index.php?pagina=contas"
                        class="contas-cancel-button"
                    >
                        Cancelar edição
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </section>


    <!-- LISTA DE CONTAS -->

    <section class="contas-list-section">

        <div class="contas-section-heading">

            <div>

                <h3>Suas contas</h3>

                <p>
                    Contas cadastradas no JABOO.
                </p>

            </div>

            <span class="contas-counter">
                <?= count($contas) ?> registros
            </span>

        </div>


        <?php if (count($contas) > 0): ?>

            <div class="contas-list">

                <?php foreach ($contas as $conta): ?>

                    <?php

                    $idConta = (int) $conta["id"];

                    $tipoConta = $conta["tipo"];

                    $nomeTipo = $tiposNomes[$tipoConta] ?? "Outros";

                    $iconeConta = $tiposIcones[$tipoConta] ?? "💼";

                    $saldoAtual = jaboo_formatar_moeda_contas(
                        $conta["saldo_atual"]
                    );

                    $saldoInicial = jaboo_formatar_moeda_contas(
                        $conta["saldo_inicial"]
                    );

                    $corConta = preg_match(
                        '/^#[0-9A-Fa-f]{6}$/',
                        $conta["cor"]
                    ) ? $conta["cor"] : "#2E7D32";

                    ?>


                    <article class="conta-item <?= !$conta["ativo"] ? "conta-inativa" : "" ?>">

                        <div class="conta-item-main">

                            <div
                                class="conta-item-icon"
                                style="--conta-cor: <?= htmlspecialchars($corConta, ENT_QUOTES, "UTF-8") ?>"
                            >

                                <?= $iconeConta ?>

                            </div>


                            <div class="conta-item-info">

                                <div class="conta-item-name">

                                    <strong>
                                        <?= htmlspecialchars($conta["nome"], ENT_QUOTES, "UTF-8") ?>
                                    </strong>

                                    <?php if (!$conta["ativo"]): ?>

                                        <span class="conta-status-inativo">
                                            Desativada
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <span>

                                    <?= htmlspecialchars($nomeTipo, ENT_QUOTES, "UTF-8") ?>

                                    <?php if (!empty($conta["instituicao"])): ?>

                                        · <?= htmlspecialchars($conta["instituicao"], ENT_QUOTES, "UTF-8") ?>

                                    <?php endif; ?>

                                </span>


                                <small>
                                    <?= $conta["incluir_no_patrimonio"]
                                        ? "Incluída no patrimônio"
                                        : "Fora do patrimônio"
                                    ?>
                                </small>

                            </div>

                        </div>


                        <!-- SALDO ATUAL -->

                        <div class="conta-item-balance">

                            <span>
                                Saldo atual · Inicial: R$ <?= $saldoInicial ?>
                            </span>

                            <strong>
                                R$ <?= $saldoAtual ?>
                            </strong>

                        </div>


                        <!-- AÇÕES -->

                        <div class="conta-item-actions">

                            <a
                                href="index.php?pagina=contas&editar=<?= $idConta ?>#formulario-conta"
                                class="conta-edit-button"
                            >
                                Editar
                            </a>


                            <form
                                method="POST"
                                action="index.php?pagina=contas"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(jaboo_csrf_token(), ENT_QUOTES, "UTF-8") ?>"
                                >

                                <input
                                    type="hidden"
                                    name="acao"
                                    value="alterar_status"
                                >

                                <input
                                    type="hidden"
                                    name="conta_id"
                                    value="<?= $idConta ?>"
                                >


                                <?php if ($conta["ativo"]): ?>

                                    <input
                                        type="hidden"
                                        name="novo_status"
                                        value="0"
                                    >

                                    <button type="submit" class="conta-status-button">
                                        Desativar
                                    </button>

                                <?php else: ?>

                                    <input
                                        type="hidden"
                                        name="novo_status"
                                        value="1"
                                    >

                                    <button type="submit" class="conta-activate-button">
                                        Reativar
                                    </button>

                                <?php endif; ?>

                            </form>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>


        <?php else: ?>

            <div class="contas-empty">

                <div class="contas-empty-icon">
                    ▣
                </div>

                <h4>Nenhuma conta cadastrada</h4>

                <p>
                    Comece adicionando sua primeira conta financeira.
                </p>

            </div>

        <?php endif; ?>

    </section>

</div>


<!-- ==========================================
     FORMATAÇÃO AUTOMÁTICA DO SALDO
========================================== -->

<script>

(function () {

    const campo = document.getElementById("saldo_inicial");

    if (!campo) {
        return;
    }

    const LIMITE_DIGITOS = 15;

    function formatarSaldo() {

        let digitos = campo.value.replace(/\D/g, "");

        digitos = digitos.replace(/^0+/, "");

        if (digitos === "") {

            campo.value = "";

            campo.setCustomValidity("");

            return;

        }

        if (digitos.length > LIMITE_DIGITOS) {

            campo.setCustomValidity(
                "O saldo ultrapassa o limite permitido pelo banco de dados."
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

    campo.addEventListener("input", formatarSaldo);

    campo.addEventListener("blur", function () {

        if (campo.value.trim() === "") {

            campo.value = "0,00";

            campo.setCustomValidity("");

        }

    });

})();

</script>
