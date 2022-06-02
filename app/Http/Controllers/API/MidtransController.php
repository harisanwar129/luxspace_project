<?php

namespace App\Http\Controllers\API;

use Midtrans\Config;
use Midtrans\Notification;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Transaction;

class MidtransController extends Controller
{
    public function callback(){
        //set config midtrans
    Config::$serverKey=config('services.midtrans.serverKey');
    Config::$isProduction=config('services.midtrans.isProduction');
    Config::$isSanitized=config('services.midtrans.isSanitized');
    Config::$is3ds=config('services.midtrans.is3ds');

    // buat instance midtrans notification

    $notification = new Notification();

    $status=$notification->transaction_status;   
    $type=$notification->payment_type;   
    $fraud=$notification->fraud_status;   
    $order_id=$notification->order_id;   

    //get transaction id

    $order=explode('-',$order_id);//['LUX',5]

    //cari transaksi berdasarkan ID
    $transaction=Transaction::findOrFail($order[1]);

    //handle notification status midtrans

    if ($status=='capture'){
        if($type=='credit_cart'){
            if($fraud=='challege'){
                $transaction->status='PENDING';
            }
            else{
                $transaction->status='SUCCESS';
            }
        }
    }
    else if ($status=='settlement'){
        $transaction->status='SUCCESS';
    }
    else if ($status=='pending'){
        $transaction->status='PENDING';
    }
    else if ($status=='deny'){
        $transaction->status='PENDING';
    }
    else if ($status=='expire'){
        $transaction->status='CANCELLED';
    }
    else if ($status=='cancel'){
        $transaction->status='CANCELLED';
    }

    //SIMMPAN TRANASAKSI

    $transaction->save();
    //return midtrans
    return response()->json([
        'meta'=>[
            'code'=>200,
            'message'=>'Midtrans notification success'
        ]
        ]);
    }
}
