<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_sent_sms',
        'is_sent_email',
        'is_sent_in_app',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'type' => NotificationType::class,
        'data' => 'array',
        'is_sent_sms' => 'boolean',
        'is_sent_email' => 'boolean',
        'is_sent_in_app' => 'boolean',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, NotificationType $type)
    {
        return $query->where('type', $type);
    }

    // Helpers
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function getDisplayTypeAttribute()
    {
        return $this->type->getLabel();
    }
}
