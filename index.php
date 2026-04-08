<?php
/**
 * Sistema de Rateio de Pescaria - Versão PHP
 * Banco de Dados: C:\Temp\pescaria\pescaria.db
 */

$dbPath = 'C:\Temp\pescaria\pescaria.db';

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Criar tabela se não existir
    $pdo->exec("CREATE TABLE IF NOT EXISTS despesas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        descricao TEXT NOT NULL,
        pagador TEXT NOT NULL,
        valor REAL NOT NULL,
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

// Lógica de Cadastro
if (isset($_POST['cadastrar'])) {
    $desc = $_POST['descricao'];
    $pagador = $_POST['pagador'];
    $valor = str_replace(',', '.', $_POST['valor']);

    if ($desc && $pagador && is_numeric($valor)) {
        $stmt = $pdo->prepare("INSERT INTO despesas (descricao, pagador, valor) VALUES (?, ?, ?)");
        $stmt->execute([$desc, $pagador, $valor]);
        header("Location: index.php?msg=cadastrado");
        exit;
    }
}

// Lógica de Edição
if (isset($_POST['editar'])) {
    $id = $_POST['id'];
    $desc = $_POST['descricao'];
    $pagador = $_POST['pagador'];
    $valor = str_replace(',', '.', $_POST['valor']);

    if ($id && $desc && $pagador && is_numeric($valor)) {
        $stmt = $pdo->prepare("UPDATE despesas SET descricao = ?, pagador = ?, valor = ? WHERE id = ?");
        $stmt->execute([$desc, $pagador, $valor, $id]);
        header("Location: index.php?msg=editado");
        exit;
    }
}

// Lógica de Exclusão
if (isset($_GET['excluir'])) {
    $stmt = $pdo->prepare("DELETE FROM despesas WHERE id = ?");
    $stmt->execute([$_GET['excluir']]);
    header("Location: index.php");
    exit;
}

// Busca dados para o relatório
$stmt = $pdo->query("SELECT * FROM despesas ORDER BY id");
$despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pagadores = [];
$descricoes = [];
$matriz = [];
$totais_pagador = [];
$total_geral = 0;

foreach ($despesas as $d) {
    $pagadores[$d['pagador']] = true;
    $descricoes[$d['descricao']] = true;
    $matriz[$d['descricao']][$d['pagador']] = ($matriz[$d['descricao']][$d['pagador']] ?? 0) + $d['valor'];
    $totais_pagador[$d['pagador']] = ($totais_pagador[$d['pagador']] ?? 0) + $d['valor'];
    $total_geral += $d['valor'];
}

ksort($pagadores);
ksort($descricoes);
$num_pescadores = count($pagadores);
$valor_por_pescador = $num_pescadores > 0 ? $total_geral / $num_pescadores : 0;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Rateio Pescaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f4f4f9; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; background: white; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #0076D7; color: white; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .acerto-item { padding: 5px 0; border-bottom: 1px solid #eee; }
        /* Estilos do Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 10% auto; padding: 20px; border-radius: 8px; width: 400px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .modal-header { border-bottom: 1px solid #eee; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { margin: 0; font-size: 1.2rem; }
        .close { font-size: 24px; cursor: pointer; border: none; background: none; }
    </style>
</head>
<body>

    <h1>🎣 Sistema de Rateio de Pescaria</h1>

    <div class="card">
        <h2>Nova Despesa</h2>
        <form method="POST">
            <input type="text" name="descricao" placeholder="Descrição (ex: Gelo)" required>
            <input type="text" name="pagador" placeholder="Quem pagou?" required>
            <input type="text" name="valor" placeholder="Valor (R$)" required>
            <button type="submit" name="cadastrar" class="btn btn-success">Cadastrar</button>
        </form>
    </div>

    <div class="card">
        <h2>Lista de Despesas</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Descrição</th>
                    <th>Pagador</th>
                    <th>Valor</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($despesas as $d): ?>
                <tr>
                    <td><?= $d['id'] ?></td>
                    <td><?= htmlspecialchars($d['descricao']) ?></td>
                    <td><?= htmlspecialchars($d['pagador']) ?></td>
                    <td>R$ <?= number_format($d['valor'], 2, ',', '.') ?></td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick='abrirModalEditar(<?= json_encode($d) ?>)'>Editar</button>
                        <a href="?excluir=<?= $d['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Excluir?')">Excluir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($num_pescadores > 0): ?>
    <div class="card">
        <h2>Relatório Consolidado</h2>
        <table>
            <thead>
                <tr>
                    <th>Despesa</th>
                    <?php foreach (array_keys($pagadores) as $p): ?>
                        <th><?= htmlspecialchars($p) ?></th>
                    <?php endforeach; ?>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_keys($descricoes) as $desc): ?>
                <tr>
                    <td><?= htmlspecialchars($desc) ?></td>
                    <?php 
                    $total_linha = 0;
                    foreach (array_keys($pagadores) as $p): 
                        $v = $matriz[$desc][$p] ?? 0;
                        $total_linha += $v;
                    ?>
                        <td><?= $v > 0 ? 'R$ '.number_format($v, 2, ',', '.') : '-' ?></td>
                    <?php endforeach; ?>
                    <strong><td>R$ <?= number_format($total_linha, 2, ',', '.') ?></td></strong>
                </tr>
                <?php endforeach; ?>
                <tr style="background: #eee; font-weight: bold;">
                    <td>TOTAL PAGO</td>
                    <?php foreach (array_keys($pagadores) as $p): ?>
                        <td>R$ <?= number_format($totais_pagador[$p], 2, ',', '.') ?></td>
                    <?php endforeach; ?>
                    <td>R$ <?= number_format($total_geral, 2, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>
        <p><strong>Rateio por Pescador (<?= $num_pescadores ?>): R$ <?= number_format($valor_por_pescador, 2, ',', '.') ?></strong></p>
    </div>

    <div class="card">
        <h2>Acertos Sugeridos</h2>
        <?php
        $saldos = [];
        foreach ($pagadores as $p => $val) {
            $saldos[$p] = ($totais_pagador[$p] ?? 0) - $valor_por_pescador;
        }

        $devedores = [];
        $credores = [];
        foreach ($saldos as $nome => $saldo) {
            if ($saldo < -0.01) $devedores[$nome] = abs($saldo);
            if ($saldo > 0.01) $credores[$nome] = $saldo;
        }

        arsort($devedores);
        arsort($credores);

        foreach ($saldos as $nome => $saldo) {
            echo "<div class='acerto-item'>";
            if (abs($saldo) < 0.01) {
                echo "✅ $nome está quite.";
            } elseif ($saldo > 0) {
                echo "💰 $nome deve receber R$ " . number_format($saldo, 2, ',', '.');
            } else {
                echo "💸 $nome deve pagar R$ " . number_format(abs($saldo), 2, ',', '.');
            }
            echo "</div>";
        }

        if ($devedores && $credores) {
            echo "<h3>Transferências:</h3>";
            reset($credores);
            foreach ($devedores as $dev_nome => $dev_val) {
                while ($dev_val > 0.01) {
                    $cred_nome = key($credores);
                    $cred_val = current($credores);

                    if (!$cred_nome) break;

                    $transferir = min($dev_val, $cred_val);
                    echo "<li>$dev_nome paga <strong>R$ " . number_format($transferir, 2, ',', '.') . "</strong> para $cred_nome</li>";
                    
                    $dev_val -= $transferir;
                    $credores[$cred_nome] -= $transferir;

                    if ($credores[$cred_nome] < 0.01) next($credores);
                }
            }
        }
        ?>
    </div>
    <?php endif; ?>

    <!-- Modal de Edição -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Despesa</h2>
                <button class="close" onclick="fecharModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="id" id="edit-id">
                <p><input type="text" name="descricao" id="edit-descricao" placeholder="Descrição" required style="width: 95%; padding: 8px;"></p>
                <p><input type="text" name="pagador" id="edit-pagador" placeholder="Quem pagou?" required style="width: 95%; padding: 8px;"></p>
                <p><input type="text" name="valor" id="edit-valor" placeholder="Valor (R$)" required style="width: 95%; padding: 8px;"></p>
                <button type="submit" name="editar" class="btn btn-primary">Salvar Alterações</button>
            </form>
        </div>
    </div>

    <script>
        function abrirModalEditar(despesa) {
            document.getElementById('edit-id').value = despesa.id;
            document.getElementById('edit-descricao').value = despesa.descricao;
            document.getElementById('edit-pagador').value = despesa.pagador;
            document.getElementById('edit-valor').value = despesa.valor.toString().replace('.', ',');
            document.getElementById('modalEditar').style.display = 'block';
        }

        function fecharModal() {
            document.getElementById('modalEditar').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('modalEditar')) {
                fecharModal();
            }
        }
    </script>

</body>
</html>