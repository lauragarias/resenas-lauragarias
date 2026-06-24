// Función serverless (Vercel) — genera 3 reseñas con Claude a partir de la
// experiencia real del cliente y, si las hay, de las fotos que subió.
// Requiere la variable de entorno ANTHROPIC_API_KEY (ver README).

const MODEL = process.env.CLAUDE_MODEL || 'claude-sonnet-4-6';

module.exports = async (req, res) => {
  if (req.method !== 'POST') {
    res.status(405).json({ error: 'Usa POST' });
    return;
  }
  if (!process.env.ANTHROPIC_API_KEY) {
    res.status(500).json({ error: 'Falta la API key en el servidor (ANTHROPIC_API_KEY)' });
    return;
  }

  try {
    const b = typeof req.body === 'string' ? JSON.parse(req.body || '{}') : (req.body || {});
    const {
      negocio = '', sector = '', ciudad = '', servicio = '', atendio = '',
      estrellas = 5, tono = 'Cercano y natural', largo = 'media',
      volveras = '', calidad = '',
      gusto = '', mejora = '', recomienda = '', fotos = []
    } = b;

    if (!negocio.trim() || !servicio.trim() || !gusto.trim()) {
      res.status(400).json({ error: 'Faltan datos obligatorios' });
      return;
    }

    const largoTxt = {
      corta: '1 frase, muy breve (máx. 30 palabras)',
      media: '2-3 frases (40-70 palabras)',
      larga: 'un párrafo completo (80-130 palabras)'
    }[largo] || '2-3 frases';

    const datos = [
      `- Negocio: ${negocio}`,
      sector && `- Tipo de negocio: ${sector}`,
      ciudad && `- Ciudad o zona: ${ciudad}`,
      `- Servicio/producto usado: ${servicio}`,
      atendio && `- Le atendió: ${atendio}`,
      `- Puntuación que da el cliente: ${estrellas} de 5 estrellas`,
      `- Lo que más le gustó: ${gusto}`,
      mejora && `- Lo que mejoraría: ${mejora}`,
      calidad && `- Relación calidad-precio: ${calidad}`,
      volveras && `- ¿Volverá?: ${volveras}`,
      recomienda && `- Se lo recomendaría a: ${recomienda}`,
      `- Tono pedido: ${tono}`,
      `- Largo pedido: ${largoTxt}`
    ].filter(Boolean).join('\n');

    const prompt = `Eres un cliente real que acaba de escribir una reseña para la ficha de Google (Google Business Profile) de un negocio local, basándote ÚNICAMENTE en tu experiencia auténtica. Tu tarea es redactar bien esa reseña a partir de los datos reales que te doy${fotos.length ? ' y de las fotos que el cliente hizo (úsalas para mencionar algún detalle visible y concreto)' : ''}.

DATOS REALES DE LA EXPERIENCIA:
${datos}

REGLAS:
- Escribe en primera persona, como una persona normal, en español de España, natural y creíble.
- Refleja la puntuación de ${estrellas}/5: si es 5 muy positiva; 4 positiva con un matiz; 3 o menos honesta y equilibrada, mencionando con respeto lo mejorable.
- Menciona el nombre del negocio de forma natural y detalles concretos de lo que cuenta el cliente. Nada genérico ni de relleno.
- Si hay ciudad/zona, menciónala con naturalidad (ayuda al SEO local), sin forzarla. Si hay nombre del profesional que le atendió, cítalo con naturalidad. Solo en las versiones donde encaje bien; no en las tres igual.
- No inventes datos que no estén arriba. No uses hashtags, ni emojis, ni comillas, ni firmes.
- Que NO suene a texto de IA: evita frases hechas tipo "sin duda recomiendo al 100%", "una experiencia inigualable", "altamente recomendable".
- Aplica el tono y el largo pedidos.

Devuelve EXACTAMENTE 3 versiones distintas entre sí (varía el enfoque y las frases), separadas por una línea que contenga solo:
---
No añadas títulos, numeración, ni texto introductorio. Solo las 3 reseñas separadas por ---`;

    const content = [];
    for (const dataUrl of (Array.isArray(fotos) ? fotos.slice(0, 5) : [])) {
      const m = /^data:(image\/[a-zA-Z+]+);base64,(.+)$/.exec(dataUrl || '');
      if (!m) continue;
      content.push({
        type: 'image',
        source: { type: 'base64', media_type: m[1], data: m[2] }
      });
    }
    content.push({ type: 'text', text: prompt });

    const apiRes = await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: {
        'content-type': 'application/json',
        'x-api-key': process.env.ANTHROPIC_API_KEY,
        'anthropic-version': '2023-06-01'
      },
      body: JSON.stringify({
        model: MODEL,
        max_tokens: 1200,
        messages: [{ role: 'user', content }]
      })
    });

    const data = await apiRes.json();
    if (!apiRes.ok) {
      const msg = data?.error?.message || 'Error al llamar a Claude';
      res.status(apiRes.status).json({ error: msg });
      return;
    }

    const texto = (data.content || []).filter(c => c.type === 'text').map(c => c.text).join('\n');
    const resenas = texto
      .split(/\n-{3,}\n|\n?---\n?/)
      .map(s => s.replace(/^["“]|["”]$/g, '').trim())
      .filter(Boolean)
      .slice(0, 3);

    res.status(200).json({ resenas: resenas.length ? resenas : [texto.trim()] });
  } catch (ex) {
    res.status(500).json({ error: ex.message || 'Error inesperado' });
  }
};
