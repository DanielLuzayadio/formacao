<?php
// ============================================================
//  api.php — Sistema de Formação Luzayadio
//  Prefixo: mnr_
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ------------------------------------------------------------
//  CONFIG BD — editar estas 4 linhas
// ------------------------------------------------------------
define('DB_HOST', 'mysql.railway.internal');
define('DB_USER', 'root');
define('DB_PASS', 'kRiumtfUITaSOWzQarxAGSLVmsPtHoDZ');
define('DB_NAME', 'railway');
define('PREFIX',  'mnr_');

// ------------------------------------------------------------
//  LIGAÇÃO mysqli
// ------------------------------------------------------------
function db() {
  static $c = null;
  if ($c) return $c;
  $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($c->connect_error) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'erro'=>'DB: '.$c->connect_error]);
    exit;
  }
  $c->set_charset('utf8mb4');
  return $c;
}

function q($sql) {
  $r = db()->query($sql);
  if ($r === false) { err('Query error: '.db()->error); }
  return $r;
}

function rows($sql) {
  $r = q($sql);
  $out = [];
  while ($row = $r->fetch_assoc()) $out[] = $row;
  return $out;
}

function row($sql) {
  return q($sql)->fetch_assoc();
}

function run($sql, $types, $params) {
  $s = db()->prepare($sql);
  if (!$s) { err('Prepare error: '.db()->error); }
  $s->bind_param($types, ...$params);
  $s->execute();
  return $s;
}

function ok($data = null)  { echo json_encode(['ok'=>true,'data'=>$data]); exit; }
function err($msg, $c=400) { http_response_code($c); echo json_encode(['ok'=>false,'erro'=>$msg]); exit; }
function body()            { return json_decode(file_get_contents('php://input'), true) ?? []; }
function esc($v)           { return db()->real_escape_string((string)$v); }

$action = $_GET['action'] ?? '';

// ============================================================
//  AUTH
// ============================================================
if ($action === 'login') {
  $b    = body();
  $user = trim($b['user'] ?? '');
  $pass = trim($b['pass'] ?? '');
  if (!$user || !$pass) err('Dados incompletos');

  $sr = row('SELECT * FROM '.PREFIX.'senhas_rotativas LIMIT 1');
  $senhaRotativaOk = false;
  if ($sr && $sr['dataAtivacao']) {
    $senhas = json_decode($sr['senhas'] ?? '[]', true);
    $inicio = new DateTime($sr['dataAtivacao']);
    $agora  = new DateTime();
    $diff   = $inicio->diff($agora);
    $meses  = $diff->y * 12 + $diff->m + 1;
    $idx    = $meses - 1;
    if ($idx >= 0 && isset($senhas[$idx]) && $senhas[$idx] === $pass) $senhaRotativaOk = true;
    if ($meses > $sr['totalMeses']) err('Sistema desactivado. Contacte a Luzayadio.', 403);
  }

  $s = db()->prepare('SELECT * FROM '.PREFIX.'utilizadores WHERE nome=? AND senha=? LIMIT 1');
  $s->bind_param('ss', $user, $pass);
  $s->execute();
  $u = $s->get_result()->fetch_assoc();

  if (!$u && $senhaRotativaOk) $u = ['id'=>0,'nome'=>$user,'senha'=>'','perfil'=>'admin'];
  if (!$u) err('Utilizador ou senha incorrectos', 401);

  ok(['utilizador'=>$u['nome'],'perfil'=>$u['perfil']]);
}

// ============================================================
//  CONFIG
// ============================================================
if ($action === 'config_get') {
  $rows = rows('SELECT chave, valor FROM '.PREFIX.'config');
  $cfg  = [];
  foreach ($rows as $r) $cfg[$r['chave']] = $r['valor'];
  ok($cfg);
}

if ($action === 'config_set') {
  $b   = body();
  $map = ['nome','icone','morada','activado'];
  foreach ($map as $k) {
    if (isset($b[$k])) {
      $v = esc($b[$k]);
      q("INSERT INTO ".PREFIX."config (chave,valor) VALUES ('$k','$v') ON DUPLICATE KEY UPDATE valor='$v'");
    }
  }
  ok();
}

// ============================================================
//  UTILIZADORES
// ============================================================
if ($action === 'utils_list') {
  ok(rows('SELECT id,nome,perfil FROM '.PREFIX.'utilizadores ORDER BY id'));
}

if ($action === 'utils_save') {
  $b      = body();
  $id     = intval($b['id'] ?? 0);
  $nome   = trim($b['nome']  ?? '');
  $senha  = trim($b['senha'] ?? '');
  $perfil = in_array($b['perfil']??'',['admin','recepcao']) ? $b['perfil'] : 'recepcao';
  if (!$nome) err('Nome obrigatório');

  if ($id > 0) {
    if ($senha) {
      run('UPDATE '.PREFIX.'utilizadores SET nome=?,senha=?,perfil=? WHERE id=?', 'sssi', [$nome,$senha,$perfil,$id]);
    } else {
      run('UPDATE '.PREFIX.'utilizadores SET nome=?,perfil=? WHERE id=?', 'ssi', [$nome,$perfil,$id]);
    }
    ok(['id'=>$id]);
  } else {
    if (!$senha) err('Senha obrigatória');
    run('INSERT INTO '.PREFIX.'utilizadores (nome,senha,perfil) VALUES (?,?,?)', 'sss', [$nome,$senha,$perfil]);
    ok(['id'=>db()->insert_id]);
  }
}

if ($action === 'utils_delete') {
  $id = intval(body()['id'] ?? 0);
  if (!$id) err('ID inválido');
  run('DELETE FROM '.PREFIX.'utilizadores WHERE id=?', 'i', [$id]);
  ok();
}

// ============================================================
//  CURSOS
// ============================================================
if ($action === 'cursos_list') {
  ok(rows('SELECT * FROM '.PREFIX.'cursos ORDER BY id'));
}

if ($action === 'cursos_save') {
  $b    = body();
  $id   = intval($b['id'] ?? 0);
  $nome = trim($b['nome'] ?? '');
  if (!$nome) err('Nome obrigatório');
  $dur  = $b['duracao'] ?? '';
  $val  = floatval($b['valor'] ?? 0);
  $desc = $b['desc'] ?? '';

  if ($id > 0) {
    run('UPDATE '.PREFIX.'cursos SET nome=?,duracao=?,valor=?,`desc`=? WHERE id=?', 'ssdsi', [$nome,$dur,$val,$desc,$id]);
    ok(['id'=>$id]);
  } else {
    run('INSERT INTO '.PREFIX.'cursos (nome,duracao,valor,`desc`) VALUES (?,?,?,?)', 'ssds', [$nome,$dur,$val,$desc]);
    ok(['id'=>db()->insert_id]);
  }
}

if ($action === 'cursos_delete') {
  $id = intval(body()['id'] ?? 0);
  if (!$id) err('ID inválido');
  run('DELETE FROM '.PREFIX.'cursos WHERE id=?', 'i', [$id]);
  ok();
}

// ============================================================
//  TURMAS
// ============================================================
if ($action === 'turmas_list') {
  ok(rows('SELECT * FROM '.PREFIX.'turmas ORDER BY id'));
}

if ($action === 'turmas_save') {
  $b        = body();
  $id       = intval($b['id'] ?? 0);
  $nome     = trim($b['nome'] ?? '');
  if (!$nome) err('Nome obrigatório');
  $cursoId  = intval($b['cursoId'] ?? 0);
  $horario  = $b['horario'] ?? '';
  $vagas    = intval($b['vagas'] ?? 20);
  $inicio   = $b['inicio'] ?: null;
  $fim      = $b['fim'] ?: null;
  $formador = $b['formador'] ?? '';
  $estado   = in_array($b['estado']??'',['Em curso','Agendada','Concluída']) ? $b['estado'] : 'Agendada';

  if ($id > 0) {
    run('UPDATE '.PREFIX.'turmas SET nome=?,cursoId=?,horario=?,vagas=?,inicio=?,fim=?,formador=?,estado=? WHERE id=?',
        'sisiissi', [$nome,$cursoId,$horario,$vagas,$inicio,$fim,$formador,$estado,$id]);
    ok(['id'=>$id]);
  } else {
    run('INSERT INTO '.PREFIX.'turmas (nome,cursoId,horario,vagas,inicio,fim,formador,estado) VALUES (?,?,?,?,?,?,?,?)',
        'sisiisss', [$nome,$cursoId,$horario,$vagas,$inicio,$fim,$formador,$estado]);
    ok(['id'=>db()->insert_id]);
  }
}

if ($action === 'turmas_delete') {
  $id = intval(body()['id'] ?? 0);
  if (!$id) err('ID inválido');
  run('DELETE FROM '.PREFIX.'turmas WHERE id=?', 'i', [$id]);
  ok();
}

// ============================================================
//  ALUNOS
// ============================================================
if ($action === 'alunos_list') {
  ok(rows('SELECT * FROM '.PREFIX.'alunos ORDER BY nome'));
}

if ($action === 'alunos_save') {
  $b       = body();
  $id      = intval($b['id'] ?? 0);
  $nome    = trim($b['nome'] ?? '');
  if (!$nome) err('Nome obrigatório');
  $tel     = $b['tel'] ?? '';
  $nasc    = $b['nasc'] ?: null;
  $bi      = $b['bi'] ?? '';
  $sexo    = in_array($b['sexo']??'',['M','F']) ? $b['sexo'] : 'M';
  $cursoId = intval($b['cursoId'] ?? 0) ?: null;
  $turmaId = intval($b['turmaId'] ?? 0) ?: null;
  $propina = floatval($b['propina'] ?? 0);
  $insc    = $b['inscricao'] ?: null;
  $estado  = in_array($b['estado']??'',['Activo','Concluído','Desistiu']) ? $b['estado'] : 'Activo';
  $tnum    = $b['tshirt_num'] ?? '';
  $ttam    = $b['tshirt_tam'] ?? '';

  if ($id > 0) {
    run('UPDATE '.PREFIX.'alunos SET nome=?,tel=?,nasc=?,bi=?,sexo=?,cursoId=?,turmaId=?,propina=?,inscricao=?,estado=?,tshirt_num=?,tshirt_tam=? WHERE id=?',
        'sssssiiisss si', [$nome,$tel,$nasc,$bi,$sexo,$cursoId,$turmaId,$propina,$insc,$estado,$tnum,$ttam,$id]);
    ok(['id'=>$id]);
  } else {
    run('INSERT INTO '.PREFIX.'alunos (nome,tel,nasc,bi,sexo,cursoId,turmaId,propina,inscricao,estado,tshirt_num,tshirt_tam) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        'sssssiiisss s', [$nome,$tel,$nasc,$bi,$sexo,$cursoId,$turmaId,$propina,$insc,$estado,$tnum,$ttam]);
    ok(['id'=>db()->insert_id]);
  }
}

if ($action === 'alunos_delete') {
  $id = intval(body()['id'] ?? 0);
  if (!$id) err('ID inválido');
  run('DELETE FROM '.PREFIX.'alunos WHERE id=?', 'i', [$id]);
  ok();
}

// ============================================================
//  PAGAMENTOS
// ============================================================
if ($action === 'pagamentos_list') {
  ok(rows('SELECT * FROM '.PREFIX.'pagamentos ORDER BY id DESC'));
}

if ($action === 'pagamentos_save') {
  $b       = body();
  $alunoId = intval($b['alunoId'] ?? 0);
  $mes     = $b['mes'] ?? '';
  $valor   = floatval($b['valor'] ?? 0);
  $desc    = floatval($b['desconto'] ?? 0);
  $metodo  = $b['metodo'] ?? 'Dinheiro';
  $data    = $b['data'] ?: date('Y-m-d');
  $obs     = $b['obs'] ?? '';
  $util    = $b['utilizador'] ?? '';
  if (!$alunoId || $valor <= 0) err('Aluno e valor obrigatórios');
  run('INSERT INTO '.PREFIX.'pagamentos (alunoId,mes,valor,desconto,metodo,data,obs,utilizador) VALUES (?,?,?,?,?,?,?,?)',
      'isddssss', [$alunoId,$mes,$valor,$desc,$metodo,$data,$obs,$util]);
  ok(['id'=>db()->insert_id]);
}

// ============================================================
//  PRESENÇAS
// ============================================================
if ($action === 'presencas_list_all') {
  ok(rows('SELECT * FROM '.PREFIX.'presencas ORDER BY data DESC'));
}

if ($action === 'presencas_list') {
  $turmaId = intval($_GET['turmaId'] ?? 0);
  $data    = $_GET['data'] ?? '';
  if (!$turmaId || !$data) err('turmaId e data obrigatórios');
  $s = db()->prepare('SELECT * FROM '.PREFIX.'presencas WHERE turmaId=? AND data=?');
  $s->bind_param('is', $turmaId, $data);
  $s->execute();
  $r = $s->get_result();
  $out = [];
  while ($row = $r->fetch_assoc()) $out[] = $row;
  ok($out);
}

if ($action === 'presencas_save') {
  $b       = body();
  $turmaId = intval($b['turmaId'] ?? 0);
  $data    = $b['data'] ?? '';
  $lista   = $b['lista'] ?? [];
  if (!$turmaId || !$data) err('turmaId e data obrigatórios');
  run('DELETE FROM '.PREFIX.'presencas WHERE turmaId=? AND data=?', 'is', [$turmaId,$data]);
  foreach ($lista as $item) {
    $aId    = intval($item['alunoId']);
    $estado = $item['estado'] ?? 'P';
    run('INSERT INTO '.PREFIX.'presencas (turmaId,alunoId,data,estado) VALUES (?,?,?,?)', 'iiss', [$turmaId,$aId,$data,$estado]);
  }
  ok();
}

// ============================================================
//  CERTIFICADOS
// ============================================================
if ($action === 'certs_list') {
  ok(rows('SELECT * FROM '.PREFIX.'certificados ORDER BY id DESC'));
}

if ($action === 'certs_save') {
  $b       = body();
  $alunoId = intval($b['alunoId'] ?? 0);
  $cursoId = intval($b['cursoId'] ?? 0) ?: null;
  $util    = $b['utilizador'] ?? '';
  if (!$alunoId) err('alunoId obrigatório');
  run('INSERT INTO '.PREFIX.'certificados (alunoId,cursoId,utilizador) VALUES (?,?,?)', 'iis', [$alunoId,$cursoId,$util]);
  ok(['id'=>db()->insert_id]);
}

// ============================================================
//  SENHAS ROTATIVAS
// ============================================================
if ($action === 'sr_get') {
  $sr = row('SELECT * FROM '.PREFIX.'senhas_rotativas LIMIT 1');
  if (!$sr) ok(['dataAtivacao'=>'','totalMeses'=>10,'senhas'=>[]]);
  $sr['senhas'] = json_decode($sr['senhas'] ?? '[]', true);
  ok($sr);
}

if ($action === 'sr_set') {
  $b      = body();
  $data   = $b['dataAtivacao'] ?? '';
  $tot    = intval($b['totalMeses'] ?? 10);
  $senhas = json_encode($b['senhas'] ?? []);
  $count  = (int)row('SELECT COUNT(*) as n FROM '.PREFIX.'senhas_rotativas')['n'];
  if ($count > 0) {
    run('UPDATE '.PREFIX.'senhas_rotativas SET dataAtivacao=?,totalMeses=?,senhas=?', 'sis', [$data?:null,$tot,$senhas]);
  } else {
    run('INSERT INTO '.PREFIX.'senhas_rotativas (dataAtivacao,totalMeses,senhas) VALUES (?,?,?)', 'sis', [$data?:null,$tot,$senhas]);
  }
  ok();
}

// ============================================================
//  EXPORT
// ============================================================
if ($action === 'export') {
  $cfg = [];
  foreach (rows('SELECT chave,valor FROM '.PREFIX.'config') as $r) $cfg[$r['chave']] = $r['valor'];
  $sr = row('SELECT * FROM '.PREFIX.'senhas_rotativas LIMIT 1');
  if ($sr) $sr['senhas'] = json_decode($sr['senhas'] ?? '[]', true);
  ok([
    'config'           => $cfg,
    'utilizadores'     => rows('SELECT * FROM '.PREFIX.'utilizadores'),
    'cursos'           => rows('SELECT * FROM '.PREFIX.'cursos'),
    'turmas'           => rows('SELECT * FROM '.PREFIX.'turmas'),
    'alunos'           => rows('SELECT * FROM '.PREFIX.'alunos'),
    'pagamentos'       => rows('SELECT * FROM '.PREFIX.'pagamentos'),
    'presencas'        => rows('SELECT * FROM '.PREFIX.'presencas'),
    'certificados'     => rows('SELECT * FROM '.PREFIX.'certificados'),
    'senhas_rotativas' => $sr ?: null,
    'exportadoEm'      => date('c'),
  ]);
}

err('Acção desconhecida: '.$action, 404);
