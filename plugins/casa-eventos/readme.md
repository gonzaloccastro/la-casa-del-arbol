# Casa Eventos

**Version:** 0.2.0 (milestone E2, frontend integration; E1 Event Core in 0.1.0)
**Requires:** WordPress 6.6+ (developed against 7.1.2), PHP 8.0+ (tested with 8.2). No other plugins; **WooCommerce is not required**.
**Contract:** `docs/implementation/casa-eventos-contract.md` in the repository.

Eventos de La Casa del Árbol: la entidad Evento, sus categorías, fechas, estados, visibilidad y consultas. Es la única fuente de verdad de los eventos; Home, Agenda y la página del evento leen de acá (fase E2, completa). El theme `la-casa-del-arbol` sigue siendo solo presentación.

El plugin no imprime markup en el frontend: el theme arma la página del evento, la Agenda y los destacados de la Home con la API pública (`Event`, `Queries`). El plugin sí decide visibilidad (noindex, sitemap y búsqueda de los ocultos). No crea productos de WooCommerce y no vende entradas.

Caché: la Home, la Agenda y la página del evento se calculan en cada visita con el estado real de los eventos. Si producción usa caché de página completa, revisar su duración o excluir esas páginas antes de publicar (ver `docs/deployment.md` en el repositorio).

## Qué agrega en wp-admin

- **Eventos** (menú): lista con afiche, fecha del evento, categoría, estado efectivo, modalidad y visibilidad. Vistas *Próximos · Pasados · Pausados · Cancelados*, orden por fecha del evento, filtros por mes (del inicio del evento) y por categoría. Marca con "⚠ Datos incompletos" un evento publicado que no cumple las reglas (por ejemplo, creado por WP-CLI).
- **Editor del evento** (Gutenberg):
  - título, descripción (bloques), **Extracto = bajada**, **Imagen destacada = afiche**, **Categorías** (una sola);
  - panel **Datos del evento**: inicio \*, fin, modalidad de acceso \* (venta de entradas · reserva por WhatsApp · venta externa), enlace de venta externa (solo en venta externa), tipo de entrada (paga · entrada libre · a la gorra), texto de entrada, aforo, cierre de venta;
  - panel **Publicación del evento**: activo / pausado / cancelado, visible en la Agenda, destacado en la Home, ID público (UUID, solo lectura).
- **Categorías**: color de la etiqueta (menta / amarillo) y orden. Son filtros generales y estables de la Agenda (Música, Cine, Teatro…), nunca artistas, nombres de eventos, productoras, ciclos ni tipos de entrada. El plugin **no crea categorías**: las carga un administrador.
- **Ajustes**: nombre y dirección del lugar (un solo lugar en V1).

## Roles

- **Programador** (`casa_programador`): la persona que carga y mantiene las fechas. En el menú ve solo **Eventos** (Todos los eventos · Añadir evento) y **Medios**. Al entrar llega a la lista de eventos. La barra superior solo tiene el enlace al sitio público (desde el sitio, "Eventos" vuelve a la lista) y **Salir**. Crea, edita (de cualquier autor), publica, programa, pausa y cancela eventos; elige **una categoría existente**; sube y elige afiches (y edita el texto alternativo de las imágenes que subió). **No** puede borrar eventos, reactivar un evento cancelado, crear/editar/borrar categorías, entrar a Ajustes, ni tocar Entradas, Páginas, usuarios, plugins, temas o ajustes de WordPress.
- **Administrador**: todo lo anterior más borrar eventos, administrar categorías, Ajustes y reactivar cancelados.
- Los roles Editor, Autor y Colaborador de WordPress **no** tienen acceso a Eventos.
- El editor de bloques actual es una interfaz provisoria para el Programador; más adelante habrá una pantalla propia y simple para cargar eventos.

Para dar de alta a alguien: Usuarios → Añadir nuevo → Perfil: **Programador**.
- Aviso si la zona horaria del sitio no es una ciudad (Ajustes → Generales → Buenos Aires).

## Reglas

- Los borradores pueden estar incompletos. **Publicar o programar** exige: título, inicio, modalidad, una sola categoría, enlace en venta externa, fin posterior al inicio, aforo ≥ 1 y cierre de venta no posterior al fin. El servidor lo valida (el editor muestra el motivo).
- Fechas: hora local + zona horaria del evento (se captura al primer guardado). Sin fin, el evento dura 3 horas a efectos de "finalizado". Un evento pertenece al día y mes en que **empieza** (domingo 00:30 es domingo).
- Aforo vacío = 100, guardado en el evento. Cierre de venta vacío = 1 hora antes del inicio (se calcula). Puede ser posterior al inicio (con aviso), nunca al fin.
- Estados: *finalizado* se deriva de la fecha. *Pausado* y *cancelado* siguen públicos en la Agenda y sin acciones, y **nunca aparecen en Destacados de la Home ni en "También en la agenda"**. Solo un administrador puede reactivar un evento cancelado. Cancelar nunca reembolsa nada.
- Botón de la página del evento:
  - **Reservar** (WhatsApp) o **Comprar entradas** (venta externa) solo si el evento está activo y tiene destino.
  - **Venta de entradas** propia todavía no existe: el evento se publica sin botón de compra ("Entradas a la venta próximamente"), y el editor lo avisa.
  - El cierre de venta solo aplica a la venta propia de entradas.
- **No visible en la Agenda** (oculto): la página sigue accesible por enlace, pero no se indexa y no aparece en el sitemap ni en la búsqueda del sitio.

## Para desarrolladores

Estructura:

```
casa-eventos.php          bootstrap, activación (roles, rewrite) y desactivación
uninstall.php             no borra contenido; solo quita el rol Programador y las capacidades del plugin
inc/core/                 Event Core: único código que conoce el esquema (_casa_*)
  schema.php              claves, enumeraciones, defaults (filtrables)
  datetime.php            hora local ↔ GMT, meses, "ahora" (nunca la zona por defecto de PHP)
  state.php               estado efectivo, etiquetas
  validation.php          reglas puras (formato / publicación / avisos)
  enforcement.php         aplica las reglas: REST (editor) y Quick/Bulk Edit
  post-type.php           casa_evento, /evento/{slug}/, sin archivo
  taxonomy.php            casa_categoria + color/orden
  meta.php                register_post_meta (REST tipado, revisiones) + saneo
  sync.php                campos derivados, defaults materializados, acciones
  uuid.php                UUID inmutable y único
  class-event.php         modelo de lectura (Event)
  queries.php             API de consultas (Queries)
  visibility.php          eventos ocultos: noindex, fuera del sitemap y de la búsqueda
  rest.php                campo REST de solo lectura casa_event
  settings.php            opción del lugar
  capabilities.php        capacidades propias de Evento y Categoría, rol Programador (instalación/actualización)
inc/admin/                pantallas; solo usan Event, Queries y funciones de core
  navigation.php          menú y barra del Programador (solo navegación; se carga siempre)
assets/admin/             panel del editor (JS sin build) y CSS de admin
tests/                    pruebas estáticas (no se despliegan)
```

Leer eventos siempre por el modelo y las consultas, nunca por meta:

```php
use CasaEventos\Core\Event;
use CasaEventos\Core\Queries;

$event = Event::get( $post_id );
$event->start();            // DateTimeImmutable en la zona del evento
$event->effective_state();  // draft | scheduled | active | paused | cancelled | finished
$event->is_actionable();    // true solo si está activo

Queries::featured_events( array( 'limit' => 4 ) );   // solo activos: sin pausados ni cancelados
Queries::month_events( Queries::current_month() );   // el mes local: listados, con historia, pausados y cancelados
Queries::adjacent_event_month( '2026-09', -1 );      // mes anterior con eventos (saltea los vacíos)
Queries::adjacent_event_month( '2026-09', 1, array( 'category' => 'cine' ) );   // … de esa categoría
Queries::parse_month( $_GET['mes'] ?? null );        // 'YYYY-MM' válido (1970-01 … 9999-11) o null
Queries::categories();               // todas las categorías (WP_Term[]) en el orden de los chips
Queries::related_events( $event );   // otros próximos, sin pausados ni cancelados (máx. 3)
$event->cta();                       // ¿hay botón? available, mode, reason (estado, no_target, not_on_sale…), url
```

Hooks: `casa_eventos/loaded`, `casa_eventos/event_synced`, `casa_eventos/schedule_changed`, `casa_eventos/event_cancelled`, `casa_eventos/event_reactivated`, `casa_eventos/uuid_regenerated`; filtros `casa_eventos/validate_event`, `casa_eventos/default_capacity`, `casa_eventos/default_duration_minutes`, `casa_eventos/default_sales_close_offset_minutes`, `casa_eventos/fallback_timezone`, `casa_eventos/reactivate_capability`.

Capacidades: el Evento usa las suyas (`edit_casa_eventos`, `publish_casa_eventos`, …) y la categoría `manage/edit/delete/assign_casa_categorias`. Si cambia la definición del rol, subir `CAPS_VERSION` en `inc/core/capabilities.php`: el sitio la vuelve a aplicar en la siguiente carga (las actualizaciones de plugins no ejecutan la activación).

### Pruebas estáticas

Sin WordPress ni base de datos (no reemplazan la QA en un WordPress real):

```
PHP=/ruta/a/php bash plugins/casa-eventos/tests/run-static.sh
```

Corre `php -l`, `node --check`, las pruebas unitarias (`tests/run.php`), los controles de arquitectura (`tests/check-architecture.php`) y la prueba del panel (`tests/event-panel.test.cjs`). La carpeta `tests/` no debe desplegarse; sus scripts salen si no se ejecutan por CLI.
