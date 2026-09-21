import http from '@/api/http'
import type { Appointment, AppointmentStatus } from '@/types/appointment'
import type { PaginatedResponse } from '@/types/pagination'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export interface AppointmentListParams {
  status?: AppointmentStatus
  page?: number
}

export async function fetchAppointments(params: AppointmentListParams): Promise<PaginatedResponse<Appointment>> {
  const response = await http.get<PaginatedResponse<Appointment>>('/garage/appointments', { params })
  return response.data
}

export async function fetchAppointment(id: number): Promise<Appointment> {
  const response = await http.get<ApiEnvelope<Appointment>>(`/garage/appointments/${id}`)
  return response.data.data
}

export async function confirmAppointment(id: number): Promise<Appointment> {
  const response = await http.post<ApiEnvelope<Appointment>>(`/garage/appointments/${id}/confirm`)
  return response.data.data
}

export async function rejectAppointment(id: number, reason?: string): Promise<Appointment> {
  const response = await http.post<ApiEnvelope<Appointment>>(`/garage/appointments/${id}/reject`, { reason })
  return response.data.data
}

export async function rescheduleAppointment(id: number, proposedAt: string): Promise<Appointment> {
  const response = await http.post<ApiEnvelope<Appointment>>(`/garage/appointments/${id}/reschedule`, {
    proposed_at: proposedAt,
  })
  return response.data.data
}
