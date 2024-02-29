<?php /** @noinspection DuplicatedCode */

namespace App\Http\Controllers;

use App\Http\Requests\AlumnoFormRequest;
use App\Http\Resources\AlumnoCollection;
use App\Http\Resources\AlumnoResource;
use App\Models\Alumno;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlumnoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $alumnos = Alumno::all();
        return new AlumnoCollection($alumnos);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AlumnoFormRequest $request)
    {
        $datos = $request->input("data.attributes");
        $alumno = new Alumno($datos);
        $alumno->save();
        return response()->json(new AlumnoResource($alumno),201);
    }

    /**
     * Display the specified resource.
     */
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

    /**
     * Update the specified resource in storage.
     */
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

    /**
     * Remove the specified resource from storage.
     */
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
}
