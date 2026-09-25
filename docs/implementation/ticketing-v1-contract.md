# Casa Eventos --- Ticketing V1 Contract

**Proyecto:** La Casa del Árbol\
**Componente:** `casa-eventos`\
**Estado:** Contrato funcional V1\
**Fecha:** 2026-09-25

## 1. Propósito

`casa-eventos` será la capa de negocio para administrar eventos y venta
de entradas de La Casa del Árbol sobre WordPress y WooCommerce.

El objetivo de V1 es cubrir de forma simple y robusta el circuito:

**crear evento → configurar entradas → publicar → vender → cobrar →
controlar cupo → consultar compradores → exportar listado → comunicar al
comprador**

V1 prioriza operación confiable y bajo costo de mantenimiento. No busca
construir una plataforma de ticketing genérica ni una interfaz
administrativa sofisticada.

## 2. Principios de arquitectura

### 2.1 Evento como única fuente de verdad

La entidad Evento administrada por `casa-eventos` será la única fuente
de verdad para los datos del evento.

Home, Agenda, Single Event y futuros módulos relacionados consumirán esa
misma información. Los editores no deberán duplicar título, fecha,
poster, precio, disponibilidad u otros datos del evento en páginas
diferentes.

### 2.2 Separación de responsabilidades

-   **WordPress:** CMS y administración.
-   **Theme `la-casa-del-arbol`:** presentación.
-   **`casa-eventos`:** lógica de eventos, cupos, entradas, checkout de
    tickets e integración funcional.
-   **WooCommerce:** pedidos, checkout, clientes, stock transaccional y
    base de la operación comercial.
-   **Payway:** procesamiento de pagos.
-   **Proveedor de newsletter:** externo y todavía no definido.
-   **Instagram:** integración mediante plugin externo; no pertenece a
    `casa-eventos`.

La lógica de negocio de eventos no debe residir en el theme.

## 3. Entidad Evento

Cada Evento deberá contemplar como mínimo:

### Identidad

-   ID interno de WordPress.
-   Identificador público único estable (UUID/hash) preparado para usos
    futuros.
-   El identificador público no implica QR ni ticket individual en V1.

### Contenido

-   Título.
-   Descripción.
-   Poster/imagen.
-   Categoría.
-   Fecha y hora de inicio.
-   Fecha y hora de finalización opcional.

### Publicación

-   Estado.
-   Visible en Agenda.
-   Destacado en Home.

### Modalidad de acceso

V1 debe contemplar al menos:

-   Venta de entradas.
-   Reserva por WhatsApp.

La modalidad determina el CTA del frontend.

### Aforo

Cada evento tiene un **aforo global**.

-   Valor configurable por evento.
-   Default: **100 personas** cuando el administrador no especifica otro
    valor.
-   Las ventas confirmadas y las reservas temporales de checkout no
    pueden superar el aforo global.
-   El aforo global prevalece aunque la suma de stocks configurados en
    los tipos de entrada sea mayor.

### Período de venta

Debe poder definirse un horario de corte de venta.

-   Si el administrador define uno, se utiliza ese valor.
-   Si no se define, el cierre por defecto es **1 hora antes del inicio
    del evento**.

Una vez alcanzado el corte no se permiten nuevas compras aunque exista
stock.

## 4. Estados de Evento

La V1 deberá contemplar conceptualmente:

-   Borrador.
-   Activo.
-   Pausado.
-   Finalizado.
-   Cancelado.

Un evento cancelado:

-   deja de admitir nuevas compras inmediatamente;
-   debe reflejar su estado en frontend;
-   puede disparar la comunicación de cancelación a compradores;
-   **no ejecuta reembolsos automáticos**.

Los administradores gestionan manualmente los reembolsos
correspondientes.

## 5. Tipos de entrada

Un evento puede tener múltiples tipos/tandas de entrada.

Ejemplos:

-   Anticipada 1.
-   Anticipada 2.
-   General.

Cada tipo debe poder definir:

-   Nombre.
-   Precio.
-   Stock/cupo propio.
-   Estado activo/inactivo.
-   Fecha/hora límite de venta opcional.
-   Máximo por compra.

### Activación

Las tandas no cambian automáticamente en V1.

El administrador decide manualmente qué tipos están activos o inactivos.

Por ejemplo, puede desactivar Anticipada 1 y activar Anticipada 2 desde
el panel del evento.

### Máximo por compra

Default V1:

**10 entradas por operación.**

El sistema debe impedir superar el máximo permitido.

### Condiciones para que un tipo sea comprable

Deben cumplirse simultáneamente:

1.  Evento activo y vendible.
2.  Venta general del evento todavía abierta.
3.  Tipo de entrada activo.
4.  Tipo dentro de su período de venta, si tiene límite propio.
5.  Stock del tipo disponible.
6.  Aforo global disponible.
7.  Cantidad solicitada dentro del máximo permitido.

## 6. Modelo WooCommerce

La arquitectura prevista para V1 es:

**1 Evento → 1 producto WooCommerce interno/oculto → tipos de entrada
representados transaccionalmente mediante variaciones o estructura Woo
equivalente.**

El producto WooCommerce no forma parte del catálogo normal de la futura
Tienda.

El administrador de La Casa no debería necesitar operar habitualmente
sobre esos productos Woo directamente. `casa-eventos` debe presentar una
capa administrativa centrada en Eventos.

La implementación concreta deberá respetar WooCommerce CRUD y ser
compatible con HPOS.

## 7. Checkout de entradas

### 7.1 Compra inmediata

Las entradas **no utilizan el carrito persistente convencional**
destinado a productos físicos.

Flujo:

**Evento → elegir tipo/cantidad → Comprar → checkout directo**

No debe existir el caso operativo de agregar una entrada al carrito,
abandonar el sitio y pagarla mucho tiempo después.

El carrito convencional queda reservado para productos físicos de la
futura Tienda.

En V1 no se requiere una compra mixta de entradas + merchandising.

### 7.2 Reserva temporal

Al iniciar el checkout de entradas se crea una reserva temporal del cupo
solicitado.

Duración:

**20 minutos.**

Durante esos 20 minutos las unidades reservadas cuentan contra:

-   disponibilidad del tipo de entrada;
-   aforo global.

Ejemplo:

-   aforo: 100;
-   ventas confirmadas: 70;
-   reservas de checkout activas: 8;
-   disponibilidad efectiva: 22.

### 7.3 Expiración

Si el comprador no completa la operación dentro de los 20 minutos:

-   la sesión expira;
-   las unidades reservadas se liberan;
-   el checkout anterior deja de ser utilizable;
-   el usuario debe regresar al evento y verificar nuevamente
    disponibilidad.

La expiración no debe permitir vender por encima del aforo bajo
concurrencia.

### 7.4 Reintentos

Si Payway rechaza un intento:

-   el usuario puede volver a intentar el pago mientras la reserva
    original siga vigente;
-   un rechazo **no reinicia los 20 minutos**.

Ejemplo: una sesión iniciada a las 18:00 continúa venciendo a las 18:20
aunque haya intentos rechazados a las 18:08 y 18:15.

### 7.5 Pago pendiente

Una operación que ya fue enviada válidamente al procesador y se
encuentra pendiente de resolución no debe liberar el cupo simplemente
porque se alcanzó el límite original del checkout.

La implementación deberá distinguir entre una sesión de checkout
abandonada y una operación de pago efectivamente pendiente.

## 8. Estados conceptuales de compra

La implementación técnica podrá mapearlos a estados WooCommerce
apropiados, pero deberá poder representar conceptualmente:

-   Checkout activo.
-   Checkout expirado.
-   Pago pendiente.
-   Pago rechazado/fallido.
-   Pagada.
-   Cancelada.
-   Reembolsada.

Las transiciones deben ser idempotentes cuando intervengan
callbacks/webhooks del procesador de pagos.

## 9. Datos del comprador

V1 utiliza **guest checkout**. No se requiere crear una cuenta.

Datos obligatorios:

-   Nombre.
-   Apellido.
-   Email.
-   Teléfono.

Debe existir además un consentimiento separado:

`Quiero recibir novedades de La Casa`

Comprar una entrada **no equivale** a suscribirse al newsletter.

La integración final de newsletter podrá utilizar Mailchimp, Brevo u
otro proveedor y se definirá por separado.

## 10. Confirmación post-compra

Una vez confirmado el pago, el comprador recibe un email transaccional
de La Casa del Árbol.

Debe incluir:

-   logo oficial;
-   membrete/identidad visual del espacio;
-   información de contacto;
-   nombre del evento;
-   fecha y hora;
-   lugar;
-   tipo de entrada;
-   cantidad;
-   total pagado;
-   número/referencia de compra.

V1 no genera QR ni PDF de tickets individuales.

## 11. Recordatorio automático

Los compradores de un evento pagado reciben automáticamente un email de
recordatorio:

**24 horas antes del inicio del evento.**

El envío deberá basarse en pedidos válidos/pagados y evitar duplicados.

La implementación puede utilizar Action Scheduler u otro mecanismo
adecuado dentro del ecosistema WordPress/WooCommerce.

## 12. Reembolsos

V1 permite:

-   **reembolso total de una compra**.

V1 no contempla:

-   reembolso parcial de una compra;
-   devolución de una parte de las unidades compradas.

Cuando una compra se reembolsa completamente:

-   sus unidades dejan de contar como vendidas;
-   se devuelve disponibilidad al tipo de entrada correspondiente;
-   se devuelve disponibilidad al aforo global.

Si el evento todavía admite ventas, esas unidades pueden volver a
ofrecerse.

Si la venta ya está cerrada, las unidades se liberan internamente pero
no vuelven a mostrarse como comprables.

## 13. Cancelación de eventos

Cancelar un evento:

1.  cambia el evento a estado Cancelado;
2.  bloquea inmediatamente nuevas ventas;
3.  refleja el estado en frontend;
4.  permite comunicar la cancelación a los compradores.

La cancelación **no dispara automáticamente reembolsos**.

Los administradores de La Casa procesan manualmente los reembolsos de
las operaciones correspondientes.

## 14. Administración V1

El backend debe ser funcional y deliberadamente básico.

No se requiere una SPA ni un dashboard sofisticado.

### Resumen

Debe existir un panel administrativo sencillo que permita visualizar,
como mínimo:

-   eventos activos/próximos;
-   eventos pasados;
-   entradas vendidas recientemente;
-   recaudación reciente.

Las métricas son secundarias frente al funcionamiento correcto del
ticketing. V1 no requiere analytics avanzados ni gráficos complejos.

### Listado de eventos

Debe ser posible distinguir al menos:

-   activos/próximos;
-   pasados;
-   pausados;
-   cancelados.

### Operación del evento

Desde la administración del evento debe poder consultarse de forma
práctica:

-   fecha;
-   estado;
-   aforo;
-   entradas vendidas;
-   disponibilidad;
-   tipos de entrada;
-   precio;
-   stock por tipo;
-   activación/inactivación de tipos;
-   compradores;
-   recaudación básica del evento;
-   exportación para puerta.

El objetivo es evitar que el equipo tenga que administrar manualmente
productos y variaciones WooCommerce para la operatoria cotidiana.

## 15. Exportación para puerta

Cada evento debe ofrecer:

**Exportar listado (.xlsx)**

No CSV.

El archivo está destinado a la persona que controla el ingreso al
evento.

Debe incluir como mínimo:

-   Nombre y apellido.
-   Email.
-   Teléfono.
-   Tipo de entrada.
-   Cantidad.
-   Número de pedido.
-   Estado de pago.
-   Fecha de compra.
-   Columna/espacio para marcar manualmente ingresos.

Una compra de varias entradas puede ocupar una única fila con su
cantidad correspondiente.

V1 no persiste acreditaciones ni marcas de ingreso en WordPress.

## 16. Tickets y QR

V1 **no utiliza QR**.

V1 tampoco requiere una entidad Ticket individual por cada unidad
comprada.

Ejemplo:

-   Pedido #5842.
-   Anticipada 1.
-   Cantidad: 3.

Esto representa tres accesos.

La arquitectura debe permitir que una versión posterior pueda generar
tickets individuales con identificadores/QR sin tener que rediseñar
Evento, pedido o tipo de entrada.

El UUID/hash previsto en V1 no debe presentarse como QR ni utilizarse
como sistema de acreditación todavía.

## 17. Disponibilidad

La disponibilidad efectiva de un evento debe considerar:

**aforo global - ventas confirmadas - reservas temporales activas**

y además respetar el stock individual del tipo solicitado.

Nunca se debe confiar únicamente en lo que muestra el navegador. La
disponibilidad debe revalidarse en servidor al crear la reserva y
nuevamente en los puntos críticos de la operación.

La implementación debe contemplar concurrencia para evitar overselling.

## 18. Integración con frontend

El theme conserva la presentación.

`casa-eventos` suministrará posteriormente datos a:

### Home

`Eventos destacados` será una consulta/proyección de Eventos:

-   publicados;
-   marcados como destacados;
-   actuales/próximos;
-   ordenados por cercanía;
-   eventos pasados desaparecen automáticamente.

### Agenda

Agenda consume la misma fuente de Eventos.

No es un segundo repositorio editorial.

### Single Event

La página individual mostrará la información del Evento y su CTA según
modalidad:

-   Venta de entradas → `COMPRAR`.
-   Reserva por WhatsApp → `RESERVAR`.

El markup debe respetar los contratos de presentación del theme.

## 19. Integraciones externas fuera de casa-eventos

### Instagram

La sección de Instagram utilizará previsiblemente un plugin maduro como
Smash Balloon o equivalente.

No se desarrollará una integración propia con la API de Instagram.

### Newsletter

La suscripción se integrará posteriormente con un proveedor externo,
probablemente Mailchimp o Brevo.

El proveedor todavía no está definido.

El sistema de ticketing sólo debe preservar correctamente el
consentimiento explícito necesario para esa integración.

## 20. Fuera de alcance de V1

Quedan expresamente fuera:

-   QR.
-   Check-in digital.
-   Ticket individual por asistente.
-   App/interfaz de acreditación.
-   Persistencia de ingresos.
-   Reembolsos parciales.
-   Automatización del paso entre tandas.
-   Asientos numerados.
-   Mapas de ubicaciones.
-   Transferencia de entradas entre personas.
-   Cuentas obligatorias.
-   Carrito persistente para entradas.
-   Compra mixta entrada + merchandising.
-   CRM propio.
-   Newsletter propio.
-   Integración propia con Instagram.
-   Dashboard avanzado.
-   Analytics sofisticados.
-   Gráficos administrativos complejos.

## 21. Prioridad de implementación

Orden recomendado:

1.  Entidad Evento y estados.
2.  Tipos de entrada y aforo.
3.  Integración Evento ↔ WooCommerce.
4.  Disponibilidad y reservas temporales.
5.  Checkout directo de entradas.
6.  Payway y estados de pago.
7.  Confirmación post-compra.
8.  Recordatorio automático.
9.  Administración operativa básica.
10. Listado de compradores y exportación XLSX.
11. Cancelación y reembolsos totales.
12. Integración del frontend real con Home, Agenda y Single Event.
13. QA end-to-end.

## 22. Criterio de éxito V1

Ticketing V1 está completo cuando un administrador puede:

1.  crear un evento;
2.  definir aforo, fecha y cierre de venta;
3.  crear y activar tipos de entrada;
4.  publicarlo;
5.  recibir una compra real mediante Payway;
6.  evitar overselling incluso con checkouts simultáneos;
7.  ver la operación en WooCommerce/Eventos;
8.  consultar compradores;
9.  exportar el XLSX para puerta;
10. enviar automáticamente confirmación y recordatorio;
11. pausar/cancelar la venta;
12. procesar un reembolso total que devuelva correctamente el cupo.

Todo esto debe funcionar sin QR, sin check-in digital y sin exigir que
el equipo comprenda la implementación interna de WooCommerce.
