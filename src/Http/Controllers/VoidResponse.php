<?php
namespace Eyf\Autoroute\Http\Controllers;

use Illuminate\Http\JsonResponse;

class VoidResponse extends JsonResponse
{
    public function __construct(
        $status = 204,
        $headers = [],
        $options = 0,
        $json = false
    ) {
        parent::__construct("", $status, $headers, $options, $json);
    }
}
