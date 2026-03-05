/**
 * INSTRUCCIONES ESTRICTAS:
 * Ya que la regla es NO modificar archivos existentes, debes agregar el 
 * siguiente bloque en tu archivo de ruteo como `sicac_frontend/src/router/index.ts`.
 *
 * Busca el array de rutas (probablemente la ruta superior '/admin'), y agrega 
 * este objeto en el array `children` del Layout de Administrador.
 * Por ejemplo:
 *
 * children: [
 *   // ... otras rutas
 *   {
 *     path: 'dashboard-bi',
 *     name: 'admin-bi-dashboard',
 *     component: () => import('@/views/admin/BIDashboardView.vue'),
 *     meta: {
 *         adminAuth: true,
 *         title: 'BI Dashboard',
 *     },
 *   }
 * ]
 */

export const ADMIN_BI_ROUTE = {
  path: 'dashboard-bi',
  name: 'admin-bi-dashboard',
  component: () => import('@/views/admin/BIDashboardView.vue'),
  meta: {
      adminAuth: true,
      title: 'BI Dashboard',
  },
};
