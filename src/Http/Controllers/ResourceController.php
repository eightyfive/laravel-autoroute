<?php

namespace Eyf\Autoroute\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

use Eyf\Autoroute\Autoroute;

class ResourceController extends Controller
{
    protected $segments;

    protected $autoroute;

    public function __construct(Autoroute $autoroute)
    {
        $this->autoroute = $autoroute;
    }

    public function create(Request $request)
    {
        $models = $this->autoroute->authorizeRequest("create", $request);

        $modelName = end($models);

        $model = $this->autoroute->createModel($modelName, $request->all());

        return response()->json($model);
    }

    public function read(Request $request)
    {
        $models = $this->autoroute->authorizeRequest("read", $request);

        $model = end($models);

        return response()->json($model);
    }

    public function update(Request $request, Model $model)
    {
        $models = $this->autoroute->authorizeRequest("update", $request);

        // TODO: $request->validate($this->autoroute->getValidationRules( ... ));

        $model = end($models);
        $model->fill($request->all());
        $model->save();

        // TODO: Http Model Resource
        return response()->json($model);
        // TODO: return new VoidResponse();
    }

    public function delete(Request $request, Model $model)
    {
        $models = $this->autoroute->authorizeRequest("delete", $request);

        $model = end($models);
        $model->delete();

        return new VoidResponse();
    }

    public function list(Request $request)
    {
        $models = $this->autoroute->authorizeRequest("list", $request);

        $modelName = end($models);

        $models = $this->autoroute->getModels($modelName);

        return response()->json($models);
    }
}
