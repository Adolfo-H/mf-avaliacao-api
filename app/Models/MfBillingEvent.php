<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MfBillingEvent extends Model {
    protected $table = 'mf_billing_events'; public $timestamps = false; protected $guarded = ['id'];
    protected function casts(): array { return ['details'=>'encrypted:array','created_at'=>'datetime']; }
}
