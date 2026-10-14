<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['category_id','name', 'description', 'price', 'sku'];

    protected static function booted() : void{
        static::creating(function (Product $product){
            $product->slug = Str::slug($product->name);
        });
    }

    public function category(){
        return $this->belongsTo(Category::class);
    }

    public function inventory(){
        return $this->hasOne(Inventory::class);
    }

}
