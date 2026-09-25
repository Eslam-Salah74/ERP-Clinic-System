<?php

namespace Modules\Setup\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : json_decode($this->data, true) ?? [];

        return [
            'id'            => $this->id,
            'title'         => $data['title'] ?? 'إشعار جديد',
            'message'       => $data['message'] ?? '',
            'type'          => $data['type'] ?? 'general',
            'details'       => $data,
            'read_at'       => $this->read_at ? $this->read_at->toIso8601String() : null,
            'is_read'       => !is_null($this->read_at),
            'created_at'    => $this->created_at ? $this->created_at->toIso8601String() : null,
            'created_at_human' => $this->created_at ? $this->created_at->diffForHumans() : null,
        ];
    }
}
