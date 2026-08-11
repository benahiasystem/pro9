<?php

namespace Modules\Dashboard\Helpers;

use Carbon\Carbon;

class DashboardFilterHelper
{
    public static function resolve(array $request = []): array
    {
        $period = $request['period'] ?? 'last_week';
        $date_start = $request['date_start'] ?? Carbon::now()->subDays(7)->format('Y-m-d');
        $date_end = $request['date_end'] ?? Carbon::now()->format('Y-m-d');
        $month_start = $request['month_start'] ?? Carbon::now()->format('Y-m');
        $month_end = $request['month_end'] ?? Carbon::now()->format('Y-m');

        $d_start = null;
        $d_end = null;

        switch ($period) {
            case 'month':
                $d_start = Carbon::parse($month_start.'-01')->format('Y-m-d');
                $d_end = Carbon::parse($month_start.'-01')->endOfMonth()->format('Y-m-d');
                break;
            case 'between_months':
                $d_start = Carbon::parse($month_start.'-01')->format('Y-m-d');
                $d_end = Carbon::parse($month_end.'-01')->endOfMonth()->format('Y-m-d');
                break;
            case 'date':
                $d_start = $date_start;
                $d_end = $date_start;
                break;
            case 'between_dates':
            case 'last_week':
                $d_start = $date_start;
                $d_end = $date_end;
                break;
            case 'all':
                break;
            default:
                $d_start = $date_start;
                $d_end = $date_end;
                break;
        }

        return [
            'establishment_id' => $request['establishment_id'] ?? null,
            'period' => $period,
            'date_start' => $d_start,
            'date_end' => $d_end,
            'month_start' => $month_start,
            'month_end' => $month_end,
        ];
    }
}
