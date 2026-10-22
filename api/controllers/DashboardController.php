<?php
require_once __DIR__ . '/../models/LicenseLogModel.php';
require_once __DIR__ . '/../models/LicenseModel.php';
require_once __DIR__ . '/../models/SubscriptionModel.php';
require_once __DIR__ . '/../models/AnalyticsModel.php';
require_once __DIR__ . '/../helpers/response.php';

class DashboardController {
    public function index() {
        $logModel = new LicenseLogModel();
        $licModel = new LicenseModel();
        $subModel = new SubscriptionModel();
        $analyticsModel = new AnalyticsModel();

        $stats = $logModel->getDashboardStats();
        $recentLogs = $logModel->getAll(10);
        $recentLicenses = array_slice($licModel->getAll(), 0, 5);

        $analytics = [
            'expiring_radar' => $analyticsModel->getExpiringRadar(),
            'expiring_list' => $analyticsModel->getExpiringAttentionList(5),
            'revenue_by_app' => $analyticsModel->getRevenueByApplication(),
            'license_statuses' => $analyticsModel->getLicenseStatusDistribution(),
            'module_popularity' => $analyticsModel->getModulePopularity(7),
            'monthly_trend' => $analyticsModel->getMonthlyRevenueTrend(),
            'billing_cycles' => $analyticsModel->getBillingCycleDistribution(),
            'user_tiers' => $analyticsModel->getUserTierDistribution(),
            'api_health' => $analyticsModel->getApiHealthStats(),
            'instance_activity' => $analyticsModel->getInstanceActivityStats()
        ];

        sendJsonResponse([
            'stats' => $stats,
            'analytics' => $analytics,
            'recent_logs' => $recentLogs,
            'recent_licenses' => $recentLicenses
        ], 200, 'Dashboard data loaded successfully.');
    }
}
