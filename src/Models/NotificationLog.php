<?php

namespace MBLSolutions\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationLog extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'template_id',
        'user_id',
        'method',
        'uri',
        'status',
        'notification_request',
        'notification_response',
    ];
}