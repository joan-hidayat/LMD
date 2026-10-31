<?php
require_once __DIR__ . '/../models/PaymentModel.php';
require_once __DIR__ . '/../helpers/response.php';

class PaymentController {
    /**
     * POST /payments/webhook/{provider} — notifikasi dari payment gateway.
     * 200 juga untuk notifikasi duplikat, supaya provider berhenti mengirim ulang.
     */
    public function webhook($provider) {
        $headers = array_change_key_case(function_exists('getallheaders') ? (getallheaders() ?: []) : [], CASE_LOWER);
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        try {
            $result = (new PaymentModel())->handleNotification($provider, file_get_contents('php://input'), $headers);
            sendJsonResponse($result, 200, 'Notification processed.');
        } catch (PaymentSignatureException $e) {
            sendJsonResponse(null, 401, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            sendJsonResponse(null, 400, $e->getMessage());
        } catch (OutOfBoundsException $e) {
            sendJsonResponse(null, 404, $e->getMessage());
        } catch (DomainException $e) {
            sendJsonResponse(null, 409, $e->getMessage());
        }
    }
}
