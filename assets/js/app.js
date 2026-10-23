/**
 * Central SaaS Admin Dashboard - Vue 3 App Logic
 */
const { createApp } = Vue;

createApp({
    data() {
        return {
            activePage: 'dashboard',
            searchQuery: '',
            
            // Dashboard Stats
            stats: {
                total_apps: 0,
                total_customers: 0,
                active_subscriptions: 0,
                active_licenses: 0,
                active_instances: 0,
                total_revenue: 0
            },

            analyticsTab: 'executive',
            guideTab: 'manual',

            // Analytics for Decision-Making Charts
            analytics: {
                expiring_radar: { expired: 0, critical_7d: 0, warning_30d: 0, safe_90d: 0, long_term: 0 },
                expiring_list: [],
                revenue_by_app: [],
                license_statuses: { active: 0, expired: 0, suspended: 0, revoked: 0 },
                module_popularity: [],
                monthly_trend: [],
                billing_cycles: [],
                user_tiers: { starter: 0, medium: 0, enterprise: 0 },
                api_health: { success: 0, failed: 0 },
                instance_activity: { active_24h: 0, idle_7d: 0, dormant: 0 }
            },
            chartInstances: {},
            
            // Data Collections
            applications: [],
            modules: [],
            packages: [],
            customers: [],
            subscriptions: [],
            licenses: [],
            auditLogs: [],
            
            // Active Selection
            selectedAppId: null,
            selectedAppModules: [],

            // Modal States
            modalType: null,
            modalTitle: '',
            showModal: false,

            // Form Models
            appForm: { code: '', name: '', description: '', status: 'active' },
            moduleForm: { application_id: '', code: '', name: '', module_type: 'standard', description: '', sort_order: 0 },
            packageForm: { application_id: '', code: '', name: '', base_price: 500000, billing_cycle: 'monthly', max_users: 5, future_module_access: false, is_public: true, module_ids: [] },
            customerForm: { code: '', name: '', email: '', phone: '', status: 'active' },
            subForm: { customer_id: '', package_id: '', start_date: '', end_date: '', max_users: 5, base_price: 0, discount_amount: 0 },

            // License Validation & Registration Tester Model
            tester: {
                license_key: '',
                instance_key: 'INST-DEMO-001',
                domain: 'apotek-demo.com',
                result: null,
                loading: false
            }
        };
    },
    async mounted() {
        console.log('Central SaaS Dashboard Mounted. Loading data...');
        await this.fetchDashboardData();
        await this.fetchAllData();
    },
    methods: {
        setActivePage(page) {
            this.activePage = page;
            if (page === 'dashboard') this.fetchDashboardData();
            if (page === 'applications') this.fetchApplications();
            if (page === 'packages') this.fetchPackages();
            if (page === 'customers') this.fetchCustomers();
            if (page === 'subscriptions') this.fetchSubscriptions();
            if (page === 'licenses') this.fetchLicenses();
            if (page === 'audit') this.fetchAuditLogs();
        },

        // API Fetching Methods
        async fetchDashboardData() {
            try {
                const res = await fetch('api/index.php/dashboard');
                const json = await res.json();
                if (json.success) {
                    this.stats = json.data.stats;
                    if (json.data.analytics) {
                        this.analytics = json.data.analytics;
                    }
                    this.auditLogs = json.data.recent_logs;
                    this.licenses = json.data.recent_licenses;
                    this.$nextTick(() => {
                        if (this.analyticsTab === 'executive') {
                            this.renderCharts();
                        } else {
                            this.renderPhase2Charts();
                        }
                    });
                }
            } catch (err) {
                console.error('Failed to load dashboard data:', err);
            }
        },

        setAnalyticsTab(tab) {
            this.analyticsTab = tab;
            this.$nextTick(() => {
                if (tab === 'executive') {
                    this.renderCharts();
                } else {
                    this.renderPhase2Charts();
                }
            });
        },

        async fetchAllData() {
            await this.fetchApplications();
            await this.fetchPackages();
            await this.fetchCustomers();
            await this.fetchSubscriptions();
            await this.fetchLicenses();
            await this.fetchAuditLogs();
        },

        async fetchApplications() {
            try {
                const res = await fetch('api/index.php/applications');
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    this.applications = json.data;
                    if (this.applications.length > 0) {
                        const targetId = this.selectedAppId || this.applications[0].id;
                        this.selectApplication(targetId);
                    }
                }
            } catch (err) {
                console.error('Error fetching applications:', err);
            }
        },

        async selectApplication(appId) {
            this.selectedAppId = appId;
            try {
                const res = await fetch(`api/index.php/applications/${appId}/modules`);
                const json = await res.json();
                if (json.success) {
                    this.selectedAppModules = json.data;
                }
            } catch (err) {
                console.error('Error fetching modules:', err);
            }
        },

        async fetchPackages() {
            try {
                const res = await fetch('api/index.php/packages');
                const json = await res.json();
                if (json.success) this.packages = json.data;
            } catch (err) {
                console.error('Error fetching packages:', err);
            }
        },

        async fetchCustomers() {
            try {
                const res = await fetch('api/index.php/customers');
                const json = await res.json();
                if (json.success) this.customers = json.data;
            } catch (err) {
                console.error('Error fetching customers:', err);
            }
        },

        async fetchSubscriptions() {
            try {
                const res = await fetch('api/index.php/subscriptions');
                const json = await res.json();
                if (json.success) this.subscriptions = json.data;
            } catch (err) {
                console.error('Error fetching subscriptions:', err);
            }
        },

        async fetchLicenses() {
            try {
                const res = await fetch('api/index.php/licenses');
                const json = await res.json();
                if (json.success) this.licenses = json.data;
            } catch (err) {
                console.error('Error fetching licenses:', err);
            }
        },

        async fetchAuditLogs() {
            try {
                const res = await fetch('api/index.php/audit');
                const json = await res.json();
                if (json.success) this.auditLogs = json.data;
            } catch (err) {
                console.error('Error fetching audit logs:', err);
            }
        },

        // Modal Handlers
        openModal(type) {
            this.modalType = type;
            this.showModal = true;
            if (type === 'app') {
                this.modalTitle = 'Tambah Aplikasi Baru';
                this.appForm = { code: '', name: '', description: '', status: 'active' };
            } else if (type === 'module') {
                this.modalTitle = 'Tambah Modul Baru';
                this.moduleForm = { application_id: this.selectedAppId || (this.applications[0]?.id || ''), code: '', name: '', module_type: 'standard', description: '', sort_order: 0 };
            } else if (type === 'package') {
                this.modalTitle = 'Tambah Paket Penjualan';
                this.packageForm = { application_id: this.applications[0]?.id || '', code: '', name: '', base_price: 500000, billing_cycle: 'monthly', max_users: 5, future_module_access: false, is_public: true, module_ids: [] };
            } else if (type === 'customer') {
                this.modalTitle = 'Tambah Customer Baru';
                this.customerForm = { code: '', name: '', email: '', phone: '', status: 'active' };
            } else if (type === 'subscription') {
                this.modalTitle = 'Buat Subscription Baru';
                const today = new Date().toISOString().split('T')[0];
                const nextYear = new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toISOString().split('T')[0];
                this.subForm = { customer_id: this.customers[0]?.id || '', package_id: this.packages[0]?.id || '', start_date: today, end_date: nextYear, max_users: 10, base_price: 750000, discount_amount: 0 };
            } else if (type === 'tester') {
                this.modalTitle = 'Tester License API Validation';
                this.tester.license_key = this.licenses[0]?.license_key || '';
                this.tester.result = null;
            }
        },

        closeModal() {
            this.showModal = false;
        },

        // Form Submit Actions
        async submitApp() {
            try {
                const res = await fetch('api/index.php/applications', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.appForm)
                });
                const json = await res.json();
                if (json.success) {
                    this.closeModal();
                    await this.fetchApplications();
                    await this.fetchDashboardData();
                    alert('Aplikasi berhasil dibuat!');
                } else {
                    alert('Gagal: ' + json.message);
                }
            } catch (err) {
                alert('Error submitting application.');
            }
        },

        async submitModule() {
            try {
                const res = await fetch('api/index.php/applications/modules', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.moduleForm)
                });
                const json = await res.json();
                if (json.success) {
                    this.closeModal();
                    if (this.moduleForm.application_id) {
                        await this.selectApplication(this.moduleForm.application_id);
                    }
                    await this.fetchApplications();
                    alert('Modul berhasil ditambahkan!');
                } else {
                    alert('Gagal: ' + json.message);
                }
            } catch (err) {
                alert('Error submitting module.');
            }
        },

        async submitPackage() {
            try {
                const res = await fetch('api/index.php/packages', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.packageForm)
                });
                const json = await res.json();
                if (json.success) {
                    this.closeModal();
                    await this.fetchPackages();
                    alert('Paket penjualan berhasil ditambahkan!');
                } else {
                    alert('Gagal: ' + json.message);
                }
            } catch (err) {
                alert('Error submitting package.');
            }
        },

        async submitCustomer() {
            try {
                const res = await fetch('api/index.php/customers', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.customerForm)
                });
                const json = await res.json();
                if (json.success) {
                    this.closeModal();
                    await this.fetchCustomers();
                    await this.fetchDashboardData();
                    alert('Customer berhasil dibuat!');
                } else {
                    alert('Gagal: ' + json.message);
                }
            } catch (err) {
                alert('Error submitting customer.');
            }
        },

        async submitSubscription() {
            try {
                const res = await fetch('api/index.php/subscriptions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.subForm)
                });
                const json = await res.json();
                if (json.success) {
                    this.closeModal();
                    await this.fetchSubscriptions();
                    await this.fetchLicenses();
                    await this.fetchCustomers();
                    await this.fetchDashboardData();
                    alert(`Subscription berhasil dibuat!\nLicense Key: ${json.data.license_key}`);
                } else {
                    alert('Gagal: ' + json.message);
                }
            } catch (err) {
                alert('Error creating subscription.');
            }
        },

        async testLicenseValidation() {
            this.tester.loading = true;
            this.tester.result = null;
            try {
                const res = await fetch('api/index.php/licenses/validate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        license_key: this.tester.license_key,
                        instance_key: this.tester.instance_key,
                        domain: this.tester.domain
                    })
                });
                const json = await res.json();
                this.tester.result = json;
                await this.fetchAuditLogs();
                await this.fetchDashboardData();
            } catch (err) {
                this.tester.result = { success: false, message: 'API connection failed.' };
            } finally {
                this.tester.loading = false;
            }
        },

        // Helper Formatters
        formatRupiah(val) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        scrollToAttentionList() {
            const el = document.getElementById('attentionSection');
            if (el) el.scrollIntoView({ behavior: 'smooth' });
        },

        getDaysLeftBadge(daysLeft, status) {
            if (status === 'expired' || daysLeft < 0) {
                return { text: `Expired (${Math.abs(daysLeft)} hari lalu)`, class: 'badge-expired' };
            }
            if (daysLeft <= 7) {
                return { text: `🚨 Kritis: ${daysLeft} Hari Lagi`, class: 'badge-critical' };
            }
            if (daysLeft <= 30) {
                return { text: `⚠️ ${daysLeft} Hari Lagi`, class: 'badge-warning' };
            }
            return { text: `✅ ${daysLeft} Hari Lagi`, class: 'badge-safe' };
        },

        // Chart Rendering Engine (Chart.js)
        renderCharts() {
            if (typeof Chart === 'undefined') return;

            // Global Chart.js Dark Mode Aesthetics
            Chart.defaults.color = '#94a3b8';
            Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
            Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.06)';

            const setupChart = (id, config) => {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                if (this.chartInstances[id]) {
                    this.chartInstances[id].destroy();
                }
                this.chartInstances[id] = new Chart(canvas, config);
            };

            // 1. Radar Jatuh Tempo Lisensi (Expiration Radar) - Bar Chart
            const radar = this.analytics.expiring_radar || {};
            setupChart('chartExpiringRadar', {
                type: 'bar',
                data: {
                    labels: ['Expired', '≤ 7 Hari (Kritis)', '8-30 Hari', '31-90 Hari', '> 90 Hari'],
                    datasets: [{
                        label: 'Jumlah Lisensi',
                        data: [
                            radar.expired || 0,
                            radar.critical_7d || 0,
                            radar.warning_30d || 0,
                            radar.safe_90d || 0,
                            radar.long_term || 0
                        ],
                        backgroundColor: [
                            '#ef4444', // Red
                            '#f97316', // Orange
                            '#eab308', // Amber
                            '#3b82f6', // Blue
                            '#10b981'  // Emerald
                        ],
                        borderRadius: 6,
                        maxBarThickness: 45
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ` ${ctx.raw} Lisensi Tenant`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            // 2. Revenue Contribution per Application - Doughnut Chart
            const revApps = this.analytics.revenue_by_app || [];
            setupChart('chartRevenueApp', {
                type: 'doughnut',
                data: {
                    labels: revApps.map(a => a.application_name),
                    datasets: [{
                        data: revApps.map(a => parseFloat(a.total_revenue) || 0),
                        backgroundColor: ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899'],
                        borderWidth: 3,
                        borderColor: '#0c1019',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 14,
                                font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ` ${ctx.label}: Rp ${new Intl.NumberFormat('id-ID').format(ctx.raw)}`
                            }
                        }
                    },
                    cutout: '70%'
                }
            });

            // 3. Monthly Revenue Trend - Line / Area Chart (Glowing Neon Curve)
            const trend = this.analytics.monthly_trend || [];
            setupChart('chartMonthlyTrend', {
                type: 'line',
                data: {
                    labels: trend.map(t => t.label),
                    datasets: [{
                        label: 'Pendapatan',
                        data: trend.map(t => parseFloat(t.revenue) || 0),
                        borderColor: '#06b6d4',
                        backgroundColor: 'rgba(6, 182, 212, 0.12)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#38bdf8',
                        pointBorderColor: '#0c1019',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7,
                        pointRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ` Omset: Rp ${new Intl.NumberFormat('id-ID').format(ctx.raw)}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (val) => 'Rp ' + (val >= 1000000 ? (val / 1000000).toFixed(0) + ' Jt' : val)
                            }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            // 4. Module Popularity - Horizontal Bar Chart
            const mods = this.analytics.module_popularity || [];
            setupChart('chartModulePopularity', {
                type: 'bar',
                data: {
                    labels: mods.map(m => m.module_name),
                    datasets: [{
                        label: 'Tenant Pengguna',
                        data: mods.map(m => parseInt(m.adoption_count) || 0),
                        backgroundColor: '#6366f1',
                        borderRadius: 5,
                        maxBarThickness: 16
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ` ${ctx.raw} Lisensi / Tenant`
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        }
                    }
                }
            });
        },

        // Phase 2: Advanced Business & Health Operations Charts
        renderPhase2Charts() {
            if (typeof Chart === 'undefined') return;

            const setupChart = (id, config) => {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                if (this.chartInstances[id]) {
                    this.chartInstances[id].destroy();
                }
                this.chartInstances[id] = new Chart(canvas, config);
            };

            // 5. Billing Cycle Distribution (Monthly vs Annual) - Doughnut
            const cycles = this.analytics.billing_cycles || [];
            const cycleLabels = cycles.map(c => c.billing_cycle === 'annual' ? 'Tahunan (Annual)' : 'Bulanan (Monthly)');
            const cycleCounts = cycles.map(c => parseInt(c.count) || 0);
            setupChart('chartBillingCycles', {
                type: 'doughnut',
                data: {
                    labels: cycleLabels.length ? cycleLabels : ['Bulanan', 'Tahunan'],
                    datasets: [{
                        data: cycleCounts.length ? cycleCounts : [0, 0],
                        backgroundColor: ['#6366f1', '#10b981', '#f59e0b'],
                        borderWidth: 3,
                        borderColor: '#0c1019',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" } } },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ` ${ctx.label}: ${ctx.raw} Kontrak Subskripsi`
                            }
                        }
                    },
                    cutout: '70%'
                }
            });

            // 6. Max Users Capacity Tiering - Bar Chart
            const tiers = this.analytics.user_tiers || { starter: 0, medium: 0, enterprise: 0 };
            setupChart('chartUserTiers', {
                type: 'bar',
                data: {
                    labels: ['Starter (1-3 User)', 'Medium (4-10 User)', 'Enterprise (>10 User)'],
                    datasets: [{
                        label: 'Jumlah Kontrak Tenant',
                        data: [tiers.starter || 0, tiers.medium || 0, tiers.enterprise || 0],
                        backgroundColor: ['#06b6d4', '#6366f1', '#a855f7'],
                        borderRadius: 6,
                        maxBarThickness: 45
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ` ${ctx.raw} Kontrak Pelanggan`
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // 7. API Validation Traffic Health (Success vs Failed) - Doughnut
            const apiH = this.analytics.api_health || { success: 0, failed: 0 };
            setupChart('chartApiHealth', {
                type: 'doughnut',
                data: {
                    labels: ['Validasi Sukses (Authorized)', 'Validasi Gagal (Blocked/Expired)'],
                    datasets: [{
                        data: [apiH.success || 0, apiH.failed || 0],
                        backgroundColor: ['#10b981', '#f43f5e'],
                        borderWidth: 3,
                        borderColor: '#0c1019',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" } } },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ` ${ctx.label}: ${ctx.raw} Requests`
                            }
                        }
                    },
                    cutout: '68%'
                }
            });

            // 8. Instance Activity Status (Online 24h, Idle, Dormant) - Bar Chart
            const instA = this.analytics.instance_activity || { active_24h: 0, idle_7d: 0, dormant: 0 };
            setupChart('chartInstanceActivity', {
                type: 'bar',
                data: {
                    labels: ['Aktif (< 24 Jam)', 'Siaga (1-7 Hari)', 'Dorman (> 7 Hari / Offline)'],
                    datasets: [{
                        label: 'Jumlah Instance Client',
                        data: [instA.active_24h || 0, instA.idle_7d || 0, instA.dormant || 0],
                        backgroundColor: ['#10b981', '#f59e0b', '#64748b'],
                        borderRadius: 6,
                        maxBarThickness: 45
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ` ${ctx.raw} Instance Client`
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    }
}).mount('#app');