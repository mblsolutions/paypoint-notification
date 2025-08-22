<?php

namespace MBLSolutions\Notification\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Lerouse\LaravelRepository\EloquentRepository;
use MBLSolutions\Notification\Models\NotificationLog;

class NotificationLogRepository extends EloquentRepository
{    
    public function builder(): Builder
    {
        return NotificationLog::query()->selectRaw(config('notification.database.table').'.*');
    }

}
