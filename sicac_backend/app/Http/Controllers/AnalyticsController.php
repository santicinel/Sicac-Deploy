<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TechnicianRequest;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function getStats(Request $request)
    {
        $baseQuery = TechnicianRequest::with('technician.user')
            ->where('status', TechnicianRequest::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->whereNotNull('charged_amount')
            ->where('charged_amount', '>', 0);

        // Obtener listas de filtros antes de aplicar los del Request
        $allCompleted = $baseQuery->get();
        
        $availableMonths = $allCompleted->map(function ($req) {
            return Carbon::parse($req->completed_at)->format('Y-m');
        })->unique()->sort()->values()->toArray();

        $availableTechnicians = $allCompleted->map(function ($req) {
            if ($req->technician && $req->technician->user) {
                return [
                    'id' => $req->technician->id,
                    'name' => $req->technician->user->name
                ];
            }
            return null;
        })->filter()->unique('id')->values()->toArray();

        // Aplicar filtros
        $query = clone $baseQuery;

        if ($request->has('technician_id') && $request->technician_id) {
            $query->where('technician_id', $request->technician_id);
        }

        if ($request->has('month') && $request->month) {
            $month = $request->month; // YYYY-MM
            $query->whereYear('completed_at', substr($month, 0, 4))
                  ->whereMonth('completed_at', substr($month, 5, 2));
        }

        if ($request->has('period') && $request->period) {
            $period = $request->period;
            if ($period === '3_months') {
                $query->where('completed_at', '>=', Carbon::now()->subMonths(2)->startOfMonth());
            } elseif ($period === '6_months') {
                $query->where('completed_at', '>=', Carbon::now()->subMonths(5)->startOfMonth());
            } elseif ($period === '1_year') {
                $query->where('completed_at', '>=', Carbon::now()->subMonths(11)->startOfMonth());
            }
        }

        $requests = $query->get();

        // Determinar el rango de meses a mostrar (para rellenar vacíos con 0)
        $allMonthsInRange = [];
        if ($request->has('period') && $request->period) {
            $period = $request->period;
            $start = Carbon::now();
            if ($period === '3_months') $start = Carbon::now()->subMonths(2)->startOfMonth();
            elseif ($period === '6_months') $start = Carbon::now()->subMonths(5)->startOfMonth();
            elseif ($period === '1_year') $start = Carbon::now()->subMonths(11)->startOfMonth();
            
            $end = Carbon::now()->endOfMonth();
            $current = clone $start;
            while ($current <= $end) {
                $allMonthsInRange[] = $current->format('Y-m');
                $current->addMonth();
            }
        } else {
            // Si no hay filtro de periodo, usar min y max de los requests
            if ($requests->count() > 0) {
                $minDate = Carbon::parse($requests->min('completed_at'))->startOfMonth();
                $maxDate = Carbon::parse($requests->max('completed_at'))->startOfMonth();
                $current = clone $minDate;
                while ($current <= $maxDate) {
                    $allMonthsInRange[] = $current->format('Y-m');
                    $current->addMonth();
                }
            }
        }
        
        // Si aún está vacío (ej. sin requests y sin filtro explícito), poner mes actual
        if (empty($allMonthsInRange)) {
            $allMonthsInRange[] = Carbon::now()->format('Y-m');
        }

        // 1. Ingresos totales generados por mes (Eje X: Meses, Eje Y: Monto ARS)
        $monthlyIncomeRaw = array_fill_keys($allMonthsInRange, 0);
        foreach ($requests as $req) {
            $month = Carbon::parse($req->completed_at)->format('Y-m');
            if (isset($monthlyIncomeRaw[$month])) {
                $monthlyIncomeRaw[$month] += (float) $req->charged_amount;
            }
        }

        $monthly_income = [
            'labels' => array_keys($monthlyIncomeRaw),
            'data' => array_values($monthlyIncomeRaw),
        ];

        // 1.5. Cantidad de reclamos completados por mes
        $monthlyRequestsRaw = array_fill_keys($allMonthsInRange, 0);
        foreach ($requests as $req) {
            $month = Carbon::parse($req->completed_at)->format('Y-m');
            if (isset($monthlyRequestsRaw[$month])) {
                $monthlyRequestsRaw[$month] += 1;
            }
        }

        $monthly_requests = [
            'labels' => array_keys($monthlyRequestsRaw),
            'data' => array_values($monthlyRequestsRaw),
        ];

        // 2. Ingresos y Reclamos por técnico por mes (comparativa de rendimiento mensual)
        $technicianMonthlyRaw = [];
        $technicianMonthlyRequestsRaw = [];
        $technicianNames = [];

        foreach ($requests as $req) {
            if (!$req->technician || !$req->technician->user) continue;

            $techId = $req->technician->id;
            $techName = $req->technician->user->name;
            $technicianNames[$techId] = $techName;

            if (!isset($technicianMonthlyRaw[$techId])) {
                $technicianMonthlyRaw[$techId] = array_fill_keys($allMonthsInRange, 0);
                $technicianMonthlyRequestsRaw[$techId] = array_fill_keys($allMonthsInRange, 0);
            }

            $month = Carbon::parse($req->completed_at)->format('Y-m');
            if (isset($technicianMonthlyRaw[$techId][$month])) {
                $technicianMonthlyRaw[$techId][$month] += (float) $req->charged_amount;
                $technicianMonthlyRequestsRaw[$techId][$month] += 1;
            }
        }

        $technician_monthly_income = [];
        $technician_monthly_requests = [];
        foreach ($technicianNames as $techId => $name) {
            // Check if this technician actually has data in the filtered set, if they don't, skip them
            if (!isset($technicianMonthlyRaw[$techId])) continue;
            
            $technician_monthly_income[] = [
                'technician_name' => $name,
                'data' => array_values($technicianMonthlyRaw[$techId])
            ];
            
            $technician_monthly_requests[] = [
                'technician_name' => $name,
                'data' => array_values($technicianMonthlyRequestsRaw[$techId])
            ];
        }

        // 3. Participación de cada técnico en el total de ingresos generados históricamente (Gráfico de Torta)
        $technicianHistoricRaw = $requests->filter(function ($req) {
            return $req->technician && $req->technician->user;
        })->groupBy(function ($req) {
            return $req->technician->user->name;
        })->map(function ($group) {
            return $group->sum('charged_amount');
        });

        $technician_historic_income = [
            'labels' => $technicianHistoricRaw->keys()->toArray(),
            'data' => $technicianHistoricRaw->values()->toArray(),
        ];

        // 4. KPIs
        $totalIncome = $requests->sum('charged_amount');
        $totalRequests = $requests->count();
        $averageTicket = $totalRequests > 0 ? $totalIncome / $totalRequests : 0;
        
        $topTechnician = null;
        if ($technicianHistoricRaw->isNotEmpty()) {
            $topTechnicianName = $technicianHistoricRaw->sortDesc()->keys()->first();
            $topTechnicianIncome = $technicianHistoricRaw->sortDesc()->first();
            $topTechnician = [
                'name' => $topTechnicianName,
                'income' => $topTechnicianIncome
            ];
        }

        $kpis = [
            'total_income' => $totalIncome,
            'total_requests' => $totalRequests,
            'average_ticket' => $averageTicket,
            'top_technician' => $topTechnician,
        ];

        // 5. Tabla de Desglose de Datos para Análisis BI
        $breakdownData = $requests
            ->sortByDesc(function ($req) {
                return Carbon::parse($req->completed_at)->timestamp;
            })
            ->values()
            ->map(function ($req) {
                return [
                    'id' => $req->id,
                    'subject' => $req->subject,
                    'description' => $req->description,
                    'resolution_summary' => $req->resolution_summary,
                    'cancellation_reason' => $req->cancellation_reason,
                    'type' => $req->type,
                    'scheduled_visit_date' => $req->scheduled_visit_date ? Carbon::parse($req->scheduled_visit_date)->format('d/m/Y') : null,
                    'scheduled_visit_time' => $req->scheduled_visit_time,
                    'technician' => $req->technician && $req->technician->user ? $req->technician->user->name : 'N/A',
                    'completed_at' => Carbon::parse($req->completed_at)->format('d/m/Y'),
                    'charged_amount' => $req->charged_amount
                ];
            })
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => $kpis,
                'breakdown' => $breakdownData,
                'monthly_income' => $monthly_income,
                'monthly_requests' => $monthly_requests,
                'technician_monthly_income' => $technician_monthly_income,
                'technician_monthly_requests' => $technician_monthly_requests,
                'technician_historic_income' => $technician_historic_income,
                'available_months' => $availableMonths,
                'available_technicians' => $availableTechnicians,
            ]
        ]);
    }
}
