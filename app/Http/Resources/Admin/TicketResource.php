<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'category' => $this->category ?? 'general',
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name ?? 'Usuário removido',
                'email' => $this->user?->email,
            ],
            'messages' => $this->whenLoaded('messages', function () {
                return $this->messages->map(fn($m) => [
                    'id' => $m->id,
                    'from' => $m->sender_type === 'support' ? 'Admin' : ($this->user?->name ?? 'Usuário'),
                    'text' => $m->content ?? '',
                    'time' => $m->created_at?->toISOString(),
                    'is_staff' => $m->sender_type === 'support',
                    'attachments' => $m->attachments ? $m->attachments->map(fn($a) => [
                        'id' => $a->id,
                        'filename' => $a->original_name ?? $a->filename,
                        'path' => $a->path,
                        'mime_type' => $a->mime_type,
                        'size' => $a->size,
                    ])->toArray() : [],
                ]);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
