<?php
namespace Eyf\Autoroute;

use Illuminate\Support\Arr;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SchemaResourceCollection extends ResourceCollection
{
    protected $schema;

    public function setSchema(array $schema)
    {
        $this->schema = $schema;

        return $this;
    }

    public function toArray($request)
    {
        $items = parent::toArray($request);

        if ($this->schema) {
            $data = [];

            foreach ($items as $item) {
                array_push($data, $this->setVisible($item, $this->schema));
            }

            return $data;
        }

        return $items;
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
