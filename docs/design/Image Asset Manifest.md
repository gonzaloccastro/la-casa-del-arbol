# La Casa del Árbol — Manifiesto de assets de imagen (desktop)
Corresponde a las pantallas aprobadas: Home, Agenda, Evento individual. IDs = los usados en `Website Direction.dc.html`.

## 1. Home — Hero carousel (5 slides, puramente visual: sin texto/CTA overlay)

### hero-agenda
- Pantalla/sección: Home → Hero, slide 1
- Propósito: representar la cara "Agenda" de La Casa (foto ambiente, no poster)
- Contenido: música en vivo / público en la sala, tomada durante un evento real
- Fuente preferida: A (foto real existente)
- Aspect ratio desktop: ~16:9 a 16:10 (contenedor 82vh, min 640px / max 920px de alto, full-width)
- Dimensiones recomendadas: 2400×1500px mínimo (permite crop y densidad de pantalla)
- Crop: cover, sin distorsión; foto debe leerse bien aun recortada arriba/abajo
- Foco/composición: sujeto principal (banda/público) centrado horizontalmente, algo de aire arriba para no chocar con el header sticky
- ¿Crop mobile aparte?: sí, probable (vertical/cuadrado en mobile)
- Prioridad: crítica

### hero-saberes
- Pantalla/sección: Home → Hero, slide 2
- Propósito: representar "Saberes Comunidad / Saberes +60"
- Contenido: taller o encuentro con adultos mayores, foto real de la actividad
- Fuente preferida: A
- Aspect ratio: igual que hero-agenda
- Dimensiones: 2400×1500px+
- Crop: cover
- Foco: rostros/manos en la actividad, evitar fondo vacío dominante
- ¿Crop mobile aparte?: sí, probable
- Prioridad: crítica

### hero-reservas
- Pantalla/sección: Home → Hero, slide 3
- Propósito: representar "Reservas"
- Contenido: mesas/patio listo para recibir público, ambiente de La Casa antes/durante un evento
- Fuente preferida: A
- Aspect ratio: igual que arriba
- Dimensiones: 2400×1500px+
- Crop: cover
- Foco: patio/mesas, luz cálida, sin gente posando (ambiente, no stock)
- ¿Crop mobile aparte?: sí, probable
- Prioridad: secundaria

### hero-alquila
- Pantalla/sección: Home → Hero, slide 4
- Propósito: representar "Alquilá el Árbol" (eventos privados)
- Contenido: patio o salón vacío/preparado, o un cumpleaños/evento privado real
- Fuente preferida: A (o C si hay que recortar una foto existente más ancha)
- Aspect ratio: igual que arriba
- Dimensiones: 2400×1500px+
- Crop: cover
- Foco: el espacio como protagonista, sin logos de terceros visibles
- ¿Crop mobile aparte?: sí, probable
- Prioridad: secundaria

### hero-lacasa
- Pantalla/sección: Home → Hero, slide 5
- Propósito: representar "La Casa" (institucional)
- Contenido: fachada o patio interno de La Casa del Árbol
- Fuente preferida: A
- Aspect ratio: igual que arriba
- Dimensiones: 2400×1500px+
- Crop: cover
- Foco: fachada centrada o patio con el árbol visible si es posible (vínculo con el nombre)
- ¿Crop mobile aparte?: sí, probable
- Prioridad: crítica

## 2. Home — Eventos destacados del mes (grid de 4 cards)
Mismo asset que su contraparte en Agenda (ver tabla de posters abajo): `cine-jojo`, `pop`, `lapoana`, `parri`.
- Fuente preferida: B (poster real del evento) — nunca recortar/recolorear el poster para forzarlo a un frame rojo
- Prioridad: crítica

## 3. Agenda — Grid editorial (masonry, prefijo `grid-`)
## 4. Evento individual — Hero de poster (prefijo `single-`) y relacionados (prefijo `rel-`)

Los tres contextos (Home destacados, Agenda grid, Single event hero/relacionados) reutilizan el MISMO poster por evento — un solo asset por evento, no una versión por pantalla.

| Asset ID (posterId) | Evento | Aspect ratio nativo | Fuente | Prioridad |
|---|---|---|---|---|
| cine-jojo | Cine Club Saberes | 4:5 | B (poster real) | crítica |
| bubis | Bubis Vagins + Señorita Novio | 3:4 | B | crítica |
| pop | Lucernaria + Juana Talks + Monte Splash | 1:1 | B | crítica |
| kitty | Bailongo · DJ Kitty Cumbia | 2:3 | B | secundaria |
| lapoana | Lapoana | 4:5 | B | crítica |
| garage | Sindicato del Cuero (BA Garage) | 3:4 | B | secundaria |
| metegol | Torneo de Metegol | 1:1 | B | secundaria |
| flamencos | Encuentros Flamencos | 4:5 | B | secundaria |
| parri | Parri y Veredazo | 3:4 | B | crítica |

Especificaciones comunes a todos los posters:
- Dimensiones recomendadas: mínimo 1600px en el lado mayor (posters suelen ser verticales), manteniendo el aspect ratio nativo de cada afiche — NO forzar a un aspect ratio común
- Crop: contain/none — el poster se muestra completo, sin recortar ni recolorear; el aspect ratio del contenedor en cada pantalla ya está seteado por evento (`aspect-ratio` en el dato del evento) para que coincida con el poster real
- Foco/composición: ninguno adicional — usar el arte del evento tal cual fue diseñado (identidad individual del afiche)
- ¿Crop mobile aparte?: no debería ser necesario si se preserva el aspect ratio nativo; revisar solo si un poster es extremadamente panorámico
- Fuente C (crop/adaptación) aplica únicamente si el archivo original viene con sangrado/marcas de corte que haya que recortar a bordes limpios — sin alterar el diseño del afiche

## 5. Home — Instagram (grid de 6 tiles cuadrados, prefijo `ig-`)

| Asset ID | Contenido sugerido | Fuente | Aspect ratio | Dimensiones | Prioridad |
|---|---|---|---|---|---|
| ig-sept | Post: agenda de septiembre (flyer mensual) | B/C (recorte cuadrado del post real) | 1:1 | 1080×1080px | secundaria |
| ig-alquila | Post: "¡Alquilá el Árbol!" (patio) | A/C | 1:1 | 1080×1080px | secundaria |
| ig-pacto | Reel: El Pacto Sensible — Prima Limón | C (frame/cover del reel real) | 1:1 | 1080×1080px | opcional |
| ig-canto | Post: música en vivo en la casa | A/C | 1:1 | 1080×1080px | opcional |
| ig-cosmo | Post: Cosmo — poster real | B/C | 1:1 | 1080×1080px | opcional |
| ig-flamencos-ig | Reel: Encuentros Flamencos | C | 1:1 | 1080×1080px | opcional |

Crop: cover centrado (recorte cuadrado desde el contenido real de Instagram, sin generar nada nuevo). Foco: mantener el elemento principal del post (texto o cara) centrado en el crop 1:1. Mobile: la grilla probablemente pasa a 2–3 columnas; mismo asset cuadrado sirve sin recrop.

## Notas generales
- No se requieren assets generados (D) — todo el inventario tiene equivalente real (foto de La Casa o poster de evento existente).
- Los posters (B) son prioridad crítica: son el elemento de mayor impacto visual en Home, Agenda y Evento, y cada uno debe conservar su identidad gráfica individual.
- Las 5 fotos del hero (A) son la siguiente prioridad: sin ellas el Home queda con placeholders vacíos en la sección de mayor impacto de la página.
- El grid de Instagram es el bloque de menor prioridad — puede quedar con placeholders hasta tener acceso a los posts reales o su exportación.
