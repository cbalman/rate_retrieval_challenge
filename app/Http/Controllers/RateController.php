<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ShipPrimus\AuthService;
use App\Services\ShipPrimus\RateService;

class RateController extends Controller
{
    protected RateService $rates;

    public function __construct()
    {
        $auth = new AuthService();
        $this->rates = new RateService($auth);
    }

    public function index(Request $request)
    {
        $params = $request->query();

        if ($request->has('freightInfo') && is_string($params['freightInfo'])) {
            $decoded = json_decode($params['freightInfo'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Invalid freightInfo JSON'], 400);
            }
            $params['freightInfo'] = $decoded;
        }

        $results = $this->rates->fetchRates($params);
        $transformed = $this->rates->transformRates($results);
        $cheapest = $this->rates->cheapestPerServiceLevel($transformed);

        return response()->json([
            'data' => $transformed,
            'cheapest' => $cheapest
        ]);
    }
}
