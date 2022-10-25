<?php
namespace Eyf\Autoroute;

use Illuminate\Support\Arr;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SchemaResourceCollection extends ResourceCollection
{
    protected $schema;

    public function __construct($resource, array $schema)
    {
        parent::__construct($resource);

        $this->schema = $schema;
    }

    public function toArray($request)
    {
        $items = parent::toArray($request);

        $data = [];

        foreach ($items as $item) {
            array_push($data, $this->setVisible($item, $this->schema));
        }

        return $data;
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
