# Cómo realizar una API

### Inicializar
 + Creamos el proyecro de laravel  
   (todas las opciones predeterminadas)
```bash
    laravel new api_examen
```

 + Creamos la base de datos con los docker  
   (importante modificar el .env con lo que sea necesario)
   docker-compose.yaml
```yaml
#docker-compose.yaml
version: "3.8"
services:
   mysql:
      image: mysql
      container_name: mysql-api-examen
      volumes:
         - ./datos:/var/lib/mysql
         - ./datos.sql:/docker-entrypoint-initdb.d/datos.sql
      ports:
         - ${DB_PORT}:3306
      environment:
         - MYSQL_USER=${DB_USERNAME}
         - MYSQL_PASSWORD=${DB_PASSWORD}
         - MYSQL_DATABASE=${DB_DATABASE}
         - MYSQL_ROOT_PASSWORD=${DB_PASSWORD_ROOT}
   phpmyadmin:
      image: phpmyadmin
      container_name: phpmyadmin-api-examen
      ports:
         - ${DB_PORT_PHPMYADMIN}:80
      depends_on:
         - mysql
      environment:
         PMA_ARBITRARY: 1
         PMA_HOST: mysql
```
   Datos a modificar en el .env
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=23306
DB_DATABASE=instituto
DB_USERNAME=alumno
DB_PASSWORD=alumno
DB_PASSWORD_ROOT=password
DB_PORT_PHPMYADMIN=8080
```
 + Levantamos lo necesario para que funcione
```bash
php artisan serve
docker compose up
```

### Crear el modelo sobre el que vamos a trabajar y poblarlo

 + Crear modelo y recursos necesarios
```bash
php artisan make:model Alumno --api -mf
php artisan make:request AlumnoFormRequest
php artisan make:resource AlumnoResource
php artisan make:resource AlumnoCollection --collection
```
--api: crea Controller específico de api   
-mf: crea Migration y Factory

 + Poblamos la base de datos  
   Editamos la migración en  
   /database/migrations/****_create_alumnos_table.php
```php
public function up(): void
{
   Schema::create('alumnos', function (Blueprint $table) {
      $table->id();
      $table->string("nombre");
      $table->string("email");
      $table->string("direccion");
      $table->timestamps();
   });
}
```
   Editamos la factoría en  
   /database/factories/AlumnoFactory.php
```php
public function definition(): array
{
   return [
      "nombre"=>fake()->name(),
      "direccion"=>fake()->address(),
      "email"=>fake()->email()
   ];
}
```
   Editamos el seeder en  
   /database/seeders/DatabaseSeeder.php
```php
public function run(): void
{
     Alumno::factory(10)->create();
}
```
   Ejecutamos la migración  
   (también el --seed para poblar lo que hayamos incluido en el seeder)
```bash
php artisan migrate --seed
```

### Crear rutas

+ Vamos a crear el recurso  
Añadimos la siguiente línea en   
/routes/api.php
```php
   Route::apiResource("alumnos", AlumnoController::class);
```

   Ahora ya tenemos las rutas, que se pueden mirar con el siguiente comando
```bash
php artisan route:list --name=alumno
```

### Métodos e Implementaciones

#### GET

 + Primero de todo queremos que el JSON devuelto cumpla  el formato API spec.  
   Para ello modificamos el método toArray de  
   /app/http/Resources/AlumnoResource.php
```php
public function toArray(Request $request): array
{
     return [
         "id"=>(string)$this->id,
         "type"=>"Alumnos",
         "attributes"=>[
             "nombre"=>$this->nombre,
             "direccion"=>$this->direccion,
             "email"=>$this->email
         ],
         "link"=> [
             "self"=>url("api/alumnos/" . $this->id)
         ]
     ];
}
```

   Queremos también que se añada al final la versión de la jsonapi.  
   Para ello añadimos la función **with** en  
   /app/http/Resources/AlumnoCollection.php 
   y /app/http/Resources/AlumnoResource.php  
   (En Collection para cuando devuelve todos los datos y en Resource para cuando es únicamente uno)
```php
public function with(Request $request){
     return [
         "jsonapi"=>[
             "version"=>"1.0"
         ]
     ];
}
```

   + Errores  
   Si es error del servidor daremos nosotros la respuesta.  
   Utilizaremos la función **render** de 
   /app/Exceptions/Handler.php
```php
public function render($request, Throwable $e){
     // Errores de base de datos)
     if ($e instanceof QueryException) {
         return response()->json([
             'errors' => [
                 [
                     'status' => '500',
                     'title' => 'Database Error',
                     'detail' => 'Error procesando la respuesta. Inténtelo más tarde.'
                 ]
             ]
         ], 500);
     }
     // Delegar a la implementación predeterminada para otras excepciones no manejadas
     return parent::render($request, $e);
 }
```

   Queremos además validar el header y pedir una cabecera específica para "accept".  
   Para ello implementaremos un middleware.
```bash
php artisan make:middleware HeaderMiddleware
```
   Una vez creado modificamos su método **handle** en  
   /app/Http/Middleware/HeaderMiddleware.php
```php
public function handle(Request $request, Closure $next): Response
 {
     if($request->header("accept") != "application/vnd.api+json"){
         return \response()->json([
             "errors" => [
                 "status"=>"406",
                 "title"=>"Not Acceptable",
                 "detail"=>"Content File not specified"
             ]
         ], 406);
     }
     return $next($request);
 }
```
   Ahora debemos añadirlo el middleware que se ejecuta.  
   Eso se realiza en  
   /app/Http/Kernel.php
```php
'api' => [
   // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
   \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
   \Illuminate\Routing\Middleware\SubstituteBindings::class,
   HeaderMiddleware::class //añadimos esta línea
]
```

 + Index y Show
   Una vez ya tenemos controlados los errores y el formato de los datos implementamos los métodos.  
   Ambos en  
   /app/Http/Controllers/AlumnoController.php
```php
public function index()
{
   $alumnos = Alumno::all();
   return new AlumnoCollection($alumnos);
}

public function show(int $id)
{
   $alumno = Alumno::find($id);
   if(!$alumno)
      return response()->json([
          "errors" => [
              "status"=>"404",
              "title"=>"Resource not found",
              "detail"=>"Alumno $id does not exist/was not found"
          ]
      ],404);
   else
      return new AlumnoResource($alumno);
}
```

#### POST

 + Deberemos validar que los datos cumplen un formato y unos requisitos.  
   Para ello modificamos  
   /app/Http/Requests/AlumnoFormRequest.php
```php
public function authorize(): bool
{
    return true;
}

public function rules(): array
{
   return [
      'data.attributes.nombre' => 'required|string|unique:alumnos,nombre|max:255',
      'data.attributes.email' => 'required|string|email|max:255',
      'data.attributes.direccion' => 'required|string|min:3'
   ];
}
```

   Hay que controlar los errores de validación **ValidationException**  
   Añadiremos código a la función **render** de
   /app/Exceptions/Handler.php  
   (así quedara el código final de este fichero)
```php
 public function render($request, Throwable $e){

     if($e instanceof ValidationException){
         return $this->invalidJson($request,$e);
     }

     // Errores de base de datos)
     if ($e instanceof QueryException) {
         return response()->json([
             'errors' => [
                 [
                     'status' => '500',
                     'title' => 'Database Error',
                     'detail' => 'Error procesando la respuesta. Inténtelo más tarde.'
                 ]
             ]
         ], 500);
     }
     // Delegar a la implementación predeterminada para otras excepciones no manejadas
     return parent::render($request, $e);
 }

 protected function invalidJson($request, ValidationException $exception):JsonResponse
 {
     return response()->json([
         'errors' => collect($exception->errors())->map(function ($message, $field) use
         ($exception) {
             return [
                 'status' => '422',
                 'title' => 'Validation Error',
                 'details' => $message[0],
                 'source' => [
                     'pointer' => '/data/attributes/' . $field
                 ]
             ];
         })->values()
     ], $exception->status);
 }
```

   Añadimos la propiedad **fillable** en el modelo, para ser capaces de asignar los datos con un array  
   /app/Models/Alumno.php
```php
class Alumno extends Model
{
    use HasFactory;

    protected $fillable = ["nombre", "direccion", "email"];
}
```

   Por último debemos modificar el método store en  
   /app/Http/Controllers/AlumnoController.php
```php
public function store(AlumnoFormRequest $request)
{
  $datos = $request->input("data.attributes");
  $alumno = new Alumno($datos);
  $alumno->save();
  return response()->json( new AlumnoResource($alumno),201);
}
```

#### DELETE

 + Eliminas (si existe) el elemento que te indican  
   /app/Http/Controllers/AlumnoController.php
```php
public function destroy(int $id)
{
   $alumno = Alumno::find($id);
   if(!$alumno)
      return response()->json([
          "errors" => [
              "status"=>"404",
              "title"=>"Resource not found",
              "detail"=>"Alumno $id does not exist/was not found"
          ]
      ],404);
   else {
      $alumno->delete();
      return response()->json(null, 204);
   }
}
```

#### PUT Y PATCH

```php
public function update(Request $request, int $id)
{
  $alumno = Alumno::find($id);
  if(!$alumno)
      return response()->json([
          "errors" => [
              "status"=>"404",
              "title"=>"Resource not found",
              "detail"=>"Alumno $id does not exist/was not found"
          ]
      ],404);
  else{
      $verbo = $request->method();//comprobar el verbo
      $rules = [];
      if($verbo == "PUT"){
          $rules = [
              'data.attributes.nombre' => ["required","string","max:255",
                  Rule::unique("alumnos","nombre")->ignore($alumno)],
              'data.attributes.email' => 'required|string|email|max:255',
              'data.attributes.direccion' => 'required|string|min:3'
          ];
      }else{//Patch
          if ($request->has("data.attributes.nombre"))
              $rules["data.attributes.nombre"]= ["required","string","max:255",
                  Rule::unique("alumnos","nombre")->ignore($alumno)];
          if ($request->has("data.attributes.direccion"))
              $rules["data.attributes.direccion"]= 'required|string|min:3';
          if ($request->has("data.attributes.email"))
              $rules["data.attributes.email"]= 'required|string|email|max:255';
      }
      $datos_validos = $request->validate($rules);
      $datos = [];

      if(sizeOf($datos_validos)>0)//comprobamos que no nos hayan pasado 0 datos
          foreach($datos_validos['data']['attributes'] as $campo => $valor)
              $datos[$campo] = $valor;

      $alumno->update($datos);
      return new AlumnoResource($alumno);
  }
}
```
