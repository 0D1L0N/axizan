<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'avatar_url',
        'bio',
        'kyc_verified',
        'kyc_document_url',
        'email_verified_at',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'role' => UserRole::class,
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'kyc_verified' => 'boolean',
        'password' => 'hashed',
    ];

    // Relationships
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    public function preparerOrders()
    {
        return $this->hasMany(Order::class, 'preparateur_id');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'livreur_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function receivedReviews()
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByRole($query, UserRole $role)
    {
        return $query->where('role', $role);
    }

    public function scopeKycVerified($query)
    {
        return $query->where('kyc_verified', true);
    }

    // Helpers
    public function isClient()
    {
        return $this->role === UserRole::CLIENT;
    }

    public function isPreparateur()
    {
        return $this->role === UserRole::PREPARATEUR;
    }

    public function isLivreur()
    {
        return $this->role === UserRole::LIVREUR;
    }

    public function isAdmin()
    {
        return $this->role === UserRole::ADMIN;
    }

    public function getRatingAttribute()
    {
        return $this->receivedReviews()
            ->whereNotNull('rating')
            ->avg('rating') ?? 0;
    }

    public function getTotalOrdersAttribute()
    {
        return $this->orders()->count();
    }

    public function getTotalDeliveriesAttribute()
    {
        return $this->deliveries()
            ->where('status', 'delivered')
            ->count();
    }
}
