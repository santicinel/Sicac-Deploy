<?php

namespace App\Http\Controllers;

use App\Models\ClientRating;
use App\Models\Rating;
use App\Models\TechnicianRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingSummaryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            return response()->json([
                'message' => 'No autorizado para consultar puntajes',
            ], 403);
        }

        $type = $request->query('type', 'technicians');
        $period = (string) $request->query('period', 'all');
        $fromDate = $this->resolveFromDate($period);
        $data = match ($type) {
            'technicians' => $this->technicianSummary($fromDate),
            'clients' => $this->clientSummary($fromDate),
            default => collect(),
        };

        return response()->json(['data' => $data->values()]);
    }

    private function technicianSummary(?Carbon $fromDate = null)
    {
        $aggregates = Rating::query()
            ->select(
                'technician_id',
                DB::raw('AVG(score) as average'),
                DB::raw('COUNT(*) as total'),
                DB::raw('MAX(created_at) as last_created_at')
            )
            ->when($fromDate, fn ($query) => $query->where('created_at', '>=', $fromDate))
            ->groupBy('technician_id')
            ->get();

        $technicianIds = $aggregates->pluck('technician_id')->filter()->all();
        $assignedCasesByTechnician = TechnicianRequest::query()
            ->select(
                'technician_id',
                DB::raw('COUNT(*) as assigned_cases')
            )
            ->whereIn('technician_id', $technicianIds)
            ->when($fromDate, fn ($query) => $query->where('created_at', '>=', $fromDate))
            ->groupBy('technician_id')
            ->get()
            ->keyBy('technician_id');

        $closedCasesByTechnician = TechnicianRequest::query()
            ->select(
                'technician_id',
                DB::raw('COUNT(*) as closed_cases')
            )
            ->whereIn('technician_id', $technicianIds)
            ->where('status', TechnicianRequest::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->when($fromDate, fn ($query) => $query->where('completed_at', '>=', $fromDate))
            ->groupBy('technician_id')
            ->get()
            ->keyBy('technician_id');

        $revenueByTechnician = TechnicianRequest::query()
            ->select(
                'technician_id',
                DB::raw('COALESCE(SUM(charged_amount), 0) as generated_revenue')
            )
            ->whereIn('technician_id', $technicianIds)
            ->where('status', TechnicianRequest::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->whereNotNull('charged_amount')
            ->where('charged_amount', '>', 0)
            ->when($fromDate, fn ($query) => $query->where('completed_at', '>=', $fromDate))
            ->groupBy('technician_id')
            ->get()
            ->keyBy('technician_id');

        $lastByTechnician = Rating::query()
            ->with('technician.user')
            ->whereIn('technician_id', $technicianIds)
            ->when($fromDate, fn ($query) => $query->where('created_at', '>=', $fromDate))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('technician_id')
            ->map(fn ($items) => $items->first());

        return $aggregates->map(function ($row) use ($lastByTechnician, $assignedCasesByTechnician, $closedCasesByTechnician, $revenueByTechnician) {
            $last = $lastByTechnician->get($row->technician_id);
            $assigned = $assignedCasesByTechnician->get($row->technician_id);
            $closed = $closedCasesByTechnician->get($row->technician_id);
            $revenue = $revenueByTechnician->get($row->technician_id);
            $name = $last?->technician?->user?->name;
            [$firstName, $lastName] = $this->splitName($name);

            return [
                'technician_id' => $row->technician_id,
                'name' => $name,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'average' => round((float) $row->average, 2),
                'total' => (int) $row->total,
                'assigned_cases' => (int) ($assigned?->assigned_cases ?? 0),
                'closed_cases' => (int) ($closed?->closed_cases ?? 0),
                'generated_revenue' => round((float) ($revenue?->generated_revenue ?? 0), 2),
                'last_review_at' => $last?->created_at?->toDateTimeString(),
                'last_client_rating' => $last?->score,
                'last_client_notes' => $last?->description,
                'last_comment' => $last?->description,
            ];
        });
    }

    private function clientSummary(?Carbon $fromDate = null)
    {
        $aggregates = ClientRating::query()
            ->select(
                'client_user_id',
                DB::raw('AVG(score) as average'),
                DB::raw('COUNT(*) as total'),
                DB::raw('MAX(created_at) as last_created_at')
            )
            ->when($fromDate, fn ($query) => $query->where('created_at', '>=', $fromDate))
            ->groupBy('client_user_id')
            ->get();

        $clientIds = $aggregates->pluck('client_user_id')->filter()->all();
        $clientsById = User::query()
            ->whereIn('id', $clientIds)
            ->get()
            ->keyBy('id');

        $assignedCasesByClient = TechnicianRequest::query()
            ->select(
                'requesting_user_id as client_user_id',
                DB::raw('SUM(CASE WHEN technician_id IS NOT NULL THEN 1 ELSE 0 END) as assigned_cases')
            )
            ->whereIn('requesting_user_id', $clientIds)
            ->when($fromDate, fn ($query) => $query->where('created_at', '>=', $fromDate))
            ->groupBy('requesting_user_id')
            ->get()
            ->keyBy('client_user_id');

        $closedCasesByClient = TechnicianRequest::query()
            ->select(
                'requesting_user_id as client_user_id',
                DB::raw('COUNT(*) as closed_cases')
            )
            ->whereIn('requesting_user_id', $clientIds)
            ->where('status', TechnicianRequest::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->when($fromDate, fn ($query) => $query->where('completed_at', '>=', $fromDate))
            ->groupBy('requesting_user_id')
            ->get()
            ->keyBy('client_user_id');

        $lastByClient = ClientRating::query()
            ->whereIn('client_user_id', $clientIds)
            ->when($fromDate, fn ($query) => $query->where('created_at', '>=', $fromDate))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('client_user_id')
            ->map(fn ($items) => $items->first());

        return $aggregates->map(function ($row) use ($clientsById, $lastByClient, $assignedCasesByClient, $closedCasesByClient) {
            $client = $clientsById->get($row->client_user_id);
            $last = $lastByClient->get($row->client_user_id);
            $assigned = $assignedCasesByClient->get($row->client_user_id);
            $closed = $closedCasesByClient->get($row->client_user_id);
            [$firstName, $lastName] = $this->splitName($client?->name);

            return [
                'client_user_id' => $row->client_user_id,
                'name' => $client?->name,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $client?->email,
                'average' => round((float) $row->average, 2),
                'total' => (int) $row->total,
                'assigned_cases' => (int) ($assigned?->assigned_cases ?? 0),
                'closed_cases' => (int) ($closed?->closed_cases ?? 0),
                'last_review_at' => $last?->created_at?->toDateTimeString(),
                'last_score' => $last?->score,
                'last_comment' => $last?->description,
            ];
        });
    }

    private function resolveFromDate(string $period): ?Carbon
    {
        return match ($period) {
            'last_day' => now()->subDay(),
            'last_week' => now()->subWeek(),
            'last_month' => now()->subMonth(),
            'last_3_months' => now()->subMonths(3),
            'last_6_months' => now()->subMonths(6),
            'last_12_months' => now()->subMonths(12),
            default => null,
        };
    }

    private function splitName(?string $fullName): array
    {
        $clean = trim((string) $fullName);
        if ($clean === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $clean, 2);
        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }
}
