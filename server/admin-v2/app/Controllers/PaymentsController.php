<?php
/**
 * Payment operations over the real payments table. For reconciliation, the full
 * identifiers (payer, recipient, M-Pesa receipt) are shown unmasked to holders of
 * payments.view. The one write is deleting a payment record — CSRF-checked, guarded by
 * payments.export and audited. Admin V2 never marks an unverified payment successful.
 */

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Flash;
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
            'canDelete' => Rbac::can('payments.export'),
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
            'canDelete' => Rbac::can('payments.export'),
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
            ['id', 'time_nairobi', 'payer', 'recipient', 'offer', 'amount', 'status', 'receipt'],
            array_map(fn($r) => [
                $r['id'], fmt_nairobi($r['created_at'], 'Y-m-d H:i'),
                $r['payer'], $r['recipient'] ?: $r['payer'],
                $r['offer_id'], $r['amount'],
                PaymentRepository::displayState($r['status'])['label'],
                $r['mpesa_receipt'] ?: '',
            ], $result['rows'])
        );
    }

    /**
     * Delete a payment record. Admin V2 is otherwise read-only over payments, so this is
     * a deliberate capability: CSRF-checked, guarded by the strongest payments permission
     * (payments.export) and fully audited with the deleted row captured before removal.
     */
    public function delete(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('payments.export');
        $row = PaymentRepository::find((int) $id);
        if (!$row) {
            Flash::error('Payment record not found.');
            $this->redirect('/payments');
        }
        PaymentRepository::delete((int) $id);
        Audit::log([
            'action' => 'payment.delete',
            'entity_type' => 'payment',
            'entity_id' => (int) $id,
            'before' => $row,
            'after' => null,
        ]);
        Flash::success('Payment record deleted.');
        $this->redirect('/payments');
    }
}
