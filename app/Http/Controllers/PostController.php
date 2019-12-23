<?php

namespace App\Http\Controllers;

use App\Helpers\JwtAuth;
use App\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PostController extends Controller
{
   public function __construct()
   {
       $this->middleware('api.auth', ['except' 
                =>['index',
                    'show',
                    'getImagen',
                    'getPostsByCategory',
                    'getPostsByUser']]);
   }
   public function index(){
       $posts = Post::all()->load('category');
       
       return response()->json( [
            'code'=>200,
            'status' => 'success',
            'posts' => $posts
       ], 200);

   }
   public function show($id){
        $post = Post::find($id)->load('category');
        if(is_object($post)){
            $data = [
                'code'=>200,
                'status' => 'success',
                'post' => $post
            ];
        }else{
            $data =[
                'code'=>404,
                'status' => 'error',
                'message' => 'la entrada no existe'
            ];
        }
        return response()->json($data, $data['code']);
   }
   public function store(Request $request){
       //recoger datos por post
       $json = $request->input('json',null);
       $params = json_decode($json);
       $params_array = json_decode($json, true);
        if(!empty($params_array)){
            //conseguir usuario identificado
            $user = $this->getIdentity($request);
            //validar los datos
            $validate = \Validator::make($params_array,[
                'title'=> 'required',
                'content' => 'required',
                'category_id' => 'required',
                'image' => 'required'
            ]);
            if($validate->fails()){
                $data = [
                    'code' => 400,
                    'status' => 'error',
                    'message' => 'No se ha guarado el post, faltan datos' 
                ];
            }
            else{
                //guardar el post (articulo)
                $post = new Post();
                $post->user_id = $user->sub;
                $post->category_id = $params->category_id;
                $post->title = $params ->title;
                $post->content = $params->content;
                $post->image = $params->image;
                $post->save();
                $data = [
                    'code' => 200,
                    'status' => 'success',
                    'post' =>  $post
                ];
            }
        }
        else {
            $data = [
                'code' => 400,
                'status' => 'error',
                'message' => 'Envia los datos correctamente' 
            ];
        }
       //devolver la respuesta
        return response()->json($data, $data['code']);
   }
   public function update($id, Request $request){
    //Recoge los datos por post
    $json = $request->input('json',null);
    $params_array = json_decode($json,true);

    $data = array(
        'code' => 400,
        'status' => 'error',
        'message'=>'datos enviados incorrectos'
    );

    if(!empty($params_array)){

        //Validar datos
        $validate = \Validator::make($params_array,[
            'title' => 'required',
            'content' => 'required',
            'category_id' => 'required'
        ]);
        if($validate->fails()){
            $data['errors'] = $validate->errors();
            return response()->json($data, $data['code']);
        }
        //Eliminar lo que no queremos actualizar
            unset($params_array['id']);
            unset($params_array['user_id']);
            unset($params_array['created_at']);
            unset($params_array['user']);

            $user = $this->getIdentity($request);

        //Buscar el registro
        $post = Post::where('id', $id)
                    ->where('user_id', $user->sub)->first();
                    
        if(!empty($post) && is_object($post) ){
            //actualizar el registro
            $post->update($params_array);            
            //devolver respuesta
            $data = array(
                'code' => 200,
                'status' => 'success',
                'post'=> $post,
                'change'=>$params_array
            );
        }
        // $where = [
        //     'id'=>$id,
        //     'user_id' =>$user->sub
        // ];
        //     $post = Post::updateOrCreate($where,$params_array);

   }

    return response()->json($data,$data['code']);
   }
   public function destroy($id, Request $request){
    //consegir usuario identificado

       $user = $this->getIdentity($request);

       //comprobar si existe el registro
        $post = Post::where('id', $id)
                      ->where('user_id', $user->sub)->first();
        if(!empty($post)){
            //borrarlo
            $post->delete();
            //devlver algo
            $data =[
                'code'=>200,
                'status' => 'success',
                'post' => $post
            ];
        }else{
            $data =[
                'code'=>404,
                'status' => 'error',
                'message' => 'el post no existe'
            ];

        }

       return response()->json($data, $data['code']);
   }
   private function getIdentity( $request ){
        $jwtAuth = new JwtAuth();
        $token = $request->header('Authorization', null);
        $user = $jwtAuth->checkToken($token,true);
    return $user;
   }
   
   public function upload(Request $request){
       //recoger la imagen de la peticion del archibo subido
        $image = $request->file('file0', null);

       //validar la imagen
        $validate = \Validator::make($request->all(),[
            'file0'=>'required|image|mimes:jpg,jpeg,png,gif'
        ]);
        //guardar la imagen en el disco
            if(!$image || $validate->fails()){
                $data =[
                    'code' => 400,
                    'status' => 'error',
                    'message' => 'error al subir la imagen'
                ];

            }else{
                $image_name = time().$image->getClientOriginalName();
                \Storage::disk('imagenes')->put($image_name, \File::get($image));
                $data = [
                    'code'=>200,
                    'status'=>'success',
                    'image'=>$image_name
                ]; 
            }
       //devolver datos
       return response()->json($data, $data['code']);
   }
   public function getImagen($filename){
       //comprobar si existe el fichero
       $isset = \Storage::disk('imagenes')->exists($filename);
    if($isset){
        //conseguir la imagen
        $file = \Storage::disk('imagenes')->get($filename);

       //devolver la imagen
       return new Response($file, 200);
    } 
    else {
        //mostrar error
        $data = [
            'code'=>400,
            'status'=>'error',
            'message'=>'la imagen no existe'
        ];
    }
    return response()->json($data,$data['code']);

   }
   public function getPostsByCategory($id){
    $posts = Post::where('category_id',$id)->get();
    return response()->json([
        'status'=>'success',
        'posts'=>$posts
    ],200);
   }
   public function getPostsByUser($id){
    $posts = Post::where('user_id',$id)->get();
    return response()->json([
        'status'=>'success',
        'posts'=>$posts
    ],200);
   }

}
