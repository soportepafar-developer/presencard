export type UserRole = 'ROLE_ADMIN' | 'ROLE_USER';

export const ROLE_LABELS: Record<UserRole, string> = {
  ROLE_ADMIN: 'Administrador',
  ROLE_USER: 'Usuario',
};

export interface User {
  id: number;
  email: string;
  fullName: string;
  role: UserRole;
  active: boolean;
  createdAt: string;
  updatedAt: string;
}

/** Datos editables por un administrador sobre cualquier usuario. */
export interface UserInput {
  fullName: string;
  email: string;
  role: UserRole;
}

/** Alta: el administrador define la contraseña inicial. */
export interface NewUserInput extends UserInput {
  password: string;
}

/** Datos que cada usuario edita de su propio perfil. */
export interface ProfileInput {
  fullName: string;
  email: string;
}

export interface UserPage {
  items: User[];
  total: number;
  page: number;
  perPage: number;
  totalPages: number;
}

export interface UserListQuery {
  q: string;
  page: number;
}
