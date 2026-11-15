<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Finance\FinanceReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Finance extends Controller
{
    public function __construct(private FinanceReportService $reports) {}

    public function overview(): Response
    {
        $monthly = $this->reports->monthly(6);

        return Inertia::render('Admin/Finance/Overview', [
            'allTime' => $this->reports->summary(),
            'thisMonth' => end($monthly),
            'monthly' => $monthly,
        ]);
    }

    public function transactions(Request $request): Response
    {
        return Inertia::render('Admin/Finance/Transactions', [
            'transactions' => $this->transactionQuery($request)
                ->with(['shop:id,name', 'user:id,name', 'logisticsPartner:id,name'])
                ->latest('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Transaction $transaction) => $this->transactionRow($transaction)),
            'filters' => [
                'type' => TransactionType::tryFrom((string) $request->query('type'))?->value,
                'from' => $request->date('from')?->toDateString(),
                'to' => $request->date('to')?->toDateString(),
                'search' => trim((string) $request->query('search')),
            ],
            'types' => TransactionType::options(),
        ]);
    }

    /**
     * CSV of the filtered transactions, streamed in chunks so large ledgers export safely.
     */
    public function exportTransactions(Request $request): StreamedResponse
    {
        $query = $this->transactionQuery($request)->with(['shop:id,name', 'user:id,name', 'logisticsPartner:id,name'])->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Date', 'Type', 'Direction', 'Amount', 'Shop', 'User', 'Logistics partner', 'Balance after', 'Description']);

            $query->chunk(500, function ($transactions) use ($out) {
                foreach ($transactions as $transaction) {
                    $row = $this->transactionRow($transaction);
                    fputcsv($out, [$row['id'], $row['created_at'], $row['type'], $row['direction'], $row['amount'], $row['shop'], $row['user'], $row['partner'], $row['balance_after'], $row['description']]);
                }
            });

            fclose($out);
        }, 'onetap-transactions-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function reports(): Response
    {
        return Inertia::render('Admin/Finance/Reports', ['monthly' => array_reverse($this->reports->monthly(12))]);
    }

    public function exportReport(): StreamedResponse
    {
        $rows = $this->reports->monthly(12);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Month', 'GMV', 'Commission', 'Seller earnings', 'Logistics fees', 'Refunds', 'Paid out to sellers']);

            foreach ($rows as $row) {
                fputcsv($out, [$row['month'], $row['gmv'], $row['commission'], $row['sellerEarnings'], $row['logisticsFees'], $row['refunds'], $row['paidOut']]);
            }

            fclose($out);
        }, 'onetap-financial-report-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return Builder<Transaction>
     */
    private function transactionQuery(Request $request): Builder
    {
        $type = TransactionType::tryFrom((string) $request->query('type'));
        $from = $request->date('from');
        $to = $request->date('to');
        $search = trim((string) $request->query('search'));

        return Transaction::query()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('description', 'like', "%{$search}%")
                ->orWhereHas('shop', fn ($shops) => $shops->where('name', 'like', "%{$search}%"))));
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionRow(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'direction' => $transaction->direction->value,
            'amount' => $transaction->amount,
            'balance_after' => $transaction->balance_after,
            'shop' => $transaction->shop?->name,
            'user' => $transaction->user?->name,
            'partner' => $transaction->logisticsPartner?->name,
            'description' => $transaction->description,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }
}
