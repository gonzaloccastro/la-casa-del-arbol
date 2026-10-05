# La Casa del Árbol — instrucciones para agentes

## Autoridad y orientación

Leer este archivo primero. Es la autoridad operativa común para coding y review agents, independientemente de su herramienta. [CLAUDE.md](CLAUDE.md) es el punto de entrada específico de Claude.

Las instrucciones y restricciones explícitas del usuario delimitan cada task y prevalecen sobre estas instrucciones. AGENTS.md gobierna el workflow; los contratos especializados gobiernan las decisiones de su dominio; [project-status](docs/project-status.md) gobierna el roadmap y el estado documental de fases. El roadmap no autoriza a iniciar una fase. Ante conflictos no resueltos, señalar las fuentes y pedir decisión antes de actuar sobre el punto en conflicto.

Repositorio WordPress de La Casa del Árbol:

- `plugins/casa-eventos/`: dominio y lógica de eventos; Event Core y roles implementados.
- `themes/la-casa-del-arbol/`: child theme de Astra, presentación y Gutenberg; Home, Agenda y Single consumen la API pública del plugin.
- `docs/implementation/`: contratos especializados; `docs/design/`: diseño aprobado y referencias.
- `docs/deployment.md`: procedimientos operativos sujetos a autorización específica.

## Fronteras y scope

WordPress administra contenido editorial; el theme presenta; casa-eventos posee datos y reglas de eventos. La integración comercial prevista asigna pedidos, checkout y stock transaccional a WooCommerce y pagos a Payway. Instagram y newsletter conservan las fronteras externas del contrato de ownership. Los detalles pertenecen a los contratos; no duplicarlos aquí.

Inspeccionar la implementación antes de editar. Mantener el scope mínimo y respetar la allowlist del task. Preservar cambios ajenos. No añadir dependencias ni cambiar versiones, arquitectura, ownership o producto silenciosamente. Si hace falta ampliar el alcance o resolver una decisión no aprobada, explicar el motivo y esperar autorización para ese punto.

## Read-only, writer y paralelismo

- Read-only: inspeccionar y reportar sin modificar archivos, índice, branches, working tree, runtime ni servicios. No ejecutar checks que escriban artefactos sin autorización.
- Writer: escribir sólo lo autorizado. Registrar workspace, branch, HEAD y estado inicial; no descartar ni incorporar cambios ajenos a la entrega.
- **Un writer por working tree.** Reviewers pueden inspeccionar el mismo árbol, identificando la revisión concreta y cualquier cambio durante el review.
- Para writers en paralelo, usar branches/worktrees separados con tasks y scopes definidos. Su creación debe estar autorizada; no hacerla cuando el task la prohíba. Coordinar integración y conflictos antes de combinar cambios.

## Gates explícitos

La autorización de edición local no autoriza estas operaciones:

| Operación | Gate |
|---|---|
| Commit | Autorización explícita del task, diff revisado, scope comprobado y validaciones requeridas aprobadas. |
| Push | Autorización explícita, destino/branch identificados y commits a enviar revisados. |
| PR | Autorización explícita para crear/publicar el PR, base/head definidos y paquete de review disponible. |
| Deploy | Autorización explícita de entorno y release; seguir `docs/deployment.md`, checks, confirmaciones y rollback. |
| Producción | Autorización explícita de la acción concreta: también acceso remoto, verificación, wp-admin, activación, contenido, configuración, base de datos, cachés y limpieza. |

No ejecutar operaciones remotas o cambios de Git fuera del alcance autorizado. No eludir prompts, sandbox ni checks. Una receta documental o un estado COMPLETE no constituye permiso.

## Testing, review y evidencia

Revisar el diff completo, incluidos archivos nuevos, y comprobar Git contra la allowlist. Ejecutar los checks requeridos por el task y pertinentes al cambio; informar comando, resultado, fallos y skips. Para documentación, comprobar enlaces, precedencia, consistencia y ausencia de cambios funcionales. Para código, consultar los checks existentes del componente y sus contratos. No declarar éxito de pruebas no ejecutadas.

**Repo remoto != working tree local != runtime local != producción.** `origin/main` local puede estar desactualizado. Un diff o test estático evidencia el checkout inspeccionado; no acredita una instalación activa. El runtime local puede tener código, configuración y datos distintos. Un deploy de archivos no prueba activación ni comportamiento en producción. Identificar fuente, entorno, revisión y fecha de la evidencia cuando corresponda; distinguir observación actual, snapshot histórico e inferencia. No extrapolar entre capas.

## Task contract y handoff

Antes de escribir, definir ID, objetivo, modo read-only/writer, workspace/branch/base, archivos autorizados, restricciones, criterios de aceptación, validaciones y destino de entrega. Registrar incertidumbres y dependencias sin inventar decisiones para completar el task. Detenerse en el gate de review acordado.

Handoff mínimo: objetivo, archivos creados/modificados, resumen, validaciones y resultados, checks no ejecutados, evidencia/base inspeccionada, decisiones pendientes o evitadas por falta de autoridad, riesgos/limitaciones y operaciones realizadas o no realizadas. Entregar diff completo con contenido de archivos untracked, sin staging ni commit sólo para producirlo.

## Reports temporales

Los reports de review son efímeros y se generan fuera del repositorio en el destino autorizado. No agregar sus rutas absolutas a archivos del proyecto ni convertirlos en documentación canónica. No crear reports auxiliares dentro del repo. Si el sandbox exige aprobación para el destino, solicitarla sin relajar el sandbox ni cambiar de ubicación silenciosamente. No incluir credenciales ni configuración sensible.

## Índice de documentación canónica

- [Estado y roadmap](docs/project-status.md).
- [Deployment](docs/deployment.md): procedimientos; notas históricas no acreditan runtime actual.
- [Casa Eventos](docs/implementation/casa-eventos-contract.md): Event Core, API, roles y decisiones E1/E1.1/E2.
- [Ticketing V1](docs/implementation/ticketing-v1-contract.md): contrato funcional; §21 no es el roadmap vigente.
- [Content ownership](docs/implementation/content-ownership.md): fuentes de verdad y fronteras.
- [Event markup](docs/implementation/event-markup-contract.md): rendering y markup compartido.
- [Single Event](docs/implementation/single-event-contract.md), [Agenda](docs/implementation/agenda-contract.md), [Home](docs/implementation/home-contract.md): contratos de superficies.
- [Layout](docs/implementation/layout-contract.md): composición, anchos y espaciado.
- [Theme README](themes/la-casa-del-arbol/README.md): orientación y uso.
- [Design handoff](docs/design/Final%20Design%20Handoff.md): diseño aprobado.

Leer completos los contratos aplicables antes de modificar su dominio. Interpretar registros de etapas anteriores con las secciones posteriores que documentan su implementación o reemplazo. Señalar inconsistencias residuales sin extender el scope del task.
