<?php

namespace App\Http\Controllers\Product\Admin;

use App\CustomClass\ItopCyberUpload;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    $products = Product::orderByDesc('id')->paginate(20);

    return view('product.admin.index', compact('products'));
  }

  /**
   * Show the form for creating a new resource.
   */
  public function create()
  {
    return view('product.admin.create');
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {

    $validated = $request->validate(
      [
        "code" => 'required|string',
        "title" => 'required|string',
        "price_actual" => 'required|string',
        "price" => 'required|string',
        'cover' => 'required|image|max:3072|mimes:jpg,jpeg,png,webp'
      ],
      [
        'code.required' => 'โปรดระบุรหัสสินค้า',
        'title.required' => 'โปรดระบุชื่อสินค้า',
        'price_actual.required' => 'โปรดระบุราคาจริง',
        'price.required' => 'โปรดระบุราคาขาย',
        'cover.required' => 'โปรดแนบรูปภาพหน้าปก',
        'cover.max' => 'ภาพหน้าต้องมีขนาดไม่เกิน 3 MB',
        'cover.mimes' => 'ภาพหน้าปกต้องมีนามสกุล *.jpg, *.jpeg, *.png',
      ]
    );

    $product = Product::create($request->all());
    if ($product) {
      if (isset($request['cover'])) {
        Product::where('id', $product->id)->update([
          'cover' => $this->ctlUpload($_FILES['cover'], $product->id)
        ]);
      }

      if (isset($request['images'])) {
        $response = [];
        foreach ($request['images'] as $value) {
          if ($value != '') {
            $requestFile = [
              'name' => $value->getClientOriginalName(),
              'type' => $value->getClientMimeType(),
              'tmp_name' => $value->getPathName(),
              'error' => $value->getError(),
              'size' => $value->getSize()
            ];
          }

          $requestFile['name_uploaded'] = $this->ctlUploadImages($requestFile, $product->id);
          $response[count($response)] = $requestFile;
        }

        Product::where('id', $product->id)->update([
          'images' => $response
        ]);
      }
    }


    return redirect()->route('admin.products.index');
  }

  /**
   * Display the specified resource.
   */
  public function show(string $id)
  {
    $product = Product::findOrFail($id);

    return view('product.admin.show', compact('product'));
  }

  /**
   * Show the form for editing the specified resource.
   */
  public function edit(string $id)
  {
    $product = Product::findOrFail($id);
    $initialPreview = [];
    $initialPreviewConfig = [];
    if (!empty($product->images)) {
      foreach ($product->images as $index => $value) {
        $initialPreview[$index] = Storage::url('product/' . gen_folder($product->id) . '/' . $value['name_uploaded']);
        $initialPreviewConfig[$index] = [
          'caption' => $value["name"],
          'size' => $value["size"],
          'width' => "120px",
          'key' => $index,
          'downloadUrl' => Storage::url('product/' . gen_folder($product->id) . '/' . $value['name_uploaded'])
        ];
      }
    }
    $product->initialPreview = _jsonUnescapedUnicode($initialPreview);
    $product->initialPreviewConfig = _jsonUnescapedUnicode($initialPreviewConfig);

    // dd($product);

    return view('product.admin.edit', compact('product'));
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, string $id)
  {
    $validated = $request->validate(
      [
        "code" => 'required|string',
        "title" => 'required|string',
        "price_actual" => 'required|string',
        "price" => 'required|string',

        // 'cover' => 'required|image|max:3072|mimes:jpg,jpeg,png,webp'
      ],
      [
        'code.required' => 'โปรดระบุรหัสสินค้า',
        'title.required' => 'โปรดระบุชื่อสินค้า',
        'price_actual.required' => 'โปรดระบุราคาจริง',
        'price.required' => 'โปรดระบุราคาขาย',

        // 'cover.required' => 'โปรดแนบรูปภาพหน้าปก',
        // 'cover.max' => 'ภาพหน้าต้องมีขนาดไม่เกิน 3 MB',
        // 'cover.mimes' => 'ภาพหน้าปกต้องมีนามสกุล *.jpg, *.jpeg, *.png',
      ]
    );

    Product::findOrFail($id)->update($request->all());

    return redirect()->route('admin.products.index');
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(string $id)
  {
    Product::findOrFail($id)->delete();

    return redirect()->route('admin.products.index');
  }

  public function ctlUploadOption()
  {
    return [
      'crop' => [
        'width' => 400,
        'height' => 400,
        'watermark' => ""
      ]
    ];
  }

  public function ctlUpload($file, $id)
  {
    $folder = strtolower('product/' . gen_folder($id));

    $options = $this->ctlUploadOption();

    return ItopCyberUpload::upload(storage_path('app/public/' . $folder), $file, $options);
  }

  /**
   * @param mixed $id
   * @param mixed $file_name
   * @return void
   */
  public function storageDelete($id, $file_name)
  {
    $folder = strtolower('product/' . gen_folder($id));

    Storage::disk('public')->delete("$folder/$file_name");
    Storage::disk('public')->delete("$folder/crop/$file_name");
    Storage::disk('public')->delete("$folder/thumbnail/$file_name");
    Storage::disk('public')->delete("$folder/resize/$file_name");
  }

  public function storageImagesDelete($id, $file_name)
  {
    $folder = strtolower('product/' . gen_folder($id));
    // $folder = strtolower(end($exp)) . '/' . gen_folder($id);

    Storage::disk('public')->delete("$folder/$file_name");
    Storage::disk('public')->delete("$folder/crop/$file_name");
  }


  public function ctlUploadImagesOption()
  {
    return [
      'crop' => [
        'width' => 400,
        'height' => 266,
        // 'watermark' => env('ITOPCY_UPLOAD_WATEMARK', "NAKOMAH STUDIO")
      ]
    ];
  }

  public function ctlUploadImages($file, $id)
  {
    // $exp = explode("\\", $this->classModelName);
    // $folder = strtolower(end($exp)) . '/' . gen_folder($id);

    $folder = strtolower('product/' . gen_folder($id));
    $options = $this->ctlUploadImagesOption();

    return ItopCyberUpload::upload(storage_path('app/public/' . $folder), $file, $options);
  }


  public function imagesSort(Request $request, $id)
  {
    $product = Product::findOrFail($id);
    $images = $product->images;

    $response = [];
    foreach ($request->input("stack") as $index => $value) {
      $response[$index] = $images[$value['key']];
    }

    $product->update([
      'images' => _jsonUnescapedUnicode($response)
    ], $id);

    return response()->json(['success' => 'updated successfully.']);
  }

  public function imagesDestroy(Request $request, $id)
  {
    $index = $request->input("key");
    $product = Product::findOrFail($id);
    $images = $product->images;
    $file_name = $images[$index]["name_uploaded"];

    // $this->repository->storageSliderDelete($id, $images[$index]["name_uploaded"]);
    // $exp = explode("\\", $this->classModelName);
    $folder = 'product/' . gen_folder($id);

    Storage::disk('public')->delete("$folder/$file_name");
    Storage::disk('public')->delete("$folder/crop/$file_name");

    unset($images[$index]);

    $response = array_values($images);
    $product->update([
      'images' => _jsonUnescapedUnicode($response)
    ], $id);

    return response()->json(['success' => 'updated successfully.']);
  }
}
