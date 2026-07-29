export interface AppUser {
  id: number;
  email: string;
  name?: string;
  avatarUrl?: string;
  role?: string;
  email_verified_at?: string | null;
  createdAt?: string;
  lastLoginAt?: string;
}
