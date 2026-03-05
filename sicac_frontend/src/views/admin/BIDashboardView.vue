<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Bar, Pie } from 'vue-chartjs';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  ArcElement,
  LineElement,
  PointElement,
  LineController
} from 'chart.js';
import ChartDataLabels from 'chartjs-plugin-datalabels';
import api from '@/lib/axios';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement, LineElement, PointElement, LineController, ChartDataLabels);

const isLoading = ref(false);
const error = ref('');

const selectedMonth = ref('');
const selectedTechnician = ref('');
const availableMonths = ref<string[]>([]);
const availableTechnicians = ref<{id: number, name: string}[]>([]);
const kpis = ref<any>(null);

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value);
};

// Interfaces
interface MonthlyIncomeData {
  labels: string[];
  data: number[];
}

interface MonthlyRequestsData {
  labels: string[];
  data: number[];
}

interface TechnicianMonthlyIncome {
  technician_name: string;
  data: number[];
}

interface TechnicianMonthlyRequests {
  technician_name: string;
  data: number[];
}

interface TechnicianHistoricIncome {
  labels: string[];
  data: number[];
}

interface BreakdownRow {
  id: number;
  subject: string;
  description: string;
  resolution_summary: string | null;
  cancellation_reason: string | null;
  type: string;
  scheduled_visit_date: string | null;
  scheduled_visit_time: string | null;
  technician: string;
  completed_at: string;
  charged_amount: number;
}

// Chart Data Objects
const barChartData = ref<any>({ labels: [], datasets: [] });
const requestsChartData = ref<any>({ labels: [], datasets: [] });
const groupedBarChartData = ref<any>({ labels: [], datasets: [] });
const pieChartData = ref<any>({ labels: [], datasets: [] });
const breakdownData = ref<BreakdownRow[]>([]);

// Table State
const expandedRows = ref<Set<number>>(new Set());

const toggleRow = (id: number) => {
  const newSet = new Set(expandedRows.value);
  if (newSet.has(id)) {
    newSet.delete(id);
  } else {
    newSet.add(id);
  }
  expandedRows.value = newSet;
};

// Chart Options
const baseChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    datalabels: {
      color: '#fff',
      font: {
        weight: 'bold' as const
      },
      formatter: (value: any) => {
        if (value === 0) return '';
        if (value > 1000) {
          return '$' + (value / 1000).toFixed(0) + 'k';
        }
        return '$' + value;
      }
    }
  }
};

const barChartOptions = {
  ...baseChartOptions,
  onClick: (_event: any, elements: any[]) => {
    if (elements.length > 0) {
      const index = elements[0].index;
      const month = barChartData.value.labels[index];
      if (month) selectedMonth.value = month;
    }
  }
};

const requestsChartOptions = {
  ...baseChartOptions,
  plugins: {
    ...baseChartOptions.plugins,
    datalabels: {
       ...baseChartOptions.plugins.datalabels,
       formatter: (value: any) => value.toString(), // No currency formatting for raw counts
       rotation: (context: any) => {
         const labelsCount = context.chart.data.labels?.length || 1;
         const datasetsCount = context.chart.data.datasets?.length || 1;
         const totalBars = labelsCount * datasetsCount;
         const areaWidth = context.chart.chartArea ? context.chart.chartArea.width : context.chart.width;
         const barWidth = areaWidth / totalBars;
         // Text is horizontal if bar > 40px wide, else -90 deg vertical
         return barWidth > 40 ? 0 : -90;
       },
       align: 'center' as const,
       anchor: 'center' as const,
       font: (context: any) => {
         const labelsCount = context.chart.data.labels?.length || 1;
         const datasetsCount = context.chart.data.datasets?.length || 1;
         const totalBars = labelsCount * datasetsCount;
         const areaWidth = context.chart.chartArea ? context.chart.chartArea.width : context.chart.width;
         const barWidth = areaWidth / totalBars;
         
         let dynamicSize = 11;
         if (barWidth > 80) dynamicSize = 16;
         else if (barWidth > 40) dynamicSize = 14;
         
         return {
           size: dynamicSize,
           weight: 'bold' as const
         };
       }
    }
  },
  onClick: (_event: any, elements: any[]) => {
    if (elements.length > 0) {
      const element = elements[0];
      const datasetIndex = element.datasetIndex;
      const index = element.index;
      
      const month = requestsChartData.value.labels[index];
      const techName = requestsChartData.value.datasets[datasetIndex].label;
      
      const tech = availableTechnicians.value.find(t => t.name === techName);
      
      if (month) selectedMonth.value = month;
      if (tech) selectedTechnician.value = tech.id as any;
    }
  }
};

const pieChartOptions = {
  ...baseChartOptions,
  plugins: {
    ...baseChartOptions.plugins,
    datalabels: {
      ...baseChartOptions.plugins.datalabels,
      formatter: (value: any, context: any) => {
        if (value === 0) return '';
        
        let total = 0;
        const dataArr = context.chart.data.datasets[0].data;
        dataArr.map((data: number) => {
            total += data;
        });
        
        const percentage = (value * 100 / total).toFixed(1) + "%";
        return percentage;
      }
    }
  },
  onClick: (_event: any, elements: any[]) => {
    if (elements.length > 0) {
      const index = elements[0].index;
      const techName = pieChartData.value.labels[index];
      const tech = availableTechnicians.value.find(t => t.name === techName);
      if (tech) selectedTechnician.value = tech.id as any;
    }
  }
};

const groupedBarChartOptions = {
  ...baseChartOptions,
  plugins: {
    ...baseChartOptions.plugins,
    datalabels: {
      ...baseChartOptions.plugins.datalabels,
      rotation: (context: any) => {
        const labelsCount = context.chart.data.labels?.length || 1;
        const datasetsCount = context.chart.data.datasets?.length || 1;
        const totalBars = labelsCount * datasetsCount;
        const areaWidth = context.chart.chartArea ? context.chart.chartArea.width : context.chart.width;
        const barWidth = areaWidth / totalBars;
        // Text is horizontal if bar > 40px wide, else -90 deg vertical
        return barWidth > 40 ? 0 : -90;
      },
      align: 'center' as const,
      anchor: 'center' as const,
      font: (context: any) => {
        const labelsCount = context.chart.data.labels?.length || 1;
        const datasetsCount = context.chart.data.datasets?.length || 1;
        const totalBars = labelsCount * datasetsCount;
        const areaWidth = context.chart.chartArea ? context.chart.chartArea.width : context.chart.width;
        const barWidth = areaWidth / totalBars;
        
        let dynamicSize = 11;
        if (barWidth > 80) dynamicSize = 16;
        else if (barWidth > 40) dynamicSize = 14;
        
        return {
          size: dynamicSize,
          weight: 'bold' as const
        };
      }
    }
  },
  onClick: (_event: any, elements: any[]) => {
    if (elements.length > 0) {
      const element = elements[0];
      const datasetIndex = element.datasetIndex;
      const index = element.index;
      
      const month = groupedBarChartData.value.labels[index];
      const techName = groupedBarChartData.value.datasets[datasetIndex].label;
      
      const tech = availableTechnicians.value.find(t => t.name === techName);
      
      if (month) selectedMonth.value = month;
      if (tech) selectedTechnician.value = tech.id as any;
    }
  }
};

const fetchAnalytics = async () => {
  isLoading.value = true;
  error.value = '';
  expandedRows.value = new Set(); // Reset expanded rows on new search

  try {
    const params: any = {
      technician_id: selectedTechnician.value || undefined
    };

    if (selectedMonth.value.includes('_') || selectedMonth.value.includes('year')) {
      params.period = selectedMonth.value;
    } else if (selectedMonth.value) {
      params.month = selectedMonth.value;
    }

    const response = await api.get('/analytics/admin/stats', {
      params
    });
    if (response.data && response.data.success) {
      const stats = response.data.data;

      availableMonths.value = stats.available_months;
      availableTechnicians.value = stats.available_technicians;
      kpis.value = stats.kpis;
      breakdownData.value = stats.breakdown;

      const monthlyIncome: MonthlyIncomeData = stats.monthly_income;
      const monthlyRequests: MonthlyRequestsData = stats.monthly_requests;
      const techMonthlyIncome: TechnicianMonthlyIncome[] = stats.technician_monthly_income;
      const techMonthlyRequests: TechnicianMonthlyRequests[] = stats.technician_monthly_requests;
      const techHistoricIncome: TechnicianHistoricIncome = stats.technician_historic_income;

      // 1. Gráfico Mixto: Ingresos totales generados por mes
      barChartData.value = {
        labels: monthlyIncome.labels,
        datasets: [
          {
            type: 'line',
            label: 'Tendencia de Ingresos',
            borderColor: '#f59e0b', // Tailwind amber-500
            backgroundColor: '#f59e0b',
            borderWidth: 2,
            tension: 0.3,
            fill: false,
            data: monthlyIncome.data,
            datalabels: {
              align: 'top',
              anchor: 'end',
              color: '#f59e0b',
              backgroundColor: 'rgba(255,255,255,0.8)',
              borderRadius: 4,
            }
          },
          {
            type: 'bar',
            label: 'Ingresos Totales (ARS)',
            backgroundColor: '#3b82f6', // Tailwind blue-500
            data: monthlyIncome.data,
          }
        ]
      };

      const colors = [
        '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4', '#ec4899', '#f97316'
      ];

      // 1.5. Gráfico de Barras Agrupado: Cantidad de reclamos completados por técnico por mes
      requestsChartData.value = {
        labels: monthlyRequests.labels,
        datasets: techMonthlyRequests.map((tech, index) => ({
          label: tech.technician_name,
          backgroundColor: colors[index % colors.length],
          data: tech.data,
        }))
      };

      // 2. Gráfico de Barras Agrupado: Ingresos por técnico por mes
      groupedBarChartData.value = {
        labels: monthlyIncome.labels,
        datasets: techMonthlyIncome.map((tech, index) => ({
          label: tech.technician_name,
          backgroundColor: colors[index % colors.length],
          data: tech.data,
        }))
      };

      // 3. Gráfico de Torta: Participación histórica por técnico
      pieChartData.value = {
        labels: techHistoricIncome.labels,
        datasets: [
          {
            backgroundColor: colors.slice(0, techHistoricIncome.labels.length),
            data: techHistoricIncome.data,
          }
        ]
      };
    } else {
      error.value = 'Failed to load analytics data';
    }
  } catch (err: any) {
    console.error(err);
    error.value = 'Error fetching BI analytics. Please verify your backend API.';
  } finally {
    isLoading.value = false;
  }
};

const resetFilters = () => {
  selectedMonth.value = '';
  selectedTechnician.value = '';
};

// Fullscreen State
const fullscreenChart = ref<'monthly' | 'requests' | 'grouped' | 'pie' | null>(null);

const openFullscreen = (chart: 'monthly' | 'requests' | 'grouped' | 'pie') => {
  fullscreenChart.value = chart;
  document.body.style.overflow = 'hidden'; // Prevent background scrolling
};

const closeFullscreen = () => {
  fullscreenChart.value = null;
  document.body.style.overflow = '';
};

watch([selectedMonth, selectedTechnician], () => {
  fetchAnalytics();
});

onMounted(() => {
  fetchAnalytics();
});
</script>

<template>
  <div class="p-4 md:p-6 space-y-6 w-full max-w-[100vw] overflow-hidden relative">
    <!-- Fullscreen Modal -->
    <div v-if="fullscreenChart" class="fixed inset-0 z-50 flex flex-col bg-background/95 backdrop-blur-sm shadow-2xl p-6 md:p-10 transition-all duration-300">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 border-b pb-4">
        <h2 class="text-2xl font-bold">
          {{ fullscreenChart === 'monthly' ? 'Ingresos Totales por Mes (ARS)' : 
             fullscreenChart === 'requests' ? 'Reclamos Completados por Mes' : 
             fullscreenChart === 'grouped' ? 'Ingresos por Técnico (Mensual)' : 'Participación Histórica de Ingresos' }}
        </h2>
        
        <div class="flex items-center gap-4 w-full md:w-auto">
          <!-- Flters inside modal -->
          <select v-model="selectedMonth" class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 w-full md:w-auto">
            <option value="">Todos los tiempos</option>
            <optgroup label="Períodos Rápidos">
              <option value="3_months">Últimos 3 meses</option>
              <option value="6_months">Últimos 6 meses</option>
              <option value="1_year">Último año</option>
            </optgroup>
            <optgroup label="Tiempos Específicos">
              <option v-for="m in availableMonths" :key="m" :value="m">{{ m }}</option>
            </optgroup>
          </select>
          
          <select v-model="selectedTechnician" class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 w-full md:w-auto">
            <option value="">Todos los técnicos</option>
            <option v-for="t in availableTechnicians" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>

          <button @click="closeFullscreen" class="p-2 rounded-full hover:bg-muted transition-colors ml-auto sm:ml-0 group" title="Cerrar pantalla completa">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-muted-foreground group-hover:text-foreground"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
          </button>
        </div>
      </div>

      <div class="flex-1 w-full relative min-h-[400px]">
        <Bar v-if="fullscreenChart === 'monthly'" :data="barChartData" :options="{ ...barChartOptions, maintainAspectRatio: false }" />
        <Bar v-if="fullscreenChart === 'requests'" :data="requestsChartData" :options="{ ...requestsChartOptions, maintainAspectRatio: false }" />
        <Bar v-if="fullscreenChart === 'grouped'" :data="groupedBarChartData" :options="{ ...groupedBarChartOptions, maintainAspectRatio: false }" />
        <div v-if="fullscreenChart === 'pie'" class="h-full w-full flex justify-center items-center pb-10">
           <div class="w-full max-w-2xl h-full cursor-pointer">
             <Pie :data="pieChartData" :options="{ ...pieChartOptions, maintainAspectRatio: false }" />
           </div>
        </div>
      </div>
    </div>

    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">BI Dashboard</h1>
        <p class="text-muted-foreground">
          Panel analítico inteligente para revisión de ingresos de técnicos.
        </p>
      </div>

      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <select v-model="selectedMonth" class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
          <option value="">Todos los tiempos</option>
          <optgroup label="Períodos Rápidos">
            <option value="3_months">Últimos 3 meses</option>
            <option value="6_months">Últimos 6 meses</option>
            <option value="1_year">Último año</option>
          </optgroup>
          <optgroup label="Meses Específicos">
            <option v-for="m in availableMonths" :key="m" :value="m">{{ m }}</option>
          </optgroup>
        </select>
        
        <select v-model="selectedTechnician" class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
          <option value="">Todos los técnicos</option>
          <option v-for="t in availableTechnicians" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>

        <button @click="resetFilters" class="text-sm text-primary hover:underline" v-if="selectedMonth || selectedTechnician">Limpiar</button>
      </div>
    </header>

    <div v-if="isLoading" class="flex justify-center items-center py-20">
      <p class="text-muted-foreground text-lg">Cargando métricas...</p>
    </div>

    <div v-else-if="error" class="flex justify-center items-center py-20">
      <p class="text-destructive font-semibold">{{ error }}</p>
    </div>

    <div v-else class="grid gap-6">
      
      <!-- KPIs Cards -->
      <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4" v-if="kpis">
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm p-4 md:p-6 flex flex-col justify-center">
          <div class="flex flex-row items-center justify-between space-y-0 pb-2">
            <h3 class="tracking-tight text-sm font-medium">Ingresos Totales</h3>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" class="h-4 w-4 text-muted-foreground"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
          </div>
          <div class="text-2xl font-bold">{{ formatCurrency(kpis.total_income) }}</div>
        </div>
        
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm p-4 md:p-6 flex flex-col justify-center">
          <div class="flex flex-row items-center justify-between space-y-0 pb-2">
            <h3 class="tracking-tight text-sm font-medium">Ticket Promedio</h3>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" class="h-4 w-4 text-muted-foreground"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
          </div>
          <div class="text-2xl font-bold">{{ formatCurrency(kpis.average_ticket) }}</div>
        </div>

        <div class="rounded-lg border bg-card text-card-foreground shadow-sm p-4 md:p-6 flex flex-col justify-center">
          <div class="flex flex-row items-center justify-between space-y-0 pb-2">
            <h3 class="tracking-tight text-sm font-medium">Reclamos Completados</h3>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" class="h-4 w-4 text-muted-foreground"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
          </div>
          <div class="text-2xl font-bold">+{{ kpis.total_requests }}</div>
        </div>

        <div class="rounded-lg border bg-card text-card-foreground shadow-sm p-4 md:p-6 flex flex-col justify-center">
          <div class="flex flex-row items-center justify-between space-y-0 pb-2">
            <h3 class="tracking-tight text-sm font-medium">Técnico Destacado</h3>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" class="h-4 w-4 text-muted-foreground"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
          </div>
          <div class="text-2xl font-bold truncate" :title="kpis.top_technician ? kpis.top_technician.name : 'N/A'">
            {{ kpis.top_technician ? kpis.top_technician.name : 'N/A' }}
          </div>
          <p class="text-xs text-muted-foreground" v-if="kpis.top_technician">
            {{ formatCurrency(kpis.top_technician.income) }} generados
          </p>
        </div>
      </div>

      <!-- Fila 1: Macro Visión (Ingresos Totales) -->
      <div class="rounded-lg border bg-card p-4 md:p-6 shadow-sm w-full overflow-hidden group">
        <div class="flex justify-between items-start mb-4">
          <h2 class="text-lg md:text-xl shrink-0 font-semibold">Ingresos Totales por Mes (ARS)</h2>
          <button @click="openFullscreen('monthly')" class="p-1.5 rounded-md text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity hover:bg-muted focus:opacity-100" title="Ver en pantalla completa">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
          </button>
        </div>
        <div class="h-64 md:h-80 w-full relative cursor-pointer">
          <Bar :data="barChartData" :options="barChartOptions" />
        </div>
      </div>

      <!-- Fila 2: Comparativas de Técnicos (Ingresos vs Reclamos) -->
      <div class="grid gap-6 lg:grid-cols-2 w-full">
        <!-- Gráfico: Ingresos por Técnico -->
        <div class="rounded-lg border bg-card p-4 md:p-6 shadow-sm w-full overflow-hidden group">
          <div class="flex justify-between items-start mb-4">
            <h2 class="text-lg md:text-xl font-semibold">Ingresos por Técnico (Mensual)</h2>
            <button @click="openFullscreen('grouped')" class="p-1.5 rounded-md text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity hover:bg-muted focus:opacity-100" title="Ver en pantalla completa">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
            </button>
          </div>
          <div class="h-64 md:h-80 w-full relative cursor-pointer">
            <Bar :data="groupedBarChartData" :options="groupedBarChartOptions" />
          </div>
        </div>

        <!-- Gráfico: Cantidad Reclamos por Técnico -->
        <div class="rounded-lg border bg-card p-4 md:p-6 shadow-sm w-full overflow-hidden group">
          <div class="flex justify-between items-start mb-4">
            <h2 class="text-lg md:text-xl shrink-0 font-semibold">Reclamos Completados por Técnico</h2>
            <button @click="openFullscreen('requests')" class="p-1.5 rounded-md text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity hover:bg-muted focus:opacity-100" title="Ver en pantalla completa">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
            </button>
          </div>
          <div class="h-64 md:h-80 w-full relative cursor-pointer">
            <Bar :data="requestsChartData" :options="requestsChartOptions" />
          </div>
        </div>
      </div>

      <!-- Fila 3: Histórico y Tabla Dinámica -->
      <div class="grid gap-6 lg:grid-cols-3 w-full">
        <!-- Gráfico: Participación Histórica (1 Columna) -->
        <div class="rounded-lg border bg-card p-4 md:p-6 shadow-sm w-full overflow-hidden group lg:col-span-1">
          <div class="flex justify-between items-start mb-4">
            <h2 class="text-lg md:text-xl font-semibold text-center md:text-left">Participación Histórica</h2>
            <button @click="openFullscreen('pie')" class="p-1.5 rounded-md text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity hover:bg-muted focus:opacity-100" title="Ver en pantalla completa">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
            </button>
          </div>
          <div class="h-64 md:h-[400px] relative flex justify-center items-center w-full">
            <div class="w-full max-w-[280px] md:max-w-xs h-full cursor-pointer">
              <Pie :data="pieChartData" :options="pieChartOptions" />
            </div>
          </div>
        </div>

        <!-- Tabla de Desglose (2 Columnas) -->
        <div class="rounded-lg border bg-card p-4 md:p-6 shadow-sm w-full overflow-hidden lg:col-span-2 flex flex-col">
          <h2 class="text-lg md:text-xl font-semibold mb-4">Desglose de Ingresos Relevantes</h2>
          <div class="overflow-x-auto w-full flex-1 min-h-[400px]">
          <table class="w-full text-sm text-left border-collapse whitespace-nowrap md:whitespace-normal">
            <thead class="text-xs text-muted-foreground uppercase bg-muted/50 border-b">
              <tr>
                <th scope="col" class="px-6 py-3 font-medium">ID Ref.</th>
                <th scope="col" class="px-6 py-3 font-medium">Asunto</th>
                <th scope="col" class="px-6 py-3 font-medium">Técnico Asignado</th>
                <th scope="col" class="px-6 py-3 font-medium text-right">Monto Percibido</th>
                <th scope="col" class="px-6 py-3 font-medium text-right">Fecha Solución</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="item in breakdownData" :key="item.id">
                <tr 
                  @click="toggleRow(item.id)"
                  class="border-b transition-colors cursor-pointer hover:bg-muted/50"
                  :class="{'bg-muted/30': expandedRows.has(item.id)}"
                >
                  <td class="px-6 py-4 font-mono text-muted-foreground flex items-center gap-2">
                    <svg 
                      xmlns="http://www.w3.org/2000/svg" 
                      viewBox="0 0 24 24" 
                      fill="none" 
                      stroke="currentColor" 
                      stroke-width="2" 
                      stroke-linecap="round" 
                      stroke-linejoin="round" 
                      class="h-4 w-4 transition-transform duration-200"
                      :class="{'rotate-90': expandedRows.has(item.id)}"
                    >
                      <path d="m9 18 6-6-6-6"/>
                    </svg>
                    #{{ item.id }}
                  </td>
                  <td class="px-6 py-4 font-medium">{{ item.subject }}</td>
                  <td class="px-6 py-4">{{ item.technician }}</td>
                  <td class="px-6 py-4 text-right font-medium text-green-600 dark:text-green-500">{{ formatCurrency(item.charged_amount) }}</td>
                  <td class="px-6 py-4 text-right tabular-nums">{{ item.completed_at }}</td>
                </tr>
                <!-- Accordion Detail Row -->
                <tr v-if="expandedRows.has(item.id)" class="border-b bg-muted/10">
                  <td colspan="5" class="px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                      <div class="space-y-3">
                        <div>
                          <span class="font-semibold text-foreground block mb-1">Descripción Inicial</span>
                          <p class="text-muted-foreground whitespace-pre-wrap">{{ item.description }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                          <div>
                            <span class="font-semibold text-foreground block mb-1">Tipo</span>
                            <span class="inline-flex items-center rounded-md bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground ring-1 ring-inset ring-secondary-foreground/10">
                              {{ item.type === 'technical_service' ? 'Servicio Técnico' : 'Reclamo/Garantía' }}
                            </span>
                          </div>
                          <div v-if="item.scheduled_visit_date">
                            <span class="font-semibold text-foreground block mb-1">Visita Realizada</span>
                            <span class="text-muted-foreground">{{ item.scheduled_visit_date }} {{ item.scheduled_visit_time ? 'a las ' + item.scheduled_visit_time : '' }}</span>
                          </div>
                        </div>
                      </div>
                      <div class="space-y-3">
                        <div v-if="item.resolution_summary">
                          <span class="font-semibold text-foreground block mb-1">Resumen de Resolución</span>
                          <div class="p-3 bg-secondary/50 rounded-md border border-border">
                            <p class="text-muted-foreground whitespace-pre-wrap">{{ item.resolution_summary }}</p>
                          </div>
                        </div>
                        <div v-if="item.cancellation_reason" class="pt-2">
                          <span class="font-semibold text-destructive block mb-1">Motivo de Cancelación (Si aplica)</span>
                          <p class="text-destructive/80">{{ item.cancellation_reason }}</p>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-if="breakdownData.length === 0">
                <td colspan="5" class="px-6 py-8 text-center text-muted-foreground">No hay datos financieros para mostrar con estos filtros.</td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
