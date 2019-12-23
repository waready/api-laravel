<?php

namespace App\Http\Controllers;

use App\Helpers\JwtAuth;
use Illuminate\Http\Response;
use App\User;   
use Illuminate\Http\Request;


class UserController extends Controller
{
    public function pruebas(Request $request){
        return "Acccion de pruebas de user-controller";
    }

    public function register(Request $request){
       //recoger datos del usuario por post
        $json = $request->input('json', null);
        $param = json_decode($json);
        $param_array = json_decode($json,true);

    //validacion del json
    if(!empty($param) && !empty($param_array)){

       //limpiar datos de espacion ('')
        $param_array = array_map('trim', $param_array);

       //validar datos
       $validate = \Validator::make( $param_array, [
           'name'       => 'required|alpha',
           'surname'    => 'required|alpha',
           'email'      => 'required|email|unique:users',  //comprobar si el usuario existe
           'password'   => 'required'
       ]);

       if( $validate->fails()){
        //validacion fallida   
            $data = array(
                'status' => 'error',
                'code' => 400,
                'mensaje' => 'el usuario no se creo',
                'errors' => $validate->errors()
            );

       } else {
        //validacion correcta
            //cifrar cotraseña
            $pwd =  hash('sha256', $param->password);
            //dd($param_array['password']);

            //crear usuario
            $user = new User();
            $user->name = $param_array['name'];
            $user->surname = $param_array['surname'];
            $user->email = $param_array['email'];
            $user->password = $pwd;
            $user->role = 'ROLE_USER';
            
            $user->save();



            /*mensaje*/
        $data = array(
            'status' => 'success',
            'code' => 200,
            'mensaje' => 'el usuario se ha creado correctamente',
            'user'=> $user
        );
       }

    } else {
        //error al enviar json
        $data = array(
            'status' => 'error',
            'code' => 400,
            'mensaje' => 'los datos enviados no son correctos'
        );

    }


       return response()->json($data, $data['code']);
    }

    public function login(Request $request){

        $jwtAuth = new \JwtAuth();

        //recibir post 
        $json = $request->input('json', null);
        $param = json_decode($json);
        $param_array = json_decode($json,true);


        //validar datos
        $validate = \Validator::make( $param_array, [
            'email'      => 'required|email',  //comprobar si el usuario existe
            'password'   => 'required'
        ]);
        if( $validate->fails()){
        //validacion fallida   
            $signup = array(
                'status' => 'error',
                'code' => 400,
                'mensaje' => 'el usuario no se pudo logear',
                'errors' => $validate->errors()
            );

        } else {
            //cifrar contraseña
            $pwd =  hash('sha256', $param->password);
            
            //devolver token o datos
            $signup = $jwtAuth->signup($param->email,$pwd);

            if(isset($param->gettoken)){
                $signup = $jwtAuth->signup($param->email,$pwd,true);
            }
        }
        return response()->json($signup,200);        
    }
    public function update(Request $request){
        $token = $request->header('Authorization');
        $jwtAuth = new \JwtAuth();
        $checkToken = $jwtAuth->checkToken($token);

        //recivir datos
        $json = $request->input('json', null);
        $param_array = json_decode($json,true);
        


        if($checkToken && !empty($param_array)){
            

            //usuario identificado
            $user = $jwtAuth->checkToken($token, true);

            //validar datos
            $validate = \Validator::make( $param_array, [
                'name'       => 'required|alpha',
                'surname'    => 'required|alpha',
                'email'      => 'required|email|unique:users,'.$user->sub
            ]);
            //quitar campos que no se pueden tocar

            unset($param_array['id']);
            unset($param_array['role']);
            unset($param_array['password']);
            unset($param_array['created_at']);
            unset($param_array['remenber_token']);

            //actualizar en la base de datos
            $user_update = User::where('id', $user->sub)->update($param_array);
            
            //devolver array  con resultado
            $data = array(
                'status' => 'success',
                'code' => 200,
                'mensaje' => 'el usuario se ha modificados.',
                'user'=> $user,
                'change' => $param_array
            );



        }else {
            $data = array(
                'status' => 'error',
                'code' => 400,
                'mensaje' => 'el usuario no esta identificado.'
            );
        }
        return response()->json($data, $data['code']);  

    }
    public function upload(Request $request){

        //recoger datos de la peticion.Whoops
        $imagen = $request->file('file0');
      
        //validate imagen
        $validate =  \Validator::make( $request->all(),
        [
            'file0' => 'required|image|mimes:jpg,jpeg,png,gif'
        ]);

        //subir imagen de la inf.Whoops

        if(!$imagen || $validate->fails()){
        
            $data = array(
                'status' => 'error',
                'code' => 400,
                'mensaje' => 'error al subir imagen'
            );

        }else{
            $imagen_name = time().$imagen->getClientOriginalName();
            \Storage::disk('users')->put($imagen_name,\File::get($imagen));

            $data = array(
                'code' => 200,
                'status' => 'success',
                'imagen' => $imagen_name
            );
        }
    
        return response()->json($data, $data['code']);
    }

    public function getImagen($filename){
        
        
        $isset = \Storage::disk('users')->exists($filename);

        if($isset){
            $file = \Storage::disk('users')->get($filename);
            return new Response($file, 200);
        } else {    
            $data = array(
                'code' => 404,
                'status' => 'error',
                'message' => 'imagen no existe.'
            );
            return response()->json($data, $data['code']);
        }
        
        // 
    }
    public function detail($id){
        $user = User::find($id);
        if (is_object($user)){
            $data = array(
                'code' => '200',
                'status' => 'success',
                'user' => $user
            );
        }else {
            $data = array(
                'code' => '404',
                'status' => 'error',
                'user' => 'el usuario no existe.'
            );
        }
        return response()->json($data, $data['code']);
    }

}
