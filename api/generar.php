<?php
// Motor en PHP (Webempresa) — genera 3 reseñas con Claude a partir de la
// experiencia real del cliente y, si las hay, de las fotos que subió.
// La API key se lee de config.php (no versionado, subido aparte por FTP).

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Usa POST']);
  exit;
}

$cfg = @include __DIR__ . '/config.php';
$apiKey = (is_array($cfg) && !empty($cfg['api_key'])) ? $cfg['api_key'] : getenv('ANTHROPIC_API_KEY');
$model  = (is_array($cfg) && !empty($cfg['model'])) ? $cfg['model'] : 'claude-sonnet-4-6';

if (!$apiKey) {
  http_response_code(500);
  echo json_encode(['error' => 'Falta la API key en el servidor']);
  exit;
}

$b = json_decode(file_get_contents('php://input'), true);
if (!is_array($b)) $b = [];

$negocio    = trim($b['negocio'] ?? '');
$sector     = trim($b['sector'] ?? '');
$ciudad     = trim($b['ciudad'] ?? '');
$servicio   = trim($b['servicio'] ?? '');
$atendio    = trim($b['atendio'] ?? '');
$estrellas  = $b['estrellas'] ?? 5;
$tono       = trim($b['tono'] ?? 'Cercano y natural');
$largo      = trim($b['largo'] ?? 'media');
$volveras   = trim($b['volveras'] ?? '');
$calidad    = trim($b['calidad'] ?? '');
$gusto      = trim($b['gusto'] ?? '');
$mejora     = trim($b['mejora'] ?? '');
$recomienda = trim($b['recomienda'] ?? '');
$fotos      = is_array($b['fotos'] ?? null) ? $b['fotos'] : [];

if ($negocio === '' || $servicio === '' || $gusto === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Faltan datos obligatorios']);
  exit;
}

$largoMap = [
  'corta' => '1 frase, muy breve (máx. 30 palabras)',
  'media' => '2-3 frases (40-70 palabras)',
  'larga' => 'un párrafo completo (80-130 palabras)',
];
$largoTxt = $largoMap[$largo] ?? '2-3 frases';

$lineas = ["- Negocio: $negocio"];
if ($sector !== '')     $lineas[] = "- Tipo de negocio: $sector";
if ($ciudad !== '')     $lineas[] = "- Ciudad o zona: $ciudad";
$lineas[] = "- Servicio/producto usado: $servicio";
if ($atendio !== '')    $lineas[] = "- Le atendió: $atendio";
$lineas[] = "- Puntuación que da el cliente: $estrellas de 5 estrellas";
$lineas[] = "- Lo que más le gustó: $gusto";
if ($mejora !== '')     $lineas[] = "- Lo que mejoraría: $mejora";
if ($calidad !== '')    $lineas[] = "- Relación calidad-precio: $calidad";
if ($volveras !== '')   $lineas[] = "- ¿Volverá?: $volveras";
if ($recomienda !== '') $lineas[] = "- Se lo recomendaría a: $recomienda";
$lineas[] = "- Tono pedido: $tono";
$lineas[] = "- Largo pedido: $largoTxt";
$datos = implode("\n", $lineas);

$conFotos = count($fotos) > 0;
$notaFotos = $conFotos ? ' y de las fotos que el cliente hizo (úsalas para mencionar algún detalle visible y concreto)' : '';

$prompt = "Eres un cliente real que acaba de escribir una reseña para la ficha de Google (Google Business Profile) de un negocio local, basándote ÚNICAMENTE en tu experiencia auténtica. Tu tarea es redactar bien esa reseña a partir de los datos reales que te doy$notaFotos.

DATOS REALES DE LA EXPERIENCIA:
$datos

REGLAS:
- Escribe en primera persona, como una persona normal, en español de España, natural y creíble.
- Refleja la puntuación de $estrellas/5: si es 5 muy positiva; 4 positiva con un matiz; 3 o menos honesta y equilibrada, mencionando con respeto lo mejorable.
- Menciona el nombre del negocio de forma natural y detalles concretos de lo que cuenta el cliente. Nada genérico ni de relleno.
- Si hay ciudad/zona, menciónala con naturalidad (ayuda al SEO local), sin forzarla. Si hay nombre del profesional que le atendió, cítalo con naturalidad. Solo en las versiones donde encaje bien; no en las tres igual.
- No inventes datos que no estén arriba. No uses hashtags, ni emojis, ni comillas, ni firmes.
- Que NO suene a texto de IA: evita frases hechas tipo \"sin duda recomiendo al 100%\", \"una experiencia inigualable\", \"altamente recomendable\".
- Aplica el tono y el largo pedidos.

Devuelve EXACTAMENTE 3 versiones distintas entre sí (varía el enfoque y las frases), separadas por una línea que contenga solo:
---
No añadas títulos, numeración, ni texto introductorio. Solo las 3 reseñas separadas por ---";

$content = [];
foreach (array_slice($fotos, 0, 5) as $dataUrl) {
  if (preg_match('#^data:(image/[a-zA-Z+]+);base64,(.+)$#', $dataUrl ?? '', $m)) {
    $content[] = [
      'type' => 'image',
      'source' => ['type' => 'base64', 'media_type' => $m[1], 'data' => $m[2]],
    ];
  }
}
$content[] = ['type' => 'text', 'text' => $prompt];

$payload = [
  'model' => $model,
  'max_tokens' => 1200,
  'messages' => [['role' => 'user', 'content' => $content]],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    'content-type: application/json',
    'x-api-key: ' . $apiKey,
    'anthropic-version: 2023-06-01',
  ],
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
  CURLOPT_TIMEOUT => 60,
]);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($res === false) {
  http_response_code(502);
  echo json_encode(['error' => 'No se pudo contactar con Claude: ' . $err]);
  exit;
}

$data = json_decode($res, true);
if ($http < 200 || $http >= 300) {
  http_response_code($http);
  echo json_encode(['error' => $data['error']['message'] ?? 'Error al llamar a Claude']);
  exit;
}

$texto = '';
foreach (($data['content'] ?? []) as $c) {
  if (($c['type'] ?? '') === 'text') $texto .= $c['text'];
}

$partes = preg_split('/\n-{3,}\n|\n?---\n?/', $texto);
$resenas = [];
foreach ($partes as $p) {
  $p = trim(preg_replace('/^["“]|["”]$/u', '', trim($p)));
  if ($p !== '') $resenas[] = $p;
}
$resenas = array_slice($resenas, 0, 3);
if (count($resenas) === 0) $resenas = [trim($texto)];

echo json_encode(['resenas' => $resenas], JSON_UNESCAPED_UNICODE);
