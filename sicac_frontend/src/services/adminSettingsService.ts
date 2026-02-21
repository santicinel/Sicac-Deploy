import api, { csrf } from "@/lib/axios";

const DEFAULT_LABOR_RATE = 1500;

const parseLaborRate = (value: unknown): number => {
  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric < 0) return DEFAULT_LABOR_RATE;
  return Number(numeric.toFixed(2));
};

const getLaborRate = async (): Promise<number> => {
  const response = await api.get<{ data?: { labor_rate?: number | string } }>(
    "/settings/labor-rate"
  );

  return parseLaborRate(response.data?.data?.labor_rate);
};

const updateLaborRate = async (laborRate: number): Promise<number> => {
  await csrf();
  const response = await api.patch<{ data?: { labor_rate?: number | string } }>(
    "/settings/labor-rate",
    {
      labor_rate: laborRate,
    }
  );

  return parseLaborRate(response.data?.data?.labor_rate);
};

const adminSettingsService = {
  getLaborRate,
  updateLaborRate,
};

export default adminSettingsService;
export { getLaborRate, updateLaborRate };
