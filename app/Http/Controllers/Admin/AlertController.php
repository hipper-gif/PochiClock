<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AlertService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', Carbon::yesterday()->toDateString());
        $alertService = app(AlertService::class);

        $missingClockOuts = $alertService->getMissingClockOuts($date);
        $shiftOvertime    = $alertService->getShiftOvertime($date);

        return view('admin.alerts.index', compact('missingClockOuts', 'shiftOvertime', 'date'));
    }
}
