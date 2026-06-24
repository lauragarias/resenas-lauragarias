# Generador de reseñas ✱ Laura G.Arias

Web mobile-first para generar reseñas de **Google Business Profile** a partir de
una experiencia real: el cliente pone el negocio, contesta unas preguntas, sube
fotos y la herramienta le devuelve 3 versiones listas para copiar y pegar.

La redacción la hace **Claude** (incluyendo la lectura de las fotos) desde una
función serverless, para que la API key nunca quede expuesta en el móvil.

> **Úsala solo para reseñas auténticas.** Google y la normativa europea de
> competencia desleal prohíben las reseñas falsas. Esta herramienta solo ayuda a
> redactar mejor lo que un cliente real vivió de verdad.

## Estructura

```
index.html        → la web (formulario + resultados, con tu marca)
api/generar.js     → función serverless que llama a Claude
vercel.json        → config de Vercel
.env.example       → variables de entorno necesarias
```

## Qué necesitas

1. Una cuenta gratuita en **Vercel**: https://vercel.com
2. Una **API key de Anthropic**: https://console.anthropic.com → *API Keys*
   (carga unos pocos € de saldo; cada reseña cuesta céntimos)

## Desplegar en 5 minutos (sin tocar terminal)

1. Entra en https://vercel.com y crea cuenta (puedes usar tu Google).
2. Crea un repositorio en GitHub con esta carpeta y súbela, **o** en Vercel pulsa
   *Add New → Project → Deploy* y arrastra la carpeta `reseñas-gbp`.
3. En la pantalla de configuración, antes de *Deploy*, abre
   **Environment Variables** y añade:
   - `ANTHROPIC_API_KEY` = tu clave `sk-ant-...`
4. Pulsa **Deploy**. En ~1 minuto tendrás un enlace tipo
   `https://resenas-gbp.vercel.app`.
5. Ábrelo en el móvil y, en el menú del navegador, *Añadir a pantalla de inicio*
   para tenerlo como una app.

## Desplegar con terminal (alternativa)

```bash
cd ~/Desktop/reseñas-gbp
npm i -g vercel        # solo la primera vez
vercel                 # sigue los pasos, crea el proyecto
vercel env add ANTHROPIC_API_KEY    # pega tu clave
vercel --prod          # despliegue final con el enlace público
```

## Probar en local

```bash
cd ~/Desktop/reseñas-gbp
cp .env.example .env.local   # y pon tu ANTHROPIC_API_KEY dentro
npm i -g vercel
vercel dev                   # abre http://localhost:3000
```

## Ajustes rápidos

- **Gastar menos:** en las variables de entorno añade
  `CLAUDE_MODEL=claude-haiku-4-5-20251001` (más barato; calidad algo menor).
- **Preguntas del formulario:** edítalas en `index.html`.
- **Instrucciones de redacción:** edita el `prompt` en `api/generar.js`.
