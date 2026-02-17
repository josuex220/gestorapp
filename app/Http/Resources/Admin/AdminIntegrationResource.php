<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminIntegrationResource extends JsonResource
{
    public function toArray($request): array
    {
        // Mascara valores sensíveis (type: password)
        $fields = collect($this->fields ?? [])->map(function ($field) {
            if (($field['type'] ?? 'text') === 'password' && !empty($field['value'])) {
                $field['value'] = str_repeat('*', 8);
            }
            return $field;
        })->toArray();

        return [
            'id'          => $this->id,
            'slug'        => $this->slug,
            'name'        => $this->name,
            'description' => $this->description,
            'connected'   => $this->connected,
            'fields'      => $fields,
        ];
    }
}

?>
