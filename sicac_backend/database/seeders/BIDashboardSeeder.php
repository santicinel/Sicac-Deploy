<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BIDashboardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $technicians = \App\Models\Technician::all();
        $users = \App\Models\User::where('role', 'user')->get();
        $category = \App\Models\Category::first(); 

        if ($technicians->isEmpty() || $users->isEmpty()) {
            $this->command->error('No users or technicians found. Please run UserSeeder first.');
            return;
        }

        $this->command->info('Creating 100 historical completed requests for BI Dashboard...');

        for ($i = 0; $i < 100; $i++) {
            $tech = $technicians->random();
            $user = $users->random();
            $date = \Carbon\Carbon::now()->subDays(rand(1, 365));

            \App\Models\TechnicianRequest::create([
                'requesting_user_id' => $user->id,
                'technician_id' => $tech->id,
                'category_id' => $category ? $category->id : null,
                'type' => \App\Models\TechnicianRequest::TYPE_TECHNICAL_SERVICE,
                'status' => \App\Models\TechnicianRequest::STATUS_COMPLETED,
                'subject' => 'Servicio Técnico de Ejemplo ' . $i,
                'description' => 'Descripción generada para pruebas del BI Dashboard.',
                'wanted_date_start' => $date->clone()->subDays(2)->format('Y-m-d'),
                'wanted_date_end' => $date->clone()->subDays(1)->format('Y-m-d'),
                'time_shift' => 'morning',
                'scheduled_visit_date' => $date->format('Y-m-d'),
                'resolution_summary' => 'Se solucionó exitosamente el problema detectado.',
                'charged_amount' => rand(15000, 150000),
                'completed_at' => $date,
            ]);
        }

        $this->command->info('BI Dashboard mock data created successfully.');
    }
}
