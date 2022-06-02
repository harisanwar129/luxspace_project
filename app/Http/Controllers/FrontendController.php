<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TransactionItem;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\CheckoutRequest;
use Midtrans\Config;
use Midtrans\Snap;

class FrontendController extends Controller
{
    public function index(Request $request){
        $products=Product::with(['galleries'])->latest()->get();

        return view('pages.frontend.index',compact('products'));
    }

    public function details(Request $request,$slug){
        $product=Product::with(['galleries'])->where('slug',$slug)->firstOrFail();
        $recomendations=Product::with(['galleries'])->inRandomOrder()->limit(4)->get();
        return view('pages.frontend.details',compact('product','recomendations'));
    }

    public function cartAdd(Request $request, $id){
        Cart::create([
            'users_id'=>Auth::user()->id,
            'products_id'=>$id
        ]);
        return redirect('cart');
    }

public function checkout(CheckoutRequest $request){
   
    $data=$request->all();
    //get carts
    $carts=Cart::with(['products'])->where('users_id',Auth::user()->id)->get();

    //add to transaction data
    $data['users_id']=Auth::user()->id;
    $data['total_price']=$carts->sum('products.price');

    //CREATE TRANSACTION
    $transaction=Transaction::create($data);
    //create transaction item
    foreach($carts as $cart){
        $items[]=TransactionItem::create([
           'transactions_id' => $transaction->id,
           'users_id' => $cart->users_id,
           'products_id' => $cart->products_id
            
        ]);
    }

//delete cart after transactions
Cart::where('users_id',Auth::user()->id)->delete();

//konfigurasi midtrans
Config::$serverKey=config('services.midtrans.serverKey');
Config::$isProduction=config('services.midtrans.isProduction');
Config::$isSanitized=config('services.midtrans.isSanitized');
Config::$is3ds=config('services.midtrans.is3ds');

//setup variable midtrans

$midtrans=[
    'transaction_details'=>[
        'order_id'=>'LUX-'.$transaction->id,
        'gross_amount'=>(int) $transaction->total_price
    ],
    'customer_details'=>[
        'first_name'=>$transaction->name,
        'email'=>$transaction->email
    ],
    'enabled_payments'=>['gopay','bank_transfer'],
    'vtweb'=>[]
];


//payment process
try{
    //get snap payment page url
    $paymentUrl=Snap::createTransaction($midtrans)->redirect_url;

    $transaction->payment_url=$paymentUrl;
    $transaction->save();

    // redirect to snap payment page
return redirect($paymentUrl);

}

catch(Exception $e){
    echo $e->getMessage();
}

}



    public function cartDelete(Request $request,$id){
        $item=Cart::findOrFail($id);
        $item->delete();
        return redirect('cart');
    }

    public function cart(Request $request){
        $carts=Cart::with(['products.galleries'])->where('users_id',Auth::user()->id)->get();
        return view('pages.frontend.cart',compact('carts'));
    }
    public function success(Request $request){
        return view('pages.frontend.success');
    }
}
