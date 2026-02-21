import { createRouter, createWebHistory, type RouteRecordRaw } from "vue-router";
import { useAuthStore } from "@/store/authStore";

const routes: RouteRecordRaw[] = [
    {
        path: '/admin/login',
        name: 'admin-login',
        component: () => import('@/views/admin/AdminLoginView.vue'),
        meta: {
            layout: 'auth',
            requiresAuth: false,
            carouselImages: [
                'https://media.istockphoto.com/id/1396322048/photo/young-indian-businesswoman-talking-on-a-telephone-in-an-office-alone-one-female-only-making-a.jpg?s=612x612&w=0&k=20&c=LdozydESIBqXbzF7f0KM2WQogUemeWkmB33d1DGMvK4=',
                'https://img.freepik.com/premium-photo/front-view-portrait-young-smiling-experienced-smart-stylish-bearded-hindu-office-manager_769609-344.jpg',
                'https://codigoespagueti.com/wp-content/uploads/2020/11/The-Boss-Baby-2-Estrena-Trailer-min.jpg'
            ]
        },
    },
    {
        path: '/technician/login',
        name: 'technician-login',
        component: () => import('@/views/technician/TechnicianLoginView.vue'),
        meta: {
            layout: 'auth',
            requiresAuth: false,
            carouselImages: [
                'https://media.istockphoto.com/id/1036826370/photo/man-installing-security-alarm-system.jpg?s=612x612&w=0&k=20&c=ORdwPiZUruOLm_1wsI8cA6TqBvmUPbMb5MkhapyVqOk=',
                'https://courses.esaweb.org/wp-content/uploads/2021/02/Untitled-design-2021-02-10T090412.894.png',
                'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQsqMVOz8rZ7UOexLbscFfRa6GA4GwvEwlSNg&s'
            ]
        },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
        meta: {
            layout: 'simple',
            requiresAuth: false
        },
    },
    {
        path: '/terms',
        name: 'terms',
        component: () => import('@/views/TermsView.vue'),
        meta: {
            layout: 'simple',
            requiresAuth: false
        },
    },
    {
        path: '/faq',
        name: 'faq',
        component: () => import('@/views/FaqView.vue'),
        meta: {
            layout: 'simple',
            requiresAuth: false
        },
    },
    {
        path: '/manual/:role',
        name: 'manual-role',
        component: () => import('@/views/ManualView.vue'),
        meta: {
            layout: 'simple',
            requiresAuth: false
        },
    },
    {
        path: '/home',
        name: 'home',
        component: () => import('@/views/HomeView.vue'),
        meta: {
            layout: 'app',
            requiresAuth: true
        },
    },
    {
        path: '/budget',
        name: 'budget',
        component: () => import('@/views/BudgetView.vue'),
        meta: {
            layout: 'app',
            requiresAuth: true
        },
    },
    {
        path: '/ai-recommendation',
        name: 'ai-recommendation',
        component: () => import('@/views/AskAIView.vue'),
        meta: {
            layout: 'app',
            requiresAuth: true
        },
    },
    {
        path: '/about',
        name: 'about',
        component: () => import('@/views/AboutView.vue'),
        meta: {
            layout: 'app',
            requiresAuth: true
        },
    },
    {
        path: '/support',
        name: 'support',
        component: () => import('@/views/SupportLiveView.vue'),
        meta: {
            layout: 'app',
            requiresAuth: true
        },
    },
    {
        path: '/technician/claims',
        name: 'technician-claims',
        component: () => import('@/views/technician/TechnicianClaimsLiveView.vue'),
        meta: {
            layout: 'app',
            requiresAuth: true,
            requiresRole: 'technician'
        },
    },
    {
        path: '/technician/chat',
        name: 'technician-chat',
        component: () => import('@/views/technician/TechnicianChatView.vue'),
        meta: {
            layout: 'app',
            requiresAuth: true,
            requiresRole: 'technician'
        },
    },
    {
        path: '/admin/claims',
        name: 'admin-claims',
        component: () => import('@/views/admin/AdminClaimsLiveView.vue'),
        meta: {
            layout: 'app',
            requiresAuth: true,
            requiresRole: 'admin'
        },
    },
    {
        path: '/admin/technicians',
        name: 'admin-technicians',
        component: () => import('@/views/admin/AdminTechniciansLiveView.vue'),
        meta: {
            layout: 'app',
            requiresAuth: true,
            requiresRole: 'admin'
        },
    },
    {
        path: '/admin/products',
        name: 'admin-products',
        component: () => import('@/views/admin/AdminProductsView.vue'),
        meta: {
            layout: 'app',
            requiresAuth: true,
            requiresRole: 'admin'
        },
    },
    {
        path: '/admin/ratings',
        name: 'admin-ratings',
        component: () => import('@/views/admin/AdminRatingsLiveView.vue'),
        meta: {
            layout: 'app',
            requiresAuth: true,
            requiresRole: 'admin'
        },
    },
    {
        path: '/admin/labor-rate',
        name: 'admin-labor-rate',
        component: () => import('@/views/admin/AdminLaborRateView.vue'),
        meta: {
            layout: 'app',
            requiresAuth: true,
            requiresRole: 'admin'
        },
    },
    {
        path: '/forbidden',
        name: 'forbidden',
        component: () => import('@/views/ForbiddenView.vue'),
        meta: {
            layout: 'simple',
            requiresAuth: false
        },
    },
    {
        path: '/',
        redirect: '/login',
    },

];

const router = createRouter({
    history: createWebHistory(''),
    routes: routes,
});

router.beforeEach((to) => {
    if (!to.meta.layout) {
        // TODO: mostrar pantallas de error
        console.error('No layout was set for this page.')
    }

    const authStore = useAuthStore();
    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return { name: 'login' };
    }


    if (to.meta.requiresRole && authStore.role !== to.meta.requiresRole) {
        return { name: 'forbidden' };
    }
});
export default router;
