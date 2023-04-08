<?php
namespace Eyf\Autoroute;

use Illuminate\Support\Arr;
use Illuminate\Http\Resources\Json\JsonResource;

class SchemaResource extends JsonResource
{
    protected $schema;

    public function setSchema(array $schema)
    {
        $this->schema = $schema;

        return $this;
    }

    public function toArray($request)
    {
        $data = parent::toArray($request);

        if ($this->schema) {
            return $this->setVisible($data, $this->schema);
        }

        return $data;
    }

    protected function setVisible(array $data, array $schema): array
    {
        $data = Arr::only($data, array_keys($schema));

        foreach ($schema as $name => $value) {
            if (is_array($value) && isset($data[$name])) {
                if (Arr::isAssoc($data[$name])) {
                    $data[$name] = $this->setVisible($data[$name], $value);
                } else {
                    foreach ($data[$name] as $index => $item) {
                        if (is_array($item) && Arr::isAssoc($item)) {
                            $data[$name][$index] = $this->setVisible(
                                $item,
                                $value
                            );
                        }
                    }
                }
            }
        }

        return $data;
    }
}
