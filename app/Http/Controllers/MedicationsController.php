<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\User;
use App\City;
use App\Image;
use App\UserRole;
use App\Keyword;
use App\Product;
use App\ProductKeyword;
use App\Pharmacy;
use App\ProductPharmacy;
use App\PharmacyRating;
use App\Order;
use App\Favourite;

class MedicationsController extends Controller
{
    public function mobileShowAll(Request $request){
        $isFailed = false;
        $data = [];
        $errors =  [];

        $api_token = $request -> api_token;
        $user = null;
        $user = User::where('api_token', $api_token)->first();

        $keywords_response = [];
        $products_response = [];

        if ($user == null){
            $isFailed = true;
            $errors += [
                'auth' => 'authentication failed'
            ];
        }
        else{
            $city_id = $user -> city_id;
            $skip = $request -> skip;
            $limit = $request -> limit;
            $pharmacies = User::where('city_id', $city_id)->where('role_id', '2')->get();

            if($pharmacies != []){
                foreach($pharmacies as $pharmacy){
                    $pharma_id = $pharmacy -> id;
                    $pharma = Pharmacy::where('user_id', $pharma_id)->first();
                    if($pharma != null){
                        $pharma_id =  $pharma -> id;
                        $products_pharmacy = ProductPharmacy::where('pharmacy_id', $pharma_id)
                            ->orderBy('id', 'asc')
                            ->skip($skip)
                            ->take($limit)
                            ->get();

                        if($products_pharmacy != []){
                            foreach($products_pharmacy as $pro){
                                $product_id = $pro -> product_id;

                                $product = Product::find($product_id);
                                $image_id = $product -> image_id;
                                $image = Image::where('id', $image_id)->first();

                                if($image != null){
                                    $image_path = Storage::url($image -> path . '.' .$image -> extension);
                                    $image_url = asset($image_path);
                                }
                                else{
                                    $image_url = asset(Storage::url('default/product.png'));
                                }
                                // check if the user have the product in favourites
                                $favourite = Favourite::where('user_id', $user -> id)->where('product_id', $product -> id)->first();
                                $isFav = false;
                                if($favourite != null){
                                    $isFav = true;
                                }
                                $keywords = ProductKeyword::where('product_id' , $product_id)->get();
                                $keywords_ids = [];
                                if($keywords -> isNotEmpty()){
                                    foreach($keywords as $keyword){
                                        $keyword_id = $keyword -> keyword_id;
                                        $keywords_ids[] = $keyword_id;
                                    }
                                }
                                $final_product = [
                                    'id' => $product -> id,
                                    'name' => $product -> name,
                                    'image' => $image_url,
                                    'price' => $product -> price,
                                    'is_favourite' => $isFav,
                                    'keywords' => $keywords_ids,
                                ];
                                if(in_array($final_product, $products_response)){
                                    continue;
                                }
                                else{
                                    $products_response[] = $final_product;
                                }
                            }
                        }
                    }
                }
            }
            // get keywords for filters
            $keywords = Keyword::all();

            foreach($keywords as $key){
                $keywords_response[] = [
                    'id' => $key -> id,
                    'name' => $key -> name,
                ];
            }

            $pro_res = [];
            if($skip >= count($products_response)){
                $pro_res = [];
            }
            else{
                $end = 0;
                if(($skip + $limit) > count($products_response)){
                    $end = count($products_response);
                }
                else{
                    $end = $skip + $limit;
                }
                for($i = $skip; $i <= ($end - 1); $i++){
                    $pro_res[] = $products_response[$i];
                }
            }

            $data = [
                'products' => $pro_res,
                'keywords' => $keywords_response,
            ];
        }


        $response = [
            'isFailed' => $isFailed,
            'data' => $data,
            'errors' => $errors
        ];

        return response()->json($response);
    }

    public function mobileShowOne(Request $request){
        $isFailed = false;
        $data = [];
        $errors =  [];
        $pharmacies_response = [];
        $product = [];
        $api_token = $request -> api_token;
        $user = null;
        $user = User::where('api_token', $api_token)->first();

        if ($user == null){
            $isFailed = true;
            $errors += [
                'auth' => 'authentication failed'
            ];
        }
        else{
            $product_id = $request -> id;
            $pro = Product::where('id', $product_id)->first();
            if($pro == null){
                $isFailed = true;
                $errors[] = [
                    'error' => 'can not find this product'
                ];
            }
            else{
                $image_id = $pro -> image_id;
                $image = Image::where('id', $image_id)->first();
                if($image != null){
                    $image_path = Storage::url($image -> path . '.' .$image -> extension);
                    $image_url = asset($image_path);
                }
                else{
                    $image_url = asset(Storage::url('default/product.png'));
                }
                $city = City::find($user -> city_id);

                $delivery_fees = $city -> delivery_fees;

                $favourite = Favourite::where('user_id', $user -> id)->where('product_id', $product_id)->first();
                $isFav = false;
                if($favourite != null){
                    $isFav = true;
                }

                $product = [
                    'id' => $product_id,
                    'name' => $pro -> name,
                    'image' => $image_url,
                    'description' => $pro -> description,
                    'price' => $pro -> price,
                    'delivery_fees' => $delivery_fees,
                    'user_address' => $user -> address,
                    'is_favourite' => $isFav,
                ];

                // get the pharmacies that has this product and exist in my city
                $pharmacies_product = ProductPharmacy::where('product_id', $product_id)->get();
                if($pharmacies_product -> isNotEmpty()){
                    foreach($pharmacies_product as $pharmacy_product){
                        if($pharmacy_product -> count > 0){
                            $pharmacy_id = $pharmacy_product -> pharmacy_id;
                            $pharmacies = Pharmacy::where('id', $pharmacy_id)->first();
                            $pharmacy_userid = $pharmacies -> user_id;
                            $pharmacy = User::where(['id' => $pharmacy_userid, 'city_id' => $user -> city_id, 'role_id' => 2])->first();
                            if($pharmacy == null){
                                continue;
                            }
                            else{
                                $image_id = $pharmacy -> image_id;
                                $image = Image::where('id', $image_id)->first();
                                if($image != null){
                                    $image_path = Storage::url($image -> path . '.' .$image -> extension);
                                    $image_url = asset($image_path);
                                }
                                else{
                                    $image_url = asset(Storage::url('default/pharmacy.png'));
                                }
                                //ratings
                                $overall_rating = 0;
                                $rate = 0;
                                $orders = Order::where('pharmacy_id', $pharmacy_id)->get();
                                $orders_count = Order::where('pharmacy_id', $pharmacy_id)->count();
                                $ratings = [];
                                $rating_count = 0;
                                if($orders->isNotEmpty() || $orders_count != 0){
                                    foreach($orders as $order)
                                    {
                                        $order_id = $order -> id;
                                        $rating = PharmacyRating::where('order_id', $order_id)->first();
                                        if($rating == null){
                                            continue;
                                        }
                                        else
                                        {
                                            $rate += $rating -> rating ;
                                            $rating_count += 1;
                                        }
                                    }
                                    if($rating_count == 0){
                                        $overall_rating = 0;
                                    }
                                    else{
                                        $overall_rating = $rate / $rating_count;
                                    }
                                }

                                // build response for each pharmacy
                                $pharma = [
                                    'id' => $pharmacy -> id,
                                    'name' => $pharmacy -> full_name,
                                    'address' => $pharmacies -> address,
                                    'image' => $image_url,
                                    'overall_rating' => $overall_rating,
                                    'city_id' => $pharmacy -> city_id,
                                    'product_pharmacy_id' => $pharmacy_product -> id,
                                    'count' => $pharmacy_product -> count,
                                    'rating_count' => $rating_count
                                ];
                                $pharmacies_response[] = $pharma;
                            }
                        }
                    }
                }
                else{
                    $isFailed = true;
                    $errors += [
                        'error' => 'can not find this product in a pharmacy'
                    ];
                }
                if($pharmacies_response == null){
                    $isFailed = true;
                    $errors += [
                        'error' => 'can not find this product near you',
                    ];
                }
                if($isFailed == false){
                    $data = [
                        'product' => $product,
                        'pharmacies' => $pharmacies_response,
                    ];
                }
            }
        }

        $response = [
            'isFailed' => $isFailed,
            'data' => $data,
            'errors' => $errors
        ];

        return response()->json($response);
    }

    /* Web Tailored Functions */
    /* ***************************************************************************************************
    ******************************************************************************************************
    ******************************************************************************************************
    ******************************************************************************************************
    ******************************************************************************************************
    ******************************************************************************************************
    ******************************************************************************************************
    ******************************************************************************************************
    */

    public function webShowAll(Request $request){
        $isFailed = false;
        $data = [];
        $errors =  [];

        $api_token = $request -> api_token;
        $user = null;
        $user = User::where('api_token', $api_token)->first();

        $keywords_response = [];
        $products_response = [];

        if ($user == null){
            $isFailed = true;
            $errors += [
                'auth' => 'authentication failed'
            ];
        }
        else{
            $city_id = $user -> city_id;
            $skip = $request -> skip;
            $limit = $request -> limit;
            $pharmacies = User::where('city_id', $city_id)->where('role_id', '2')->get();

            if($pharmacies != []){
                foreach($pharmacies as $pharmacy){
                    $pharma_id = $pharmacy -> id;
                    $pharma = Pharmacy::where('user_id', $pharma_id)->first();
                    if($pharma != null){
                        $pharma_id =  $pharma -> id;
                        $products_pharmacy = ProductPharmacy::where('pharmacy_id', $pharma_id)
                            ->orderBy('id', 'asc')
                            ->skip($skip)
                            ->take($limit)
                            ->get();

                        if($products_pharmacy != []){
                            foreach($products_pharmacy as $pro){
                                $product_id = $pro -> product_id;

                                $product = Product::find($product_id);
                                //description
                                $descrption = $product -> description ;
                                $image_id = $product -> image_id;
                                $image = Image::where('id', $image_id)->first();

                                if($image != null){
                                    $image_path = Storage::url($image -> path . '.' .$image -> extension);
                                    $image_url = asset($image_path);
                                }
                                else{
                                    $image_url = asset(Storage::url('default/product.png'));
                                }
                                // check if the user have the product in favourites
                                $favourite = Favourite::where('user_id', $user -> id)->where('product_id', $product -> id)->first();
                                $isFav = false;
                                if($favourite != null){
                                    $isFav = true;
                                }
                                $keywords = ProductKeyword::where('product_id' , $product_id)->get();
                                $keywords_ids = [];
                                if($keywords -> isNotEmpty()){
                                    foreach($keywords as $keyword){
                                        $keyword_id = $keyword -> keyword_id;
                                        $keywords_ids[] = $keyword_id;
                                    }
                                }
                                $final_product = [
                                    'id' => $product -> id,
                                    'name' => $product -> name,
                                    'image' => $image_url,
                                    'price' => $product -> price,
                                    'is_favourite' => $isFav,
                                    'description'  => $descrption,
                                    'keywords' => $keywords_ids
                                ];

                                if(in_array($final_product, $products_response)){
                                    continue;
                                }
                                else{
                                    $products_response[] = $final_product;
                                }
                            }
                        }
                    }
                }
            }

            // get keywords for filters
            $keywords = Keyword::all();

            foreach($keywords as $key){
                $keywords_response[] = [
                    'id' => $key -> id,
                    'name' => $key -> name,
                ];
            }
            $pro_res = [];
            if($skip >= count($products_response)){
                $pro_res = [];
            }
            else{
                $end = 0;
                if(($skip + $limit) > count($products_response)){
                    $end = count($products_response);
                }
                else{
                    $end = $skip + $limit;
                }
                for($i = $skip; $i <= ($end - 1); $i++){
                    $pro_res[] = $products_response[$i];
                }
            }

            $data = [
                'products' => $pro_res,
                'keywords' => $keywords_response,
            ];
        }

        $response = [
            'isFailed' => $isFailed,
            'data' => $data,
            'errors' => $errors
        ];

        return response()->json($response);
    }

    public function webShowOne(Request $request){
        $isFailed = false;
        $data = [];
        $errors =  [];
        $pharmacies_response = [];

        $api_token = $request -> api_token;
        $user = null;
        $user = User::where('api_token', $api_token)->first();

        if ($user == null){
            $isFailed = true;
            $errors += [
                'auth' => 'authentication failed'
            ];
        }
        else{
            $product_id = $request -> id;
            $pro = Product::where('id', $product_id)->first();
            if($pro == null){
                $isFailed = true;
                $errors[] = [
                    'error' => 'can not find this product'
                ];
            }
            else{
                $image_id = $pro -> image_id;
                $image = Image::where('id', $image_id)->first();
                if($image != null){
                    $image_path = Storage::url($image -> path . '.' .$image -> extension);
                    $image_url = asset($image_path);
                }
                else{
                    $image_url = asset(Storage::url('default/product.png'));
                }
                $city = City::find($user -> city_id);

                $delivery_fees = $city -> delivery_fees;

                $favourite = Favourite::where('user_id', $user -> id)->where('product_id', $product_id)->first();
                $isFav = false;
                if($favourite != null){
                    $isFav = true;
                }

                $product = [
                    'id' => $product_id,
                    'name' => $pro -> name,
                    'image' => $image_url,
                    'description' => $pro -> description,
                    'price' => $pro -> price,
                    'delivery_fees' => $delivery_fees,
                    'user_address' => $user -> address,
                    'is_favourite' => $isFav,
                ];

                // get the pharmacies that has this product and exist in my city
                $pharmacies_product = ProductPharmacy::where('product_id', $product_id)->get();
                if($pharmacies_product -> isNotEmpty()){
                    foreach($pharmacies_product as $pharmacy_product){
                        if($pharmacy_product -> count > 0){
                            $pharmacy_id = $pharmacy_product -> pharmacy_id;
                            $pharmacies = Pharmacy::where('id', $pharmacy_id)->first();
                            $pharmacy_userid = $pharmacies -> user_id;
                            $pharmacy = User::where(['id' => $pharmacy_userid, 'city_id' => $user -> city_id, 'role_id' => 2])->first();
                            if($pharmacy == null){
                                continue;
                            }
                            else{
                                $image_id = $pharmacy -> image_id;
                                $image = Image::where('id', $image_id)->first();
                                if($image != null){
                                    $image_path = Storage::url($image -> path . '.' .$image -> extension);
                                    $image_url = asset($image_path);
                                }
                                else{
                                    $image_url = asset(Storage::url('default/pharmacy.png'));
                                }
                                //ratings
                                $overall_rating = 0;
                                $rate = 0;
                                $orders = Order::where('pharmacy_id', $pharmacy_id)->get();
                                $orders_count = Order::where('pharmacy_id', $pharmacy_id)->count();
                                $ratings = [];
                                $rating_count = 0;
                                if($orders->isNotEmpty() || $orders_count != 0){
                                    foreach($orders as $order)
                                    {
                                        $order_id = $order -> id;
                                        $rating = PharmacyRating::where('order_id', $order_id)->first();
                                        if($rating == null){
                                            continue;
                                        }
                                        else
                                        {
                                            $rate += $rating -> rating ;
                                            $rating_count += 1;
                                        }
                                    }
                                    if($rating_count == 0){
                                        $overall_rating = 0;
                                    }
                                    else{
                                        $overall_rating = $rate / $rating_count;
                                    }

                                }

                                // build response for each pharmacy
                                $pharma = [
                                    'id' => $pharmacy -> id,
                                    'name' => $pharmacy -> full_name,
                                    'address' => $pharmacies -> address,
                                    'image' => $image_url,
                                    'overall_rating' => $overall_rating,
                                    'city_id' => $pharmacy -> city_id,
                                    'product_pharmacy_id' => $pharmacy_product -> id,
                                    'count' => $pharmacy_product -> count,
                                    'rating_count' => $rating_count
                                ];
                                $pharmacies_response[] = $pharma;
                            }
                        }
                    }
                }
                else{
                    $isFailed = true;
                    $errors += [
                        'error' => 'can not find this product in a pharmacy'
                    ];
                }
                if($pharmacies_response == null){
                    $isFailed = true;
                    $errors += [
                        'error' => 'can not find this product near you',
                    ];
                }
                if($isFailed == false){
                    $data = [
                        'product' => $product,
                        'pharmacies' => $pharmacies_response,
                    ];
                }
            }
        }

        $response = [
            'isFailed' => $isFailed,
            'data' => $data,
            'errors' => $errors
        ];

        return response()->json($response);
    }
}
