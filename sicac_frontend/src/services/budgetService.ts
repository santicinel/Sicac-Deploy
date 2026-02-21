import api, { csrf } from "@/lib/axios";

export interface BudgetDocument {
  id: number;
  user_id: number;
  file_name: string;
  file_path: string;
  total_amount: string | number | null;
  items_count: number;
  metadata?: Record<string, unknown> | null;
  generated_at: string;
  created_at: string;
  updated_at: string;
}

const resolveDataArray = <TData>(payload: unknown): TData[] => {
  if (payload && typeof payload === "object") {
    const rootData = (payload as { data?: unknown }).data;
    if (Array.isArray(rootData)) return rootData as TData[];
  }
  if (Array.isArray(payload)) return payload as TData[];
  return [];
};

const saveBudgetDocument = async (payload: {
  pdf_base64: string;
  file_name?: string;
  total_amount?: number;
  items_count?: number;
  metadata?: Record<string, unknown>;
  generated_at?: string;
}): Promise<BudgetDocument> => {
  await csrf();
  const response = await api.post<{ data: BudgetDocument }>("/budgets", payload);
  return response.data.data;
};

const getMyBudgetDocuments = async (): Promise<BudgetDocument[]> => {
  const response = await api.get<{ data?: BudgetDocument[] }>("/budgets/my");
  return resolveDataArray<BudgetDocument>(response.data);
};

const downloadBudgetDocument = async (
  budgetId: number,
  fallbackFileName = "presupuesto-cea-insumos.pdf"
): Promise<void> => {
  const response = await api.get<Blob>(`/budgets/${budgetId}/download`, {
    responseType: "blob",
  });

  const blobUrl = window.URL.createObjectURL(response.data);
  const anchor = document.createElement("a");
  anchor.href = blobUrl;
  anchor.download = fallbackFileName;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.URL.revokeObjectURL(blobUrl);
};

const budgetService = {
  saveBudgetDocument,
  getMyBudgetDocuments,
  downloadBudgetDocument,
};

export default budgetService;
