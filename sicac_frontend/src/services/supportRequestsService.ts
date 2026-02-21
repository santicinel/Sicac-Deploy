import type { AxiosResponse } from "axios";
import api, { csrf } from "@/lib/axios";

export type ServiceRequestType = "technical_service" | "claim";
export type ServiceRequestStatus = "pending" | "assigned" | "completed" | "cancelled";

export interface ApiCategory {
  id: number;
  name: string;
}

export interface ApiTechnicianUser {
  id?: number;
  name?: string;
  email?: string;
  phone?: string;
  address?: string;
  city?: string;
}

export interface ApiTechnician {
  id: number;
  first_name?: string;
  last_name?: string;
  email?: string;
  phone?: string;
  address?: string;
  city?: string;
  availability_date?: string | null;
  user?: ApiTechnicianUser | null;
}

export interface TechnicianUpsertPayload {
  first_name?: string;
  last_name?: string;
  email?: string;
  password?: string;
  dni?: string;
  phone?: string;
  address?: string;
  city?: string;
  availability_date?: string | null;
}

export interface ApiServiceRequest {
  id: number;
  requesting_user_id: number;
  technician_id: number | null;
  category_id: number | null;
  claim_id: number | null;
  repaired_product_id?: number | null;
  type: ServiceRequestType;
  status: ServiceRequestStatus;
  subject: string;
  description: string;
  wanted_date_start: string;
  wanted_date_end: string;
  time_shift: string;
  scheduled_visit_date?: string | null;
  resolution_summary?: string | null;
  cancellation_reason?: string | null;
  charged_amount?: number | string | null;
  completed_at?: string | null;
  created_at: string;
  updated_at: string;
  requesting_user?: {
    id: number;
    name?: string;
    email?: string;
    phone?: string;
    address?: string;
    city?: string;
  } | null;
  technician?: ApiTechnician | null;
  category?: ApiCategory | null;
  claim?: {
    id: number;
    status?: string;
    answer?: string | null;
    answered_at?: string | null;
  } | null;
  rating?: {
    id: number;
    user_id?: number;
    score: number;
    description?: string | null;
    created_at?: string;
    updated_at?: string;
  } | null;
  repaired_product?: {
    id: number;
    name?: string;
    model_sku?: string | null;
    external_id?: string | null;
  } | null;
}

export type RatingSummaryType = "technicians" | "clients";
export type RatingSummaryPeriod =
  | "all"
  | "last_day"
  | "last_week"
  | "last_month"
  | "last_3_months"
  | "last_6_months"
  | "last_12_months";

const resolveDataArray = <TData>(payload: unknown): TData[] => {
  if (payload && typeof payload === "object") {
    const rootData = (payload as { data?: unknown }).data;
    if (Array.isArray(rootData)) return rootData as TData[];
  }

  if (Array.isArray(payload)) return payload as TData[];
  return [];
};

const getCategories = async (): Promise<ApiCategory[]> => {
  const response = await api.get<{ categories?: ApiCategory[]; data?: ApiCategory[] }>("/categories");
  if (Array.isArray(response.data.categories)) return response.data.categories;
  return resolveDataArray<ApiCategory>(response.data);
};

const getTechnicians = async (): Promise<ApiTechnician[]> => {
  const response = await api.get<{ data?: ApiTechnician[] }>("/technicians");
  return resolveDataArray<ApiTechnician>(response.data);
};

const createTechnician = async (payload: TechnicianUpsertPayload): Promise<AxiosResponse<{ data?: ApiTechnician }>> => {
  await csrf();
  return api.post("/technicians", payload);
};

const updateTechnician = async (
  technicianId: number,
  payload: TechnicianUpsertPayload
): Promise<AxiosResponse<{ data?: ApiTechnician }>> => {
  await csrf();
  return api.patch(`/technicians/${technicianId}`, payload);
};

const deleteTechnician = async (technicianId: number): Promise<AxiosResponse> => {
  await csrf();
  return api.delete(`/technicians/${technicianId}`);
};

const getUserRequests = async (): Promise<ApiServiceRequest[]> => {
  const response = await api.get<{ data?: ApiServiceRequest[] }>("/technician-requests/user-requests");
  return resolveDataArray<ApiServiceRequest>(response.data);
};

const getAdminRequests = async (
  params: Partial<{
    status: ServiceRequestStatus;
    search: string;
    type: ServiceRequestType;
    technician_id: number;
  }> = {}
): Promise<ApiServiceRequest[]> => {
  const response = await api.get<{ data?: ApiServiceRequest[] }>("/technician-requests/admin/all", { params });
  return resolveDataArray<ApiServiceRequest>(response.data);
};

const getUnassignedRequests = async (): Promise<ApiServiceRequest[]> => {
  const response = await api.get<{ data?: ApiServiceRequest[] }>("/technician-requests/unassigned");
  return resolveDataArray<ApiServiceRequest>(response.data);
};

const getMyTechnicianRequests = async (): Promise<ApiServiceRequest[]> => {
  const response = await api.get<{ data?: ApiServiceRequest[] }>("/technician-requests/my-requests");
  return resolveDataArray<ApiServiceRequest>(response.data);
};

const createTechnicalRequest = async (payload: {
  category_id?: number | null;
  subject: string;
  description: string;
  wanted_date_start: string;
  wanted_date_end: string;
  time_shift: string;
}): Promise<AxiosResponse<{ data: ApiServiceRequest; message: string }>> => {
  await csrf();
  return api.post("/technician-requests", payload);
};

const createClaim = async (payload: {
  category_id: number;
  subject: string;
  description: string;
  wanted_date_start: string;
  wanted_date_end: string;
  time_shift: string;
}): Promise<AxiosResponse<{ data: unknown; message: string }>> => {
  await csrf();
  return api.post("/claims", payload);
};

const assignToMyself = async (requestId: number): Promise<AxiosResponse<{ data: ApiServiceRequest; message: string }>> => {
  await csrf();
  return api.patch(`/technician-requests/${requestId}/assign-to-myself`);
};

const updateRequestStatus = async (
  requestId: number,
  status: ServiceRequestStatus,
  payload: Partial<{
    scheduled_visit_date: string | null;
    resolution_summary: string | null;
    cancellation_reason: string | null;
    charged_amount: number | null;
    repaired_product_id: number | null;
  }> = {}
): Promise<AxiosResponse<{ data: ApiServiceRequest; message: string }>> => {
  await csrf();
  return api.patch(`/technician-requests/${requestId}/status`, {
    status,
    ...payload,
  });
};

const updateRequest = async (
  requestId: number,
  payload: Partial<{
    status: ServiceRequestStatus;
    technician_id: number | null;
    category_id: number | null;
    type: ServiceRequestType;
    subject: string;
    description: string;
    wanted_date_start: string;
    wanted_date_end: string;
    time_shift: string;
    scheduled_visit_date: string | null;
    resolution_summary: string | null;
    cancellation_reason: string | null;
    charged_amount: number | null;
    repaired_product_id: number | null;
    completed_at: string | null;
  }>
): Promise<AxiosResponse<{ data: ApiServiceRequest; message: string }>> => {
  await csrf();
  return api.patch(`/technician-requests/${requestId}`, payload);
};

const submitTechnicianRating = async (
  technicianId: number,
  payload: {
    technician_request_id: number;
    score: number;
    description?: string;
  }
): Promise<AxiosResponse> => {
  await csrf();
  return api.post(`/technicians/${technicianId}/reviews`, payload);
};

const submitClientRating = async (
  requestId: number,
  payload: {
    score: number;
    description?: string;
  }
): Promise<AxiosResponse> => {
  await csrf();
  return api.post(`/technician-requests/${requestId}/client-rating`, payload);
};

const getRatingsSummary = async (
  type: RatingSummaryType,
  period: RatingSummaryPeriod = "all"
): Promise<unknown[]> => {
  const response = await api.get<{ data?: unknown[] }>("/ratings", { params: { type, period } });
  return resolveDataArray(response.data);
};

const supportRequestsService = {
  getCategories,
  getTechnicians,
  createTechnician,
  updateTechnician,
  deleteTechnician,
  getUserRequests,
  getAdminRequests,
  getUnassignedRequests,
  getMyTechnicianRequests,
  createTechnicalRequest,
  createClaim,
  assignToMyself,
  updateRequestStatus,
  updateRequest,
  submitTechnicianRating,
  submitClientRating,
  getRatingsSummary,
};

export default supportRequestsService;
