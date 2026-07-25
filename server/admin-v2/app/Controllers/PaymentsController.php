<?php
/**
 * Payment operations — READ-ONLY over the real payments table. Identifiers are masked;
 * full M-Pesa receipts are revealed only to holders of payments.export. Admin V2 never
 * writes payments and never marks an unverified payment successful.
 */

namespace App\Controllers;

use App\Core\Rbac;
use App\Core\Request;
use App\Repositories\PaymentRepository;
use App\Support\Csv;

final class PaymentsController extends Controller
{
    public function index(Request $request): void
    {
        $this->guard('payments.view');
        $filters = [
            'from' => (string) $request->get('from', ''),
            'to' => (string) $request->get('to', ''),
            'state' => (string) $request->get('state', ''),
            'q' => (string) $request->get('q', ''),
            'min' => $request->get('min', ''),
            'max' => $request->get('max', ''),
        ];
        $page = max(1, (int) $request->get('page', 1));
        $result = PaymentRepository::search($filters, $page, 25);
        $this->view('payments/index', [
            'activeNav' => 'payments', 'pageTitle' => 'Payments',
            'available' => PaymentRepository::available(),
            'rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'per' => 25,
            'filters' => $filters, 'canReveal' => Rbac::can('payments.export'),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $this->guard('payments.view');
        $row = PaymentRepository::find((int) $id);
        if (!$row) { $this->redirect('/payments'); }
        $this->view('payments/show', [
            'activeNav' => 'payments', 'pageTitle' => 'Payment #' . (int) $id,
            'p' => $row, 'canReveal' => Rbac::can('payments.export'),
        ]);
    }

    public function exportCsv(Request $request): void
    {
        $this->guard('payments.export');
        $result = PaymentRepository::search([
            'from' => (string) $request->get('from', ''), 'to' => (string) $request->get('to', ''),
            'state' => (string) $request->get('state', ''), 'q' => (string) $request->get('q', ''),
        ], 1, 5000);
        Csv::stream('mybingwa-payments.csv',
            ['id', 'time_nairobi', 'payer_masked', 'recipient_masked', 'offer', 'amount', 'status', 'receipt'],
            array_map(fn($r) => [
                $r['id'], fmt_nairobi($r['created_at'], 'Y-m-d H:i'),
                str_mask_phone($r['payer']), str_mask_phone($r['recipient']),
                $r['offer_id'], $r['amount'],
                PaymentRepository::displayState($r['status'])['label'],
                $r['mpesa_receipt'] ?: '',
            ], $result['rows'])
        );
    }
}
