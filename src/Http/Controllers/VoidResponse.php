<?php
namespace Eyf\Autoroute\Http\Controllers;

use Illuminate\Http\JsonResponse;

class VoidResponse extends JsonResponse
{
    public function __construct($headers = [], $options = 0, $json = false)
    {
        parent::__construct("", 204, $headers, $options, $json);
    }
}
