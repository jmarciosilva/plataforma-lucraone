<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Sales\Http\Requests\StoreCustomerRequest;
use App\Modules\Sales\Http\Resources\CustomerResource;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class CustomerController extends Controller
{
    public function __construct(
        private TenantContext $context
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $customers = Customer::query()
            ->withCount('orders')
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->string('company_id')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20);

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create([
            ...$request->validated(),
            'tenant_id' => $this->context->id(),
            'status' => $request->validated('status') ?? Customer::STATUS_ACTIVE,
        ]);

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): CustomerResource
    {
        $customer = Customer::query()->withCount('orders')->findOrFail($id);

        return new CustomerResource($customer);
    }
}
