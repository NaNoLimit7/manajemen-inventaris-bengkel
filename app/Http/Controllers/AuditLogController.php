<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Item;
use App\Models\StockDocument;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * @var array<class-string, string>
     */
    private const TYPE_LABELS = [
        Item::class => 'Barang',
        Supplier::class => 'Supplier',
        Customer::class => 'Pelanggan',
        StockDocument::class => 'Dokumen Stok',
    ];

    public function index(Request $request): Response
    {
        $event = trim((string) $request->string('event'));
        $type = trim((string) $request->string('type'));
        $dateFrom = trim((string) $request->string('date_from'));
        $dateTo = trim((string) $request->string('date_to'));

        $logs = AuditLog::query()
            ->when($event !== '', fn (Builder $query) => $query->where('event', $event))
            ->when($type !== '', fn (Builder $query) => $query->where('auditable_type', $type))
            ->when($dateFrom !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Reports/Audit', [
            'logs' => $logs,
            'events' => AuditLog::query()
                ->select('event')
                ->distinct()
                ->orderBy('event')
                ->pluck('event'),
            'types' => AuditLog::query()
                ->select('auditable_type')
                ->distinct()
                ->orderBy('auditable_type')
                ->pluck('auditable_type')
                ->map(fn (string $type): array => [
                    'value' => $type,
                    'label' => self::TYPE_LABELS[$type] ?? Str::headline(class_basename($type)),
                ])
                ->values(),
            'filters' => [
                'event' => $event,
                'type' => $type,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}
