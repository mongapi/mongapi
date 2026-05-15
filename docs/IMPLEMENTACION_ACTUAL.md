# Implementacion actual del proyecto

Este documento describe como funciona hoy el proyecto despues de la refactorizacion a sesiones con PIN, lesson plans, presencia y resultados persistidos. La idea es que sirva como mapa real del sistema, no como lista historica de cambios.

## 1. Modelo funcional actual

El producto ya no gira alrededor de `classroom`. El flujo real es este:

1. El profesor crea un juego o reutiliza uno existente.
2. Si quiere una secuencia, agrupa varios juegos dentro de un `lesson plan`.
3. Desde un juego o un `lesson plan`, crea una `game_session`.
4. En esa creacion elige un `game_mode`:
   - `shared`: una partida compartida para todos.
   - `table`: una partida agregada por mesa/equipo.
   - `individual`: una partida por alumno.
5. La sesion genera un PIN unico.
6. El alumnado entra por ese PIN.
7. El backend devuelve la fase activa de la sesion y su `game_content`.
8. El dashboard del profesor controla estado, PIN, participantes y resultados.
9. Si la sesion viene de `lesson plan`, el profesor avanza de fase sin expulsar a nadie de la misma sesion.

## 2. Arquitectura actual

### Backend

Repositorio: `proyecto-back`

Stack principal:

- Laravel 12
- Sanctum para autenticacion protegida
- Reverb para eventos realtime
- PostgreSQL en local/Docker y SQLite en tests
- Docker Compose + Nginx

Responsabilidades actuales:

- autenticacion y roles
- CRUD de juegos y tipos de juego
- CRUD de lesson plans
- creacion y control de sesiones
- union publica a sesion por PIN
- presencia de participantes por heartbeat
- evaluacion de respuestas
- persistencia de resultados
- emision de eventos realtime

### Frontend

Repositorio: `proyecto-front`

Stack principal:

- React 19 + Vite
- React Router
- Axios
- motion/react
- Three.js / react-three-fiber / drei

Responsabilidades actuales:

- login y registro
- biblioteca de juegos y lesson plans
- formularios guiados para crear contenido
- creacion de sesiones con selector de modo
- join por PIN
- dashboard del profesor
- runtimes conectados a `game_content`
- envio de presencia y resultados al backend

## 3. Backend: como funciona hoy

### 3.1 Sesiones

Controlador principal: `app/Http/Controllers/API/SessionController.php`

Lo que hace:

- crea sesiones desde `game_id` o `lesson_plan_id`
- resuelve automaticamente la fase inicial cuando la sesion viene de `lesson_plan`
- guarda `game_mode` con los valores `shared`, `table` o `individual`
- serializa el estado actual para front y dashboard
- permite arrancar, pausar, reanudar, finalizar y avanzar de fase

La sesion serializada devuelve, entre otros:

- `id`
- `pin`
- `status`
- `game_mode`
- `game_content`
- `current_phase_index`
- `total_phases`
- `participants_count`
- `active_participants`
- `results_summary`
- `lesson_plan`
- `game`
- `started_at`, `ended_at`, `created_at`, `updated_at`

### 3.2 Modos de sesion

El comportamiento actual es:

- `shared`: cada navegador conserva su propia identidad y su propio resultado. Es util para puestos conectados a una misma actividad compartida.
- `table`: la presencia puede llegar desde varios navegadores, pero el resultado se agrega por nombre de mesa usando `participant_key` comun.
- `individual`: cada navegador/alumno genera su propio resultado independiente.

La clave que usa backend para agrupar resultados es `participant_key`.

- en `table` se deriva del nombre de mesa
- en `shared` e `individual` se deriva del `device_id`

### 3.3 Presencia

La presencia no se basa en canales privados autenticados, sino en heartbeat HTTP publico:

- `POST /api/sessions/{id}/presence`
- `POST /api/sessions/{id}/presence/leave`

Estado actual:

- se guarda en cache con TTL
- cada participante conserva `device_id`, `player_name`, `participant_key` y `last_seen_at`
- el backend emite `SessionPresenceUpdated`
- el dashboard del profesor refleja participantes activos en tiempo real

### 3.4 Respuestas y resultados

Ruta principal:

- `POST /api/sessions/{id}/answers`

El backend ya no solo entiende quiz/timeline. Hoy soporta estos formatos:

- `quiz`: valida contra `game_content.questions[*].correctAnswer`
- `shooting`: valida contra `game_content.questions[*].correct`
- `timeline`: valida contra `game_content.items[*].correct`
- `guess_who`: compara texto contra `game_content.answer`
- `filling_blanks`: compara array ordenado de palabras contra `hiddenWords`
- `memory`: acepta cierre de partida con `memory-complete` y verifica que se hayan resuelto todas las parejas

Persistencia actual:

- se crea o actualiza un `GameResult` por `game_session_id + participant_key`
- se acumulan `score`, `correct_answers`, `incorrect_answers`
- se conserva el mayor `time_seconds` recibido
- se marca `completed` y `completed_at` cuando el runtime informa cierre

Resumen expuesto al front:

- `results_summary` devuelve ranking agregado por participante, mesa o navegador segun `game_mode`

### 3.5 Realtime

Eventos activos:

- `GameSessionStarted`
- `GameStateUpdated`
- `PlayerAnswered`
- `SessionPresenceUpdated`

Lo que ve hoy el dashboard:

- cambios de estado de la sesion
- presencia activa
- respuestas en vivo
- actualizacion del scoreboard usando `results_summary`

### 3.6 Lesson plans

Controlador principal: `app/Http/Controllers/API/LessonPlanController.php`

Estado actual:

- CRUD protegido por auth
- los `lesson plans` usan `game_ids` como secuencia de fases
- cualquier `lesson plan` se puede lanzar como sesion real

### 3.7 Juegos, tipos y seeders

Controladores:

- `GameController`
- `GameTypeController`

Seeders relevantes:

- `UserSeeder`
- `DeviceSeeder`
- `GameTypeSeeder`
- `GameSeeder`

Datos demo actuales:

- `profe@mongame.com / password123`
- `admin@mongame.com / password123`
- juegos demo para quiz, memory, completar, adivina, shooter, timeline y hangman

## 4. Frontend: como funciona hoy

### 4.1 Biblioteca y creacion

Vistas principales:

- `src/views/GameLibraryView.jsx`
- `src/views/GameEditorView.jsx`
- `src/views/LessonPlanEditorView.jsx`
- `src/views/GameChooserView.jsx`

Estado actual:

- biblioteca separada en `Juegos` y `Lesson plans`
- busqueda, filtros, ordenacion y paginacion
- formularios guiados por tipo de juego
- modo JSON como opcion avanzada, no como flujo principal
- creacion de sesion desde juego, lesson plan o biblioteca
- selector visual de `game_mode` reutilizable en todos los flujos

Componente de apoyo:

- `src/components/organisms/SessionModeSelector.jsx`

### 4.2 Join por PIN

Vista principal:

- `src/views/JoinView.jsx`

Comportamiento actual:

- el alumno introduce PIN y su identificador
- la etiqueta cambia segun modo: alumno, mesa o puesto
- se guarda `device_id` en localStorage
- se guarda `player_name` en localStorage
- se redirige al runtime conectado al tipo de juego

### 4.3 Hook compartido de runtime

Archivo:

- `src/games/shared/useSessionGame.js`

Responsabilidades:

- cargar la sesion por `sessionId`
- resolver `game_content`
- validar el contenido antes de jugar
- mantener heartbeat de presencia
- exponer `participant` con `deviceId` y `playerName`

Este hook es la base comun de todos los juegos ya integrados.

### 4.4 Juegos ya integrados con resultados

Runtimes conectados:

- `FastQuiz.jsx`
- `MemoryGame.jsx`
- `CompletarEnunciado.jsx`
- `AdivinaQue3D.jsx`
- `Shooter3D.jsx`
- `OrdenarCronologias.jsx`

Estado actual por juego:

- `FastQuiz`: envia respuesta, tiempo y cierre al contestar o al agotar tiempo
- `MemoryGame`: envia cierre con tiempo cuando se completa todo el tablero
- `CompletarEnunciado`: envia cada comprobacion con las palabras seleccionadas y marca cierre al acertar
- `AdivinaQue3D`: envia cada intento y marca cierre cuando se acierta la respuesta
- `Shooter3D`: envia cada disparo/pregunta con tiempo acumulado y cierra en la ultima pregunta
- `OrdenarCronologias`: envia cada hito respondido y marca cierre cuando se completa la cronologia

### 4.5 Dashboard del profesor

Vista principal:

- `src/views/DashboardView.jsx`

Estado actual:

- lista sesiones recientes si no se entra con `sessionId`
- muestra el PIN inmediatamente al crear una sesion
- enseña modo, fase, estado y participantes activos
- escucha eventos realtime
- mezcla `active_participants` con `results_summary`
- el mapa central cambia el lenguaje segun modo:
  - alumnos
  - mesas
  - puestos
- muestra tiempo y puntuacion por participante agregado

### 4.6 Admin

Vista principal:

- `src/views/admin/AdminDashboardView.jsx`

Estado actual:

- metricas agregadas
- actividad reciente
- bloques de salud basicos
- accesos rapidos todavia en fase placeholder

## 5. Que esta validado

Validaciones ejecutadas sobre el estado actual:

- `pnpm build` del frontend pasando tras integrar modos y resultados
- migracion de `participant_key` aplicada correctamente
- `php artisan test --filter=SessionModesFeatureTest` pasando

La prueba `tests/Feature/SessionModesFeatureTest.php` cubre actualmente:

- separacion por navegador en `shared`
- agregacion por nombre de mesa en `table`
- separacion por alumno en `individual`
- persistencia de `time_seconds`
- generacion de `results_summary`

## 6. Pendiente real

Lo que sigue sin estar cerrado del todo:

- no todos los juegos del repositorio estan conectados al backend; los runtimes legacy o demo siguen fuera del flujo real
- el scoreboard agregado existe a nivel de sesion, pero no hay todavia una vista final especifica por fase o ranking exportable
- no se persisten tiempos por pregunta, solo el tiempo acumulado enviado por cada runtime
- el leave por `sendBeacon` sigue dependiendo del navegador y convive con el cierre normal por API
- el area admin todavia no tiene modulos completos de gestion avanzada

## 7. Resumen operativo

Si alguien del equipo quiere entender hoy como funciona el producto, la idea correcta es esta:

- se crean juegos o lesson plans
- se abre una sesion con PIN y modo
- el alumnado entra por PIN con identidad propia o de mesa
- el runtime reporta presencia y respuestas
- el backend agrega resultados por la clave correcta segun modo
- el dashboard del profesor controla estado, participantes y ranking en vivo

Ese es el flujo real actual del proyecto.
- Ahora ya usa `game_content.items` de la sesion y envia respuestas reales al backend.

## 5. Estado real del flujo profesor

A dia de hoy, el flujo funcional del profesor es:

1. Entrar autenticado como profesor.
2. Ir a biblioteca o crear sesion.
3. Crear o editar un juego.
4. Opcionalmente agrupar varios juegos en un lesson plan.
5. Crear una sesion desde un juego o lesson plan.
6. Abrir el dashboard de esa sesion.
7. Arrancar, pausar, finalizar o avanzar de fase.
8. Ver respuestas llegar en realtime desde el backend.

## 6. Pruebas reales hechas hasta ahora

Se ha comprobado de forma real en local:

- login del profesor por API
- listado de rutas de sesiones
- creacion de sesion real
- arranque de sesion
- envio de respuesta por API
- build del frontend tras cambios principales

Ultimo caso validado manualmente por API:

- juego timeline
- sesion creada correctamente
- sesion arrancada en estado `playing`
- respuesta aceptada con `is_correct=true`

## 7. Lo que sigue faltando de verdad

### 7.1 Admin

Aun no esta definida ni implementada la experiencia real de admin.

Ahora mismo:

- `AdminDashboardView` es placeholder
- el menu apunta a rutas aun no construidas
- falta decidir datos y flujo de negocio del admin

### 7.2 Logout real ya resuelto

Antes solo navegaba a login.
Ahora ya llama a `authAPI.logout()` desde:

- `TeacherNavbar.jsx`
- `AdminNavbar.jsx`

### 7.3 Tests

Sigue faltando base de tests tanto en backend como en frontend.

### 7.4 Documentacion integrada

Este documento cubre el estado funcional, pero aun falta una guia de arranque full-stack mas formal y mantenible.

### 7.5 Tooling

Siguen existiendo algunos avisos menores de Tailwind y configuracion de `jsconfig.json`.
No bloquean el producto, pero conviene limpiarlos.

## 8. Propuesta de siguiente fase

Orden recomendado a partir de aqui:

1. Definir flujo y datos del dashboard admin.
2. Implementar endpoints o agregados necesarios para admin.
3. Construir el dashboard admin con metricas, actividad reciente y accesos rapidos.
4. Hacer una prueba end-to-end completa desde el frontend:
   - profesor crea sesion
   - alumno entra por PIN
   - alumno responde
   - profesor ve cambios en dashboard

## 9. Archivos clave de referencia

Backend:

- `app/Http/Controllers/API/SessionController.php`
- `app/Http/Controllers/API/LessonPlanController.php`
- `routes/api.php`
- `app/Events/GameSessionStarted.php`
- `app/Events/GameStateUpdated.php`
- `app/Events/PlayerAnswered.php`

Frontend:

- `src/api/api.jsx`
- `src/views/GameLibraryView.jsx`
- `src/views/GameEditorView.jsx`
- `src/views/LessonPlanEditorView.jsx`
- `src/views/GameChooserView.jsx`
- `src/views/DashboardView.jsx`
- `src/views/JoinView.jsx`
- `src/games/shared/useSessionGame.js`
- `src/components/gameForms/GameContentForm.jsx`
- `src/games/OrdenarCronologias.jsx`

---

Si este documento se queda corto, el siguiente paso logico es crear una segunda version mas tecnica con diagramas de flujo y contrato de datos por endpoint.
