<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseBag extends Model
{
    protected $table = 't_purchase_bag';

    protected $fillable = [
        'product_id', 'group', 'category', 'sub_category', 
        'quantity', 'pb_date', 'temp', 'log_user', 'company_id',
    ];

    public function productRelation()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id', 'id');
    }

    public function groupRelation()
    {
        return $this->belongsTo(GroupModel::class, 'group', 'id');
    }

    public function categoryRelation()
    {
        return $this->belongsTo(CategoryModel::class, 'category', 'id');
    }

    public function subCategoryRelation()
    {
        return $this->belongsTo(SubCategoryModel::class, 'sub_category', 'id');
    }
}

?>