<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Pdv\Application\GeneratePdvCatalogPublication;
use App\Modules\Pdv\Domain\Models\PdvCatalogDelivery;
use App\Modules\Pdv\Domain\Models\PdvCatalogPublication;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PdvCatalogLoadController extends Controller
{
    private const ONLINE_SECONDS = 90;

    private const DELAYED_SECONDS = 300;

    public function index(Request $request, TenantContext $context)
    {
        abort_unless(config('pdv_catalog.enabled', false), 404);
        $tenantId = $context->id();
        $this->authorizePage($request, $tenantId);

        Gate::forUser($request->user())->authorize('viewAny', Company::class);
        $companies = Company::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('trade_name')->get()
            ->filter(fn (Company $candidate) => Gate::forUser($request->user())->allows('view', $candidate));
        $companyId = (string) $request->query('company_id', $companies->first()?->id);
        $company = $companies->firstWhere('id', $companyId);
        abort_unless($company, 404);

        $branches = Branch::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('company_id', $company->id)->where('status', 'ACTIVE')->with('company')->orderBy('name')->get()
            ->filter(fn (Branch $branch) => Gate::forUser($request->user())->allows('view', $branch));
        $terminals = Terminal::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('company_id', $company->id)
            ->with(['branch.company', 'company'])->orderBy('name')->get()
            ->filter(fn (Terminal $terminal) => Gate::forUser($request->user())->allows('view', $terminal));
        $terminalIds = $terminals->modelKeys();
        $states = DB::table('pdv_terminal_catalog_states as state')
            ->join('pdv_catalog_publications as publication', 'publication.id', '=', 'state.publication_id')
            ->whereIn('state.terminal_id', $terminalIds)
            ->get(['state.terminal_id', 'state.version', 'state.applied_at', 'publication.product_count', 'publication.price_count', 'publication.package_count'])
            ->keyBy('terminal_id');
        $latestDeliveries = PdvCatalogDelivery::query()->whereIn('terminal_id', $terminalIds)
            ->whereRaw('pdv_catalog_deliveries.id = (select latest.id from pdv_catalog_deliveries as latest where latest.terminal_id = pdv_catalog_deliveries.terminal_id order by latest.created_at desc, latest.id desc limit 1)')
            ->with('publication')->get()->keyBy('terminal_id');
        $terminals = $terminals->map(function (Terminal $terminal) use ($states, $latestDeliveries): Terminal {
            $seconds = $terminal->last_seen_at === null || $terminal->last_seen_at->isFuture()
                ? null : $terminal->last_seen_at->diffInSeconds(now());
            $terminal->connectivity = $terminal->status !== Terminal::STATUS_ACTIVE ? 'not_operational'
                : ($seconds === null || $seconds > self::DELAYED_SECONDS ? 'offline'
                    : ($seconds > self::ONLINE_SECONDS ? 'delayed' : 'online'));
            $terminal->connectivity_label = match ($terminal->connectivity) {
                'online' => 'online', 'delayed' => 'comunicação atrasada', 'offline' => 'offline', default => 'não operacional',
            };
            $terminal->catalog_state = $states->get($terminal->id);
            $terminal->last_delivery = $latestDeliveries->get($terminal->id);

            return $terminal;
        });

        $publications = PdvCatalogPublication::query()->where('tenant_id', $tenantId)->where('company_id', $company->id)
            ->with('deliveries')->withCount('deliveries')->latest()->paginate(15)->withQueryString();

        return view('pdv.catalog-loads.index', [
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [['label' => 'central de cargas pdv']],
            'companies' => $companies,
            'company' => $company,
            'branches' => $branches,
            'terminals' => $terminals,
            'publications' => $publications,
            'canPublish' => $request->user()->hasPermission('manage-products', $tenantId),
            'onlineSeconds' => self::ONLINE_SECONDS,
        ]);
    }

    public function store(Request $request, TenantContext $context)
    {
        abort_unless(config('pdv_catalog.enabled', false), 404);
        $tenantId = $context->id();
        $this->authorizePage($request, $tenantId);
        abort_unless($request->user()->hasPermission('manage-products', $tenantId), 403);

        $validated = $request->validate([
            'company_id' => ['required', 'string', 'exists:companies,id'],
            'terminal_ids' => ['required', 'array', 'min:1', 'max:50'],
            'terminal_ids.*' => ['required', 'string', 'distinct', 'exists:terminals,id'],
            'schedule_offline' => ['sometimes', 'accepted'],
        ]);
        $company = Company::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->find($validated['company_id']);
        abort_unless($company, 404);
        Gate::authorize('view', $company);

        $terminals = Terminal::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('company_id', $company->id)
            ->whereIn('id', $validated['terminal_ids'])->with('branch')->get();
        if ($terminals->count() !== count($validated['terminal_ids'])) {
            abort(404);
        }
        foreach ($terminals as $terminal) {
            Gate::authorize('update', $terminal);
            if ($terminal->status !== Terminal::STATUS_ACTIVE || $terminal->installation_id === null
                || ! $terminal->branch?->isActive() || ! $company->isActive()) {
                throw ValidationException::withMessages(['terminal_ids' => 'Selecione apenas terminais ativos, instalados e vinculados a uma filial operacional.']);
            }
            $recent = $terminal->last_seen_at && $terminal->last_seen_at->greaterThanOrEqualTo(now()->subSeconds(self::ONLINE_SECONDS));
            if (! $recent && ! ($request->boolean('schedule_offline') && $request->input('schedule_offline') === '1')) {
                throw ValidationException::withMessages(['schedule_offline' => 'Marque explicitamente para agendar a entrega aos terminais offline.']);
            }
        }

        $publication = DB::transaction(function () use ($tenantId, $company, $terminals, $request): PdvCatalogPublication {
            DB::table('companies')->where('id', $company->id)->lockForUpdate()->first();
            $generating = PdvCatalogPublication::query()->where('company_id', $company->id)->where('status', PdvCatalogPublication::GENERATING)->lockForUpdate()->exists();
            if ($generating) {
                throw ValidationException::withMessages(['publication' => 'Já existe uma publicação em geração para esta empresa. Aguarde sua conclusão.']);
            }
            $version = (int) PdvCatalogPublication::query()->where('company_id', $company->id)->max('version') + 1;
            $publication = PdvCatalogPublication::query()->create([
                'id' => (string) Str::ulid(), 'tenant_id' => $tenantId, 'company_id' => $company->id,
                'created_by' => $request->user()->id, 'version' => $version, 'schema_version' => 1,
                'status' => PdvCatalogPublication::GENERATING, 'product_count' => 0, 'price_count' => 0, 'package_count' => 0,
            ]);
            foreach ($terminals as $terminal) {
                PdvCatalogDelivery::query()->create([
                    'id' => (string) Str::ulid(), 'publication_id' => $publication->id, 'terminal_id' => $terminal->id,
                    'status' => PdvCatalogDelivery::PENDING,
                ]);
            }
            AuditLog::logAction('publish', 'pdv_catalog_publication', $publication->id, [
                'version' => $version, 'company_id' => $company->id, 'terminal_ids' => $terminals->modelKeys(),
                'recipient_count' => $terminals->count(),
            ], 'Publicação de catálogo PDV solicitada.');

            return $publication;
        });

        try {
            GeneratePdvCatalogPublication::dispatch($publication->id);
        } catch (\Throwable $exception) {
            $publication->update(['status' => PdvCatalogPublication::FAILED, 'failure_code' => 'QUEUE_DISPATCH_FAILED']);
            report(new \RuntimeException('Falha ao enfileirar publicação de catálogo '.$publication->id, previous: $exception));
            throw ValidationException::withMessages(['publication' => 'Não foi possível iniciar a geração da carga. Nenhuma carga foi disponibilizada.']);
        }

        return redirect()->route('pdv.catalog-loads.index', ['company_id' => $company->id])
            ->with('sucesso', 'Geração da carga iniciada. A página mostrará o resultado quando estiver pronta.');
    }

    private function authorizePage(Request $request, string $tenantId): void
    {
        abort_unless($request->user()->canAccessTenant($tenantId), 403);
        Gate::forUser($request->user())->authorize('viewAny', Terminal::class);
        Gate::forUser($request->user())->authorize('viewAny', Company::class);
        abort_unless($request->user()->hasAnyPermission(['view-products', 'manage-products'], $tenantId), 403);
    }
}
