<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'admin',
        'title',
        'manager',
        'mobile',
        'office',
        'address',
        'company',
        'profile_image',
        'market_id',
        'region_id',
        'territory_id',
        'home_address',
        'company_address',
        'active',
        'demo_coordinator'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Grab the market
    public function market() {
        return $this->hasOne('App\Models\Localities\Market', 'id', 'market_id');
    }

    // Grab the region
    public function region() {
        return $this->hasOne('App\Models\Localities\Region', 'id', 'region_id');
    }

    // Grab the territory
    public function territory() {
        return $this->hasOne('App\Models\Localities\Territory', 'id', 'territory_id');
    }

    // Grab the column filters
    public function columnFilters() {
        return $this->hasMany('App\Models\Users\ColumnFilter', 'user_id', 'id');
    }

    // Grab the addresses
    public function addresses() {
        return $this->hasMany('App\Models\Users\Address', 'user_id', 'id');
    }

    // Grab the primary address
    public function primaryAddress() {
        return $this->hasOne('App\Models\Users\Address', 'user_id', 'id');
    }
}
