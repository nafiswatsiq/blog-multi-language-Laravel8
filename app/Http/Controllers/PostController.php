<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Post;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $posts = Post::all();
        return view('posts.index', ['posts'=> $posts]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        $categories = Category::all();
        $tags = Tag::all();
        return view('posts.create', compact('categories', 'tags'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "title_id"  => "required", //|unique:posts,title
            "title_en"  => "required", //|unique:posts,title
            "cover"     => "required",
            "desc_id"   => "required",
            "desc_en"   => "required",
            "category"  => "required",
            "tags"      => "array|required", 
            "keywords"  => "required",
            "meta_desc" => "required",
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $post               = new Post();
        
        $cover              = $request->file('cover');
        $new_image = Image::make($cover->getRealPath());
        if($new_image != null){
            $image_width= $new_image->width();
            $image_height= $new_image->height();
            // dd($image_height, $image_width);
            if($image_height > $image_width){
                $new_height= 1024;
                $new_width= 768;
            }elseif($image_height < $image_width){
                $new_height= 768;
                $new_width= 1024;
            }
            
            $new_image->resize($new_width, $new_height, function    ($constraint) {
                $constraint->aspectRatio();
            });
            
            $imageName = time().'.'.$cover->extension();
    
            $store_image = $new_image->save(public_path('storage/images/blog/' .$imageName));
            // dd($new_image);
            $post->cover    = 'images/blog/'.$imageName;
        }
        

        // if($cover){
        //     $cover_path     = $cover->store('images/blog', 'public');
        //     $post->cover    = $cover_path;
        // }
        // $post->title        = $request->title;
        $post->slug         = Str::slug($request->title_id);
        $post->user_id      = Auth::user()->id;
        $post->category_id  = $request->category;
        // $post->desc         = $request->desc;
        $post->keywords     = $request->keywords;
        $post->meta_desc    = $request->meta_desc;
        $post->online = true;
        $post->save();

        foreach (['id'] as $locale) {
            $post->translateOrNew($locale)->title = "$request->title_id";

            $content = $request->desc_id;
            $dom = new \DomDocument();
            $dom->loadHtml($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $imageFile = $dom->getElementsByTagName('imageFile');
            foreach($imageFile as $item => $image){
                $data = $image->getAttribute('src');
                list($type, $data) = explode(';', $data);
                list(, $data)      = explode(',', $data);
                $imgeData = base64_decode($data);
                $image_name= "/upload/" . time().$item.'.png';
                $path = public_path() . $image_name;
                file_put_contents($path, $imgeData);
                
                $image->removeAttribute('src');
                $image->setAttribute('src', $image_name);
            }
            $content = $dom->saveHTML();

            $post->translateOrNew($locale)->desc = "$content";
            // $post->translateOrNew($locale)->desc = "$request->desc_id";
        }
        foreach (['en'] as $locale) {
            $post->translateOrNew($locale)->title = "$request->title_en";

            $content = $request->desc_en;
            $dom = new \DomDocument();
            $dom->loadHtml($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $imageFile = $dom->getElementsByTagName('imageFile');
            foreach($imageFile as $item => $image){
                $data = $image->getAttribute('src');
                list($type, $data) = explode(';', $data);
                list(, $data)      = explode(',', $data);
                $imgeData = base64_decode($data);
                $image_name= "/upload/" . time().$item.'.png';
                $path = public_path() . $image_name;
                file_put_contents($path, $imgeData);
                
                $image->removeAttribute('src');
                $image->setAttribute('src', $image_name);
            }
            $content = $dom->saveHTML();

            $post->translateOrNew($locale)->desc = "$content";
            // $post->translateOrNew($locale)->desc = "$request->desc_en";
        }
        $post->save();

        $post->tags()->attach($request->tags);

        return redirect()->route('posts.index')->with('success', 'Data added successfully'); 
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        $post = Post::findOrfail($id);
        $categories = Category::all();
        $tags = Tag::all();
        return view('posts.edit', compact('post', 'categories', 'tags'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            "title"     => "required".$id,
            "desc"      => "required",
            "category"  => "required",
            "tags"      => "array|required",  
            "keywords"  => "required",
            "meta_desc" => "required",
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $post = Post::findOrFail($id);

        $new_cover = $request->file('cover');

        if($new_cover){
            if($post->cover && file_exists(storage_path('app/public/' . $post->cover))){
                \Storage::delete('public/'. $post->cover);
            }

            $new_cover_path = $new_cover->store('images/blog', 'public');

            $post->cover = $new_cover_path;
        }

        $post->title        = $request->title;
        $post->slug         = $request->slug;
        $post->user_id      = Auth::user()->id;
        $post->category_id  = $request->category;
        $post->desc         = $request->desc;
        $post->keywords     = $request->keywords;
        $post->meta_desc    = $request->meta_desc;
        $post->save();

        $post->tags()->sync($request->tags);

        return redirect()->route('posts.index')->with('success', 'Data updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);

        $post->delete();

        return redirect()->route('posts.index')->with('success', 'Data moved to trash');
    }

    public function trash(){
        $posts = Post::onlyTrashed()->get();

        return view('posts.trash', compact('posts'));
    }

    public function restore($id) {
        $post = Post::withTrashed()->findOrFail($id);

        if ($post->trashed()) {
            $post->restore();
            return redirect()->back()->with('success','Data successfully restored');
        }else {
            return redirect()->back()->with('error','Data is not in trash');
        }
    }

    public function deletePermanent($id){
        
        $post = Post::withTrashed()->findOrFail($id);

        if (!$post->trashed()) {
        
            return redirect()->back()->with('error','Data is not in trash');
        
        }else {
        
            $post->tags()->detach();
            

            if($post->cover && file_exists(storage_path('app/public/' . $post->cover))){
                \Storage::delete('public/'. $post->cover);
            }

        $post->forceDelete();

        return redirect()->back()->with('success', 'Data deleted successfully');
        }
    }
}
