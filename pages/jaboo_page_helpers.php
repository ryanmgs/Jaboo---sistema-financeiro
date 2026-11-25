<?php
/** Funções compartilhadas pelas novas telas do JABOO. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

if (function_exists('jaboo_exigir_login')) {
    jaboo_exigir_login();
}

if (!isset($conexao) || !($conexao instanceof mysqli)) {
    if (isset($GLOBALS['conexao']) && $GLOBALS['conexao'] instanceof mysqli) {
        $conexao = $GLOBALS['conexao'];
    } else {
        throw new RuntimeException('A conexão MySQLi não foi encontrada em includes/database.php.');
    }
}

if (!function_exists('jaboo_tela_escape')) {
    function jaboo_tela_escape($valor): string {
        if (function_exists('jaboo_escape')) {
            return (string) jaboo_escape((string) ($valor ?? ''));
        }
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('jaboo_tela_usuario_id')) {
    function jaboo_tela_usuario_id(): int {
        $chaves = ['usuario_id', 'user_id', 'id_usuario'];
        foreach ($chaves as $chave) {
            if (isset($_SESSION[$chave]) && is_numeric($_SESSION[$chave])) {
                return (int) $_SESSION[$chave];
            }
        }

        foreach (['usuario', 'user', 'usuario_logado'] as $chave) {
            $usuario = $_SESSION[$chave] ?? null;
            if (is_array($usuario)) {
                foreach (['id', 'usuario_id'] as $campo) {
                    if (isset($usuario[$campo]) && is_numeric($usuario[$campo])) return (int) $usuario[$campo];
                }
            } elseif (is_object($usuario)) {
                foreach (['id', 'usuario_id'] as $campo) {
                    if (isset($usuario->$campo) && is_numeric($usuario->$campo)) return (int) $usuario->$campo;
                }
            }
        }

        if (function_exists('jaboo_usuario_logado')) {
            $usuario = jaboo_usuario_logado();
            if (is_numeric($usuario)) return (int) $usuario;
            if (is_array($usuario)) {
                return (int) ($usuario['id'] ?? $usuario['usuario_id'] ?? 0);
            }
            if (is_object($usuario)) {
                return (int) ($usuario->id ?? $usuario->usuario_id ?? 0);
            }
        }
        return 0;
    }
}

$jabooUsuarioId = jaboo_tela_usuario_id();
if ($jabooUsuarioId <= 0) {
    if (!headers_sent()) header('Location: login.php');
    exit;
}

if (!function_exists('jaboo_tela_csrf_token')) {
    function jaboo_tela_csrf_token(): string {
        if (function_exists('jaboo_csrf_token')) {
            return (string) jaboo_csrf_token();
        }
        if (empty($_SESSION['jaboo_csrf_token'])) {
            $_SESSION['jaboo_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['jaboo_csrf_token'];
    }
}

if (!function_exists('jaboo_tela_validar_csrf')) {
    function jaboo_tela_validar_csrf(): void {
        if (function_exists('jaboo_validar_csrf')) {
            jaboo_validar_csrf();
            return;
        }
        $recebido = (string) ($_POST['csrf_token'] ?? '');
        $esperado = (string) ($_SESSION['jaboo_csrf_token'] ?? '');
        if ($esperado === '' || !hash_equals($esperado, $recebido)) {
            throw new RuntimeException('Sua sessão expirou. Atualize a página e tente novamente.');
        }
    }
}

if (!function_exists('jaboo_tela_csrf_input')) {
    function jaboo_tela_csrf_input(): string {
        return '<input type="hidden" name="csrf_token" value="' . jaboo_tela_escape(jaboo_tela_csrf_token()) . '">';
    }
}

if (!function_exists('jaboo_tela_moeda')) {
    function jaboo_tela_moeda($valor): string {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }
}

if (!function_exists('jaboo_tela_decimal')) {
    function jaboo_tela_decimal($valor): float {
        if (is_int($valor) || is_float($valor)) return (float) $valor;
        $valor = trim((string) $valor);
        $valor = str_replace(['R$', "\xc2\xa0", ' '], '', $valor);
        if (str_contains($valor, ',')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }
        return is_numeric($valor) ? (float) $valor : NAN;
    }
}

if (!function_exists('jaboo_tela_decimal_sql')) {
    function jaboo_tela_decimal_sql(float $valor, int $casas = 2): string {
        return number_format($valor, $casas, '.', '');
    }
}

if (!function_exists('jaboo_tela_data_br')) {
    function jaboo_tela_data_br(?string $data): string {
        if (!$data || $data === '0000-00-00') return '—';
        $dt = DateTime::createFromFormat('Y-m-d', $data);
        return $dt ? $dt->format('d/m/Y') : '—';
    }
}

if (!function_exists('jaboo_tela_flash_definir')) {
    function jaboo_tela_flash_definir(string $tipo, string $mensagem): void {
        $_SESSION['jaboo_flash_tela'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    }
}

if (!function_exists('jaboo_tela_flash_exibir')) {
    function jaboo_tela_flash_exibir(): void {
        $flash = $_SESSION['jaboo_flash_tela'] ?? null;
        unset($_SESSION['jaboo_flash_tela']);
        if (!$flash || empty($flash['mensagem'])) return;
        $classe = ($flash['tipo'] ?? '') === 'sucesso' ? 'sucesso' : 'erro';
        echo '<div class="jaboo-alerta ' . $classe . '">' . jaboo_tela_escape($flash['mensagem']) . '</div>';
    }
}

if (!function_exists('jaboo_tela_redirecionar')) {
    function jaboo_tela_redirecionar(string $pagina, string $query = ''): void {
        $destino = 'index.php?pagina=' . rawurlencode($pagina);
        if ($query !== '') $destino .= '&' . ltrim($query, '&');
        header('Location: ' . $destino);
        exit;
    }
}

if (!function_exists('jaboo_tela_db')) {
    function jaboo_tela_db(): mysqli {
        global $conexao;
        if (!($conexao instanceof mysqli)) throw new RuntimeException('Conexão com o banco indisponível.');
        return $conexao;
    }
}

if (!function_exists('jaboo_tela_tipo_investimento')) {
    function jaboo_tela_tipo_investimento(string $tipo): string {
        $mapa = [
            'renda_fixa' => 'Renda fixa', 'acao' => 'Ação', 'fii' => 'FII',
            'fundo' => 'Fundo', 'cripto' => 'Criptoativo', 'tesouro' => 'Tesouro', 'outro' => 'Outro'
        ];
        return $mapa[$tipo] ?? ucfirst($tipo);
    }
}

if (!function_exists('jaboo_tela_tipo_movimento')) {
    function jaboo_tela_tipo_movimento(string $tipo): string {
        $mapa = ['aporte' => 'Aporte', 'resgate' => 'Resgate', 'dividendo' => 'Dividendo', 'juros' => 'Juros', 'taxa' => 'Taxa', 'outro' => 'Outro'];
        return $mapa[$tipo] ?? ucfirst($tipo);
    }
}
