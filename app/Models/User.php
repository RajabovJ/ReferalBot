<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'referrer_id',
        'is_admin',
        'referral_verified',
    ];



    /**
     * Kim orqali kelganini ko‘rsatadi (referal ustozi)
     */
    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * Bu foydalanuvchi orqali kelganlar (referallar ro‘yxati)
     */
    public function referrals()
    {
        return $this->hasMany(User::class, 'referrer_id');
    }

    /**
     * Bu foydalanuvchi adminmi?
     */
    public function isAdmin(): bool
    {
        return $this->is_admin;
    }
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_admin' => 'boolean',
        'referral_verified' => 'boolean',
    ];
}
