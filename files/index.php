<?php
// ============================================
// index.php - Sistema de Conversión USD a GTQ
// ============================================
require_once 'conexion.php';

$conn = conectar();
$mensaje = '';
$resultado_conversion = null;

// Obtener tasa actual
$tasa_row = $conn->query("SELECT tasa FROM tipo_cambio ORDER BY id DESC LIMIT 1")->fetch_assoc();
$tasa_actual = $tasa_row['tasa'];

// Procesar conversión
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto_usd    = floatval($_POST['monto_usd']);
    $tipo         = in_array($_POST['tipo'], ['fisica','consulta']) ? $_POST['tipo'] : 'consulta';
    $nombre       = trim($_POST['nombre_cliente']);

    if ($monto_usd <= 0) {
        $mensaje = ['tipo' => 'error', 'texto' => 'Ingresa un monto válido mayor a 0.'];
    } else {
        $monto_gtq = round($monto_usd * $tasa_actual, 2);
        $stmt = $conn->prepare("INSERT INTO conversiones (monto_usd, monto_gtq, tasa_usada, tipo, nombre_cliente) VALUES (?,?,?,?,?)");
        $stmt->bind_param("dddss", $monto_usd, $monto_gtq, $tasa_actual, $tipo, $nombre);
        $stmt->execute();
        $stmt->close();

        $resultado_conversion = [
            'usd' => $monto_usd,
            'gtq' => $monto_gtq,
            'tasa' => $tasa_actual,
            'tipo' => $tipo
        ];
        $mensaje = ['tipo' => 'exito', 'texto' => 'Conversión registrada correctamente.'];
    }
}

// Datos para reportes
$por_hora  = $conn->query("SELECT * FROM v_conversiones_por_hora");
$por_dia   = $conn->query("SELECT * FROM v_total_por_dia LIMIT 30");
$historial = $conn->query("SELECT * FROM conversiones ORDER BY fecha_hora DESC LIMIT 50");
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Conversión USD → GTQ | Banco</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: #f0f2f5; color: #333; }

  header {
    background: #1a3c6e;
    color: white;
    padding: 18px 30px;
    display: flex;
    align-items: center;
    gap: 15px;
  }
  header h1 { font-size: 1.4rem; }
  header span { font-size: 0.9rem; opacity: 0.8; }

  .tasa-banner {
    background: #e8f4fd;
    border-left: 4px solid #1a3c6e;
    padding: 10px 30px;
    font-size: 0.95rem;
  }
  .tasa-banner strong { color: #1a3c6e; font-size: 1.1rem; }

  main { max-width: 1100px; margin: 25px auto; padding: 0 20px; }

  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }

  .card {
    background: white;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  }
  .card h2 { color: #1a3c6e; margin-bottom: 18px; font-size: 1.1rem; border-bottom: 2px solid #e0e7ef; padding-bottom: 8px; }

  label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9rem; }
  input[type=number], input[type=text], select {
    width: 100%; padding: 10px; border: 1px solid #ccc;
    border-radius: 5px; font-size: 1rem; margin-bottom: 15px;
  }
  input:focus, select:focus { outline: none; border-color: #1a3c6e; }

  .radio-group { display: flex; gap: 20px; margin-bottom: 15px; }
  .radio-group label { display: flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer; }

  button[type=submit] {
    width: 100%; background: #1a3c6e; color: white;
    padding: 12px; border: none; border-radius: 5px;
    font-size: 1rem; cursor: pointer; font-weight: bold;
  }
  button[type=submit]:hover { background: #245fa0; }

  .resultado {
    background: #f0f9f0; border: 1px solid #5cb85c;
    border-radius: 8px; padding: 20px; margin-top: 20px; text-align: center;
  }
  .resultado .monto { font-size: 2rem; font-weight: bold; color: #2e7d32; }
  .resultado .detalle { color: #555; font-size: 0.9rem; margin-top: 6px; }

  .alerta { padding: 10px 15px; border-radius: 5px; margin-bottom: 15px; font-size: 0.9rem; }
  .alerta.error { background: #fdecea; color: #c62828; border: 1px solid #ef9a9a; }
  .alerta.exito { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }

  table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
  th { background: #1a3c6e; color: white; padding: 8px 10px; text-align: left; }
  td { padding: 7px 10px; border-bottom: 1px solid #eee; }
  tr:hover td { background: #f5f8ff; }
  .badge {
    display: inline-block; padding: 2px 8px; border-radius: 12px;
    font-size: 0.8rem; font-weight: bold;
  }
  .badge.fisica  { background: #d4edda; color: #155724; }
  .badge.consulta { background: #d1ecf1; color: #0c5460; }

  .full-width { grid-column: 1 / -1; }

  .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
  .stat { background: #f0f4fb; border-radius: 6px; padding: 14px; text-align: center; }
  .stat .num { font-size: 1.5rem; font-weight: bold; color: #1a3c6e; }
  .stat .lbl { font-size: 0.8rem; color: #666; margin-top: 3px; }

  @media(max-width:700px){ .grid{grid-template-columns:1fr;} .stat-grid{grid-template-columns:1fr 1fr;} }
</style>
</head>
<body>

<header>
  <div>
    <h1>💱 Sistema de Conversión de Moneda</h1>
    <span>Dólar Estadounidense → Quetzal Guatemalteco</span>
  </div>
</header>

<div class="tasa-banner">
  Tasa de cambio actual: <strong>Q <?= number_format($tasa_actual, 4) ?></strong> por cada USD $1.00
</div>

<main>
  <div class="grid">

    <!-- FORMULARIO -->
    <div class="card">
      <h2>Nueva Conversión</h2>
      <?php if ($mensaje): ?>
        <div class="alerta <?= $mensaje['tipo'] ?>"><?= htmlspecialchars($mensaje['texto']) ?></div>
      <?php endif; ?>
      <form method="POST" action="">
        <label>Nombre del cliente (opcional)</label>
        <input type="text" name="nombre_cliente" placeholder="Ej. Juan Pérez" maxlength="100">

        <label>Monto en dólares (USD $)</label>
        <input type="number" name="monto_usd" step="0.01" min="0.01" placeholder="0.00" required>

        <label>Tipo de operación</label>
        <div class="radio-group">
          <label><input type="radio" name="tipo" value="consulta" checked> Solo consulta</label>
          <label><input type="radio" name="tipo" value="fisica"> Cambio físico</label>
        </div>

        <button type="submit">Realizar Conversión</button>
      </form>

      <?php if ($resultado_conversion): ?>
      <div class="resultado">
        <div class="detalle">USD $ <?= number_format($resultado_conversion['usd'], 2) ?> equivale a:</div>
        <div class="monto">Q <?= number_format($resultado_conversion['gtq'], 2) ?></div>
        <div class="detalle">Tasa: Q<?= $resultado_conversion['tasa'] ?> × $<?= number_format($resultado_conversion['usd'],2) ?></div>
        <div class="detalle" style="margin-top:8px">
          Tipo: <span class="badge <?= $resultado_conversion['tipo'] ?>"><?= ucfirst($resultado_conversion['tipo']) ?></span>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- REPORTE POR HORA (HOY) -->
    <div class="card">
      <h2>📊 Conversiones por Hora — Hoy</h2>
      <?php if ($por_hora->num_rows === 0): ?>
        <p style="color:#888; text-align:center; padding:20px;">Sin conversiones registradas hoy.</p>
      <?php else: ?>
      <table>
        <tr><th>Hora</th><th>Tipo</th><th>Ops</th><th>Total USD</th><th>Total GTQ</th></tr>
        <?php while($r = $por_hora->fetch_assoc()): ?>
        <tr>
          <td><?= str_pad($r['hora'],2,'0',STR_PAD_LEFT) ?>:00</td>
          <td><span class="badge <?= $r['tipo'] ?>"><?= ucfirst($r['tipo']) ?></span></td>
          <td><?= $r['total_operaciones'] ?></td>
          <td>$ <?= number_format($r['total_usd'], 2) ?></td>
          <td>Q <?= number_format($r['total_gtq'], 2) ?></td>
        </tr>
        <?php endwhile; ?>
      </table>
      <?php endif; ?>
    </div>

    <!-- TOTAL POR DÍA -->
    <div class="card full-width">
      <h2>📅 Total de Conversiones por Día</h2>
      <?php if ($por_dia->num_rows === 0): ?>
        <p style="color:#888; text-align:center; padding:20px;">Sin historial disponible.</p>
      <?php else: ?>
      <table>
        <tr><th>Fecha</th><th>Total Ops</th><th>Cambios Físicos</th><th>Solo Consultas</th><th>Total USD</th><th>Total GTQ</th></tr>
        <?php while($r = $por_dia->fetch_assoc()): ?>
        <tr>
          <td><?= $r['dia'] ?></td>
          <td><?= $r['total_operaciones'] ?></td>
          <td><span class="badge fisica"><?= $r['cambios_fisicos'] ?></span></td>
          <td><span class="badge consulta"><?= $r['solo_consultas'] ?></span></td>
          <td>$ <?= number_format($r['total_usd'], 2) ?></td>
          <td>Q <?= number_format($r['total_gtq'], 2) ?></td>
        </tr>
        <?php endwhile; ?>
      </table>
      <?php endif; ?>
    </div>

    <!-- HISTORIAL -->
    <div class="card full-width">
      <h2>📋 Historial de Conversiones (últimas 50)</h2>
      <?php if ($historial->num_rows === 0): ?>
        <p style="color:#888; text-align:center; padding:20px;">Sin registros aún.</p>
      <?php else: ?>
      <table>
        <tr><th>Fecha y Hora</th><th>Cliente</th><th>Tipo</th><th>USD</th><th>GTQ</th><th>Tasa</th></tr>
        <?php while($r = $historial->fetch_assoc()): ?>
        <tr>
          <td><?= $r['fecha_hora'] ?></td>
          <td><?= $r['nombre_cliente'] ? htmlspecialchars($r['nombre_cliente']) : '<em style="color:#aaa">—</em>' ?></td>
          <td><span class="badge <?= $r['tipo'] ?>"><?= ucfirst($r['tipo']) ?></span></td>
          <td>$ <?= number_format($r['monto_usd'], 2) ?></td>
          <td>Q <?= number_format($r['monto_gtq'], 2) ?></td>
          <td><?= $r['tasa_usada'] ?></td>
        </tr>
        <?php endwhile; ?>
      </table>
      <?php endif; ?>
    </div>

  </div><!-- /grid -->
</main>

</body>
</html>
