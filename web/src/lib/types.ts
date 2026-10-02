export type Role = 'customer' | 'agent' | 'admin'
export type TicketStatus = 'open' | 'in_progress' | 'resolved' | 'closed'
export type TicketPriority = 'low' | 'medium' | 'high' | 'urgent'

export interface User {
  id: number
  name: string
  email: string
  role: Role
}

export interface Person {
  id: number
  name: string
}

export interface Category {
  id: number
  name: string
}

export interface Comment {
  id: number
  body: string
  is_internal: boolean
  author: Person & { role: Role }
  created_at: string
}

export interface Ticket {
  id: number
  reference: string
  subject: string
  description: string
  status: TicketStatus
  priority: TicketPriority
  category: Category
  requester: Person
  assignee: Person | null
  comments_count: number
  /** Statuses the signed-in user may move this ticket to (decided by the API). */
  allowed_statuses: TicketStatus[]
  created_at: string
  updated_at: string
  resolved_at: string | null
  comments?: Comment[]
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export interface Stats {
  by_status: Record<TicketStatus, number>
  by_priority: Record<TicketPriority, number>
  unassigned: number
  assigned_to_me: number
  average_resolution_hours: number | null
}

export interface TicketFilters {
  q: string
  status: TicketStatus | ''
  priority: TicketPriority | ''
  category_id: number | ''
  assigned: '' | 'me' | 'none'
  page: number
}

export const STATUS_LABELS: Record<TicketStatus, string> = {
  open: 'Open',
  in_progress: 'In progress',
  resolved: 'Resolved',
  closed: 'Closed',
}

export const PRIORITY_LABELS: Record<TicketPriority, string> = {
  low: 'Low',
  medium: 'Medium',
  high: 'High',
  urgent: 'Urgent',
}
