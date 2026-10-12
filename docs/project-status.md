# Estado del proyecto y roadmap

Estado documental actualizado el 2026-10-12 tras la aceptación e integración local de E3.2. Esta es la referencia del roadmap vigente; los contratos especializados conservan autoridad sobre sus decisiones funcionales y arquitectónicas.

## Fases

| Fase | Estado |
|---|---|
| E0 | COMPLETE |
| E1 | COMPLETE |
| E1.1 | COMPLETE |
| E2 | COMPLETE |
| D1 | COMPLETE |
| M0 | IN PROGRESS |
| E3.1 | IMPLEMENTED / LOCAL: adaptador de escritura de eventos |
| E3.2 | ACCEPTED / LOCALLY INTEGRATED; deployment no acreditado |
| T0 | NEXT: arquitectura de ticketing y pagos; requiere task propio |
| T1–T9 | POSTERIORES: T1, T2, T3, T4, T5, T6, T7, T8, T9 |
| V1-R | POSTERIOR |
| V1-D | POSTERIOR |
| V1-Q | POSTERIOR |
| V1 | POSTERIOR |

Secuencia vigente: E0 → E1 → E1.1 → E2 → D1 → M0 → E3 → T0–T9 → V1-R → V1-D → V1-Q → V1.

M0 prepara el workflow multiagente: autoridad operativa común, precedencia documental, scopes controlados, separación de writers y reviewers, gates explícitos y entregas con evidencia revisable. Su cierre global no se infiere de la integración de E3.2; el estado IN PROGRESS se conserva hasta una decisión explícita.

## E3.2: aceptación e integración local

- Aceptación informada por el usuario en el task POST-E3.2-T0-READINESS; integración verificada en el checkout local el 2026-10-12.
- Workspace: `C:\Projects\la-casa-del-arbol`; branch `main`; commit de integración `ed98b97e529547912851f88afc5762b037c79853`, con padres `e0d55564011f4ffb5b21cbd9b4e209101ae6e0dc` (E3.1) y `8d02cca46d0d59c3086b289952ab7f0ddb9016e3` (E3.2). Árbol limpio al iniciar la inspección.
- UI operacional server-rendered del Programador, propiedad de casa-eventos, sobre el adaptador E3.1; controles de capacidades, nonce, estado obsoleto y confirmación de acciones. Detalles y limitaciones en [Casa Eventos](implementation/casa-eventos-contract.md#16-operational-event-management-e31--e32).
- No se declara deployment de E3.2, actualización del remoto ni runtime actual verificado. La versión del plugin sigue siendo 0.2.0: el número de versión por sí solo no distingue el código E2 del checkout post-E3.2.

Las fases posteriores requieren sus propios tasks, alcance, criterios y autorización. Esta secuencia no agrega decisiones funcionales ni define aquí el contenido de fases sin task aprobado. §21 de [Ticketing V1](implementation/ticketing-v1-contract.md) registra la prioridad original, no el roadmap vigente.

## Baseline conocido al cierre de D1

| Dato | Snapshot |
|---|---|
| Fecha del baseline | 2026-10-02 |
| Plugin casa-eventos | 0.2.0 |
| Theme la-casa-del-arbol | 0.7.0 |
| Commit | `ba82aa3` |

El baseline es un **snapshot histórico** proporcionado al cierre de D1 y debe verificarse antes de operaciones futuras: revisión y estado del checkout, referencias remotas cuando se autorice consultarlas y evidencia del entorno concreto. No acredita el estado actual de runtime local ni producción.

Los contratos de E2 y [deployment](deployment.md) contienen registros previos al cierre de D1, incluidos pendientes de release de ese momento. D1 COMPLETE registra el cierre informado; no convierte esas notas en verificaciones actuales ni autoriza repetir procedimientos. Para operar, aplicar [AGENTS.md](../AGENTS.md) y el task autorizado.

La inspección del 2026-10-12 acredita la implementación local de E2 y la presencia de los scripts de deployment; no se encontraron logs de ejecución de D1 versionados que permitan acreditar por separado archivos desplegados, activación, cleanup, caché y smoke checks. El baseline informado es plugin 0.2.0 / theme 0.7.0 / `ba82aa3`, no una nueva observación de producción. Esta falta de evidencia no bloquea el diseño documental T0; debe resolverse con autorización específica antes de usar producción como baseline de una release comercial.
