<?php
namespace Eyf\Autoroute;

use Illuminate\Support\Arr;
use Illuminate\Http\Resources\Json\JsonResource;

class SchemaResource extends JsonResource
{
    protected $schema;

    public function __construct($resource, array $schema)
    {
        parent::__construct($resource);

        $this->schema = $schema;
    }

    public function toArray($request)
    {
        $data = parent::toArray($request);

        return $this->setVisible($data, $this->schema);
    }

    protected function setVisible(array $data, array $schema)
    {
        $data = Arr::only($data, array_keys($schema));

        foreach ($schema as $name => $value) {
            if (is_array($value) && isset($data[$name])) {
                $data[$name] = $this->setVisible($data[$name], $value);
            }
        }

        return $data;
    }
}
