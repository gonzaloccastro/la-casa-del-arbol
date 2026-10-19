# T1 — Especificación de tipos de entrada, precios y cupos

**Estado:** READY FOR IMPLEMENTATION SCOPING / revisión 1, 2026-10-12. Especificación documental; no autoriza ejecución. A1 adoptada y A9 aprobada por el usuario; detalles técnicos de este documento son scope propuesto para el task T1. Ventas propias permanecerán cerradas.

**Base:** `main@7aa22adb1a0e740b8a5a588b0363d2291aafc1c9`, E3.2 integrado localmente. Task documental `T0.1-CONSOLIDATION-T1-SPEC`; solo modifica [T0.1](t0.1-ticketing-architecture-proposal.md) y este archivo. Implementación/runtime, instalación Woo, migraciones, commit y producción fuera del task actual.

## 1. Autoridad, objetivo y límites

Fuentes: [Ticketing V1](ticketing-v1-contract.md) §§3,5,6,12,17; [Casa Eventos](casa-eventos-contract.md) §§1,6,8,14,16; [ownership](content-ownership.md); [T0.1](t0.1-ticketing-architecture-proposal.md) §§11–12. No cambia las reglas aprobadas. A9 amplía explícitamente la operación de tipos/precios/cupos al Programador, sin acceso Woo general. Los contratos históricos de roles deberán registrar la extensión al entregar código; no fingir que los permisos ya están instalados.

Objetivo T1: configurar y consultar tipos, precios y cupos desde el evento usando Woo CRUD y permisos A9, conservar identidad e historia, y dejar contratos internos listos para reservas posteriores. No crear pedidos, retenciones, callbacks, pagos, refund, emails, exports ni disponibilidad pública transaccional. Tampoco aprobar las tres tablas de T0.1.

T1 puede completarse con precio nominal/moneda de la instalación de QA, fiscalidad marcada pendiente y venta denegada. La decisión fiscal, Payway y credenciales bloquean sus flujos posteriores, no la configuración administrativa. No mostrar el nominal como total final al comprador.

## 2. Modelo y relaciones

- Un Event existente (ID + UUID inmutable) se vincula a un producto variable Woo interno; cada tipo tiene una variación virtual con stock propio, sin backorders ni stock heredado del padre. No clonar eventos ni añadir CPT de tickets.
- Crear el producto solo al guardar explícitamente el primer tipo de un evento guardado, nunca por GET, activación o guardado editorial sin tipos. Un evento nuevo debe guardarse primero. Mantener tipos al pasar de tickets a whatsapp/external; no borrar producto ni restablecer stock al volver.
- Relación autoritativa en metadata del producto Woo: evento ID + UUID + versión de esquema; metadata de variación: UUID de tipo inmutable, nombre operativo, activo, corte opcional y máximo por compra. Prefijo técnico propuesto `_lcda_ticket_`; no usar `_casa_*` fuera de Core. Precio y cantidad stock usan propiedades Woo nativas, no copias en Event.
- Producto oculto de catálogo/búsqueda; marcador comercial obliga a denegar compra y altas al carrito por todas las rutas públicas. Ocultar no basta. No exponer página comercial independiente ni cambiar Home/Agenda/Single. T1 no registra el filtro de venta disponible como true.
- Un identificador estable del tipo en el atributo de variación evita que renombrar la etiqueta visible cambie identidad. No aceptar producto/parent/event UUID desde el cliente para relinkear; resolverlos en servidor desde el evento y comprobar relación completa.
- Resolver 0/1/múltiples productos: 0 permite creación explícita; 1 permite editar; múltiples o UUID discordante bloquean escritura y requieren revisión, sin elegir uno arbitrariamente. Duplicación de eventos no copia relaciones comerciales. Reintento de creación no crea duplicados: serializar la creación por evento y volver a resolver antes de crear; mecanismo técnico a demostrar en T1, sin adoptar el coordinador de reservas T2.
- La estructura variable/variación, los hooks precisos y la versión Woo compatible se fijan contra la versión elegida para QA; no asumir compatibilidad por la versión de la cabecera del plugin.

## 3. Campos y validación de tipos

Validar payload completo antes de mutar; rechazar campos desconocidos, IDs ajenos y valores no escalares. Omitir campo preserva valor; borrar explícitamente corte elimina solo ese corte. Una operación de fila crea/modifica un tipo; no batch masivo que prometa atomicidad inexistente.

| Campo | Regla T1 / default | Persistencia y semántica |
|---|---|---|
| Identidad tipo | UUID generado servidor; no editable | Metadata variación; nunca regenerar al renombrar |
| Nombre | Texto no vacío tras trim, sin HTML; límite técnico propuesto 120 caracteres | Nombre operativo visible; duplicados por evento rechazados tras normalización de espacios/case para evitar ambigüedad |
| Precio nominal | Decimal finito ≥0; sin notación científica ni redondeo silencioso; escala de moneda configurada | Propiedad de precio Woo; UI normaliza separador local y servidor valida decimal canónico |
| Moneda | Solo lectura de configuración Woo y snapshot de configuración | Sin selector por evento ni cambio de moneda global por Programador; mismatch con configuración almacenada bloquea edición de precio hasta revisión administrativa, nunca conversión implícita |
| Cupo del tipo | Entero ≥0 explícito; 0 representa agotado, no ilimitado | Stock propio Woo + lectura de consumo cuando exista; sin backorders |
| Activo | Booleano; default false; cambio manual | No automatizar tandas ni confundir con estado Event/publicación |
| Corte propio | Opcional, fecha/hora real en zona Event; vacío hereda corte Event | Guardar representación local y derivación UTC mediante helpers Core; corte efectivo = menor de Event y tipo; un corte posterior al Event se informa como limitado, no extiende venta |
| Máximo por compra | Entero 1–10, default 10 | Máximo propio; T3 impone además suma ≤10 entre tipos |

Precio cero se puede representar como dato inactivo; activar tipo cero queda bloqueado con explicación de política gratuita pendiente. Esto es una restricción del scope T1, no decisión de excluir entradas gratis de V1. Un precio positivo permite activar configuración aunque la venta global continúe cerrada. No añadir cupones, sale prices programados, impuestos, cargos ni reglas de cuotas. Configuración fiscal existente no se altera ni se certifica correcta. Cambiar precio solo afecta futuras cotizaciones; nunca reescribir pedidos, reservas o intentos.

Los tipos pueden configurarse para eventos borrador, pausados, cancelados o de otra modalidad. Activo significa configurado, no comprable: publicar, reactivar o cambiar acceso no habilita comercio T1. La elegibilidad futura combina Event activo/publicado, modalidad tickets, cortes, activo del tipo, cantidades y disponibilidad; T2/T3 implementan ejecución, no UI.

## 4. Aforo global, stock y frontera de consumo

Event Core mantiene aforo global entero positivo, default 100; vacío en su formulario conserva semántica existente de restablecer default. La suma de cupos de tipos puede superar el aforo. No limitar esa suma ni mover aforo al producto padre. No introducir aforo ilimitado.

Contrato interno propuesto `occupancy(event_id)` devuelve `known`, `sold`, `held`, desglose por tipo y revisión; nunca devuelve ceros por indisponibilidad de integración. T1 no construye ledger: solo puede considerar cero cuando el evento comercial acaba de inicializarse sin historial y todas las vías de compra siguen cerradas. Eventos/productos importados, restaurados, con órdenes referenciadas o consumo no verificable devuelven unknown. No inferir ausencia de ventas de un stock alto o de la falta de un contador.

Toda reducción global exige `nuevo C ≥ sold + held`; reducción por tipo exige `nuevo cupo_i ≥ sold_i + held_i`. Stock restante Woo es `cupo_i − sold_i`; held se resta después en disponibilidad, no dos veces en stock. En T1 sin consumo conocido no nulo, cupo y stock restante coinciden; con consumo desconocido **toda reducción y todo set absoluto de stock quedan bloqueados**. No admitir aumento que reescriba stock calculado con vendidos desconocidos. Edición de nombre/precio/activo no reajusta cantidades. Mostrar “consumo no disponible” en vez de vendido=0.

La protección cubre formulario de tipos, aforo en E3.2, save adapter, REST/editor nativo y restauración de revisión de aforo; no basta ocultar controles. Si no se puede interceptar una ruta de reducción con las garantías requeridas, denegar esa operación para el evento comercial y documentar límite. Una capability comercial es requisito adicional al permiso de evento al cambiar aforo de eventos comerciales; eventos sin tipos conservan operación E3.2.

El filtro de validación actual puede expresar rechazo pero no aporta exclusión mutua. T1 debe serializar cambios de configuración conflictivos y rechazar snapshots obsoletos; no declarar seguridad con ventas simultáneas. Antes de T2/T3 habilitar compras, todas las ediciones de capacidad se integrarán con el coordinador único y sus checks atómicos. Ningún stock/reserva nativo o custom se activa en T1 para simular ese resultado.

## 5. A9: autorización y separación financiera

Decisión humana APPROVED: Administrator y Programador crean/modifican tipos, precios y capacidades, con autorización de servidor y por evento. Nombres técnicos propuestos:

| Capability primitiva nueva | Acción | Roles |
|---|---|---|
| `manage_casa_ticket_types` | Crear/editar nombre, activo, corte y máximo; consultar configuración | Administrator y Programador |
| `edit_casa_ticket_prices` | Crear/modificar precio nominal | Administrator y Programador |
| `edit_casa_ticket_capacities` | Crear/modificar cupo de tipos y aforo comercial | Administrator y Programador |

Cada servicio de lectura/escritura exige `current_user_can( 'edit_post', event_id )` y capability pertinente. Crear tipo con precio/cupo exige las tres; un payload mixto sin una capability se rechaza entero antes de escribir. No comprobar solo rol, menú o nonce. El calendario compartido permite editar eventos de otro autor si las capabilities de Event lo permiten; no relajar las restricciones de eventos inexistentes/trashed ni validar IDs por cast permisivo.

El controller aplica método POST, nonce por evento/acción y comprobación de snapshot; el servicio vuelve a comprobar autorización, propiedad parent/variation y invariantes aunque se invoque fuera de UI. Un usuario con capability comercial pero sin permiso del evento falla, y viceversa. Revocar capability entre GET y POST bloquea guardado. No dar `manage_woocommerce`, `edit_products`, `edit_shop_orders`, permisos de gateway ni genéricos de Posts/Pages.

Finanzas, configuración gateway, refunds y conciliación son administrador-only, fuera de T1; no crear sus controles ni capabilities por anticipado. A9 no autoriza aún PII/export. Reactivación cancelada, categorías y ajustes Event conservan gates existentes. Añadir nuevas capabilities al registro central de install/update/uninstall; no editar roles mediante un instalador paralelo.

## 6. UI operacional y persistencia

- Añadir sección server-rendered “Tipos de entrada” en la edición E3.2, después del formulario editorial, con lista de filas, alta y edición dedicada por tipo. Separar “Guardar tipo” de “Guardar evento” y lifecycle; no formularios anidados, SPA ni bundler. Mantener los campos actuales `entry_kind`/`entry_label`: describen acceso, no son variaciones Woo; usar etiquetas que eviten confundirlos.
- Mostrar nombre, precio nominal y moneda, cupo, activo, corte y máximo. Aforo global permanece en Datos del evento; mostrar referencia de solo lectura en tipos. Mensaje estable: venta propia aún no disponible. Sin “Comprar”, compradores, métricas financieras ni disponibilidad vendible.
- Operaciones de tipos no envían contenido/editorial pendiente. Avisar antes de abandonar cambios; bloques protegidos siguen protegidos. No ampliar Gutenberg para Programador; administrador conserva fallback editorial pero los cambios comerciales pasan por servicio autorizado.
- Woo ausente/incompatible: evento funciona; sección informa dependencia y deniega comandos de tipos. No instalar, activar ni actualizar desde la UI; no fatal y no fake-save.
- Validar todo antes de CRUD; tras error parcial no mostrar éxito ni reintentar creación ciegamente. Verificar relación y valores persistidos, conservar ID para recarga/revisión. No prometer rollback de múltiples entidades Woo. Para doble alta concurrente o solicitudes repetidas, definir clave de creación y serialización de configuración; misma clave/payload devuelve entidad existente, distinta carga genera conflicto.
- Fingerprint comercial incluye valores actuales de tipo, moneda/configuración relevante y aforo/identidad Event; no reutilizar solo `operational_state()` editorial. Dos escrituras simultáneas requieren serialize/recheck bajo exclusión real; un fingerprint por sí solo no evita lost update. Usar solución mínima de configuración T1, sin aprobar ni implementar tablas de reservas T2.

## 7. Historia, migración y versiones

- No borrar físicamente tipos en UI T1, usados o no; desactivar es suficiente. No eliminar producto con tipos ni resetear relaciones al cambiar modalidad. Guardar nombres/precios nuevos no actualiza líneas de pedidos, refund ni snapshots de reservas. Future snapshot builder consumirá valores canónicos; T1 no crea pedidos para hacerlo.
- Incluir payload de lectura para snapshot futuro: versión esquema, Event ID/UUID, tipo UUID/variation ID, nombre, nominal, moneda y reglas actuales. T2/T3 captura y persiste al reservar/comprar, junto a impuestos/totales definidos entonces. No confundir lectura de configuración actual con historia ya congelada.
- Esquema comercial propuesto v1 en metadata Woo; sin tablas propias ni migración masiva en T1. Datos Event existentes intactos; productos se crean por comando explícito. Marcador v1 se escribe solo cuando relación válida persiste; versión desconocida o migración incompleta bloquea edición comercial. No crear defaults de tipos para todos los eventos.
- Capability version actual 1 deberá avanzar a 2 en implementación aprobada, usando `maybe_install_roles()` existente e instalación idempotente. Registrar nuevas primitivas en limpieza de uninstall; preservar datos Event y Woo. No ejecutar actualización de roles ni migración en este task documental.
- Propuesta release de plugin T1: 0.3.0, sincronizada entre header, constante, readme y regla 7. Se presenta explícitamente, no es cambio realizado. Mantener theme 0.7.0. La versión Woo/PHP/WP de QA se fija y registra en el futuro task; no asumir que runtime coincide con checkout. No agregar Composer/npm/vendor.

## 8. Secuencia y allowlist propuesta para el writer T1

1. Registrar Git y baseline LocalWP, versión Woo de QA y scope autorizado; revisar código actual. Implementar loader condicional/contrato de lectura, relaciones y validación; adaptar checks de arquitectura con excepciones estrechas.
2. Implementar capabilities A9 y servicio de edición con protección por evento, exclusión de configuración, claves de alta, guardas de aforo y errores de persistencia. No introducir coordinator de reservas.
3. Integrar UI de tipos en E3.2 y guards de no-compra; validar regresión con Woo ausente y presente. Extender tests existentes del componente sin reemplazar harness de QA.
4. QA estática y funcional en LocalWP descartable autorizado; revisar diff y evidencia; entregar para review. Commit, merge o deployment requieren autorización propia.

Todos los paths siguientes son relativos al repo. Es allowlist propuesta de implementación, no autorización presente ni permiso de editar otros archivos por conveniencia:

| Archivos existentes permitidos en futuro task | Motivo acotado |
|---|---|
| `plugins/casa-eventos/casa-eventos.php`, `plugins/casa-eventos/readme.md` | Carga condicional y versión/documentación de uso |
| `plugins/casa-eventos/inc/core/capabilities.php` | Primitivas, install/update/uninstall y CAPS_VERSION |
| `plugins/casa-eventos/inc/core/event-write.php`, `plugins/casa-eventos/inc/core/enforcement.php`, `plugins/casa-eventos/inc/core/validation.php` | Hooks neutrales para guards de aforo/autorización y rutas existentes; sin importar Woo a Core |
| `plugins/casa-eventos/inc/admin/event-form.php`, `plugins/casa-eventos/inc/admin/operational.php` | Punto de extensión UI/transporte, sin CRUD Woo dentro del formulario |
| `plugins/casa-eventos/assets/admin/operational.css`, `plugins/casa-eventos/assets/admin/operational.js` | Sección de tipos y aviso de cambios pendientes, solo si necesario |
| `plugins/casa-eventos/tests/check-architecture.php`, `plugins/casa-eventos/tests/bootstrap.php`, `plugins/casa-eventos/tests/run-static.sh` | Delimitar reglas y dobles unitarios mínimos; no framework nuevo |
| `plugins/casa-eventos/tests/test-capabilities.php`, `plugins/casa-eventos/tests/test-event-write.php`, `plugins/casa-eventos/tests/test-enforcement.php`, `plugins/casa-eventos/tests/test-operational.php`, `plugins/casa-eventos/tests/operational.test.cjs` | Extender regresión de permisos, guards y UI |
| `docs/implementation/casa-eventos-contract.md`, `docs/implementation/ticketing-v1-contract.md`, `docs/implementation/content-ownership.md`, `docs/project-status.md`, `docs/implementation/t1-ticket-types-spec.md` | Registrar implementación real, A9 y resultados sin declarar deployment |

Archivos nuevos propuestos (sin glob abierto): `plugins/casa-eventos/inc/commerce/bootstrap.php`, `ticket-types.php`, `ticket-validation.php`, `ticket-write.php`, `capacity-guard.php`, `purchase-guard.php` dentro de ese mismo directorio; `plugins/casa-eventos/inc/admin/ticket-types.php`; `plugins/casa-eventos/tests/test-ticket-types.php`, `test-ticket-write.php`, `test-ticket-capacity.php` dentro de tests. Consolidar archivos si conviene, sin obligar a crear todos. Si hace falta otro path, justificar ampliación en el task antes de editarlo.

Regla 2: permitir símbolos Woo solo dentro de `inc/commerce/`; loader principal incluye bootstrap sin referenciar APIs Woo. UI consume DTO/servicio; Core y theme continúan sin Woo. Regla 1 mantiene aislamiento `_casa_*`; metadata comercial solo con CRUD Woo en commerce. No debilitar regla 9 del theme, ni protecciones E3.2 contra writer paralelo. Regla 7 avanza con release aprobada. No modificar theme, scripts deploy, settings globales, .git, dependencias ni artefactos frozen QA como parte de código T1.

## 9. QA y criterios de aceptación

Checks futuros, no ejecutados en este task. Reutilizar `PHP=<php LocalWP> bash plugins/casa-eventos/tests/run-static.sh` con Git for Windows bash, no WSL. Incluye lint PHP, JS, tests unitarios y arquitectura existentes. El `tests/bootstrap.php` de la suite versionada es un doble unitario: extenderlo mínimamente no habilita el antiguo bootstrap/harness de runtime E3.2. Ese harness permanece congelado y no se ejecuta ni modifica.

| Escenario | Resultado requerido |
|---|---|
| Crear dos tipos en evento guardado | Un padre variable, dos variaciones ligadas al ID/UUID correcto, stock individual, sin backorders |
| GET/reload/guardar solo contenido Event | No crea producto ni tipo; sin migración masiva |
| Doble alta/retry/creación simultánea | Un padre y una entidad por clave; no duplicados silenciosos ni lost update |
| Woo ausente/incompatible | Core/E3.2 funciona, tipos denegados con mensaje, sin fatal |
| Admin/Programador autorizados | Crear/editar tipos, precios y cupos de eventos propios y de otro autor permitido; sin administración Woo |
| Cap comercial sin permiso Event / permiso Event sin cap / revocación / CSRF | Denegación servidor sin escrituras; IDs de variación ajena rechazados |
| Refund/gateway/pedidos solicitados por Programador | Denegación; no UI/ruta financiera nueva |
| Nombre vacío/duplicado, precio negativo/exponente, cupo fraccionario/negativo, máximo 0/11, fecha inválida | Error antes de CRUD; input conservado para corregir; sin guardado parcial por validación |
| Decimal localizado y moneda cambiada | Normalización exacta sin redondeo; mismatch moneda bloquea edición, sin conversión tácita |
| Precio cero | Configuración inactiva posible; activación denegada por política pendiente |
| Aforo 100 y tipos con suma 150 | Configuración permitida; no compra posible ni disponibilidad global 150 |
| Ocupación conocida fixture S=70/H=8 | Reducción global a 77 denegada, 78 permitida por guard; prueba de regla, no de reserva concurrente |
| Consumo desconocido/importado | Reducción/set absoluto stock denegados; nunca asumir cero; precio/nombre no resetean stock |
| Reducir vía REST/editor/revisión o payload editorial | Misma autorización e invariante, o denegación explícita de ruta no soportada |
| Corte propio posterior a Event; pausa/cancelación/cambio de acceso | No extiende corte Event ni habilita compra; tipos/stock preservados |
| Modificar precio/nombre de tipo con historia | Líneas históricas y referencias inalteradas; no delete, refund, restock ni reescritura de snapshots |
| Falla Woo tras persistencia parcial | Error y recarga para revisión, sin éxito falso ni retry ciego |
| Activar tipo/publicar evento/URL producto/add-to-cart/Store API/order-pay | Todas las compras de tickets siguen bloqueadas; frontend mantiene “próximamente” |
| Cambio comercial + contenido protegido/snapshot obsoleto | No sobrescribe descripción ni cambios ajenos; form de tipo separado; acción rechazada si stale |
| Update roles repetido y desactivar/reactivar | Primitivas exactas, sin permisos Woo genéricos; datos preservados, no doble instalación |

QA funcional: reutilizar el sitio LocalWP descartable existente identificado en la sesión (`lcda-e32-qa-20261009`), sin crear otro sitio/harness. Solo se comprobó existencia de su directorio el 2026-10-12; servicio, código instalado, DB y Woo no fueron verificados. Antes de futuras escrituras, confirmar que sigue siendo descartable, registrar versiones/revisión y permisos, y desplegar la revisión T1 solo con autorización del task. No copiar datos de producción ni reactivar el harness frozen.

Con Woo ya disponible o instalación local expresamente autorizada en el futuro task, usar wp-admin/browser y herramientas WordPress/Woo existentes para casos Admin/Programador, IDs ajenos, moneda/cortes y no-compra. Fixtures locales mínimos de historia, si se autorizan, permiten verificar preservación; no pagos/gateway reales. Registrar fuentes y resultados fuera del repo en destino autorizado, sin rutas absolutas de reports en documentos canónicos. HPOS verifica independencia del storage de órdenes con fixtures mediante CRUD; ausencia de órdenes no acredita compatibilidad de un gateway.

QA se entrega como PASS/FAIL/SKIP por escenario con evidencia de checkout/runtime concreto. Si Woo no está disponible, reportar SKIP de QA funcional comercial y no declarar T1 aceptado solo por mocks. No construir runner, endpoints, scripts bootstrap ni infraestructura nuevos para suplir el entorno. Los tests de concurrencia de reservas/overselling, expiración y pagos tardíos pertenecen a T2–T5.

## 10. Readiness y decisiones realmente bloqueantes

El scope de código T1 puede autorizarse sin elegir gateway, sin credenciales, sin impuestos finales y sin aprobar las tablas candidatas T2. No quedan preguntas humanas indispensables para completar esta especificación. Las reglas técnicas propuestas quedan concretas para revisión del task de implementación, sin volver a pedir A9.

Para ejecutar T1 falta autorización de código y, si Woo falta, instalación/activación solo en LocalWP descartable; fijar versiones verificables es requisito de ejecución/QA, no nueva decisión de producto. A11/A12 continúan abiertas y bloquearán fiscalidad/checkout/pagos según fase. El runtime D1 no verificado no bloquea T1 local. Antes de habilitar ventas son obligatorios coordinador atómico probado, revalidación, conciliación financiera y gates de pagos; T1 no acredita ninguno de ellos.
