<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Automation\Domain\Models\Notification;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationWebController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', Notification::class);

        $avisos = Notification::query()
            ->visibleTo($request->user()->id)
            ->with('rule')
            ->when($request->input('status') === 'nao_lidos', fn ($query) => $query->unread())
            ->when($request->input('status') === 'lidos', fn ($query) => $query->whereNotNull('read_at'))
            ->when($request->filled('level'), fn ($query) => $query->where('level', $request->string('level')->toString()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', [
            'avisos' => $avisos,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [
                ['label' => 'dashboard', 'url' => route('dashboard')],
                ['label' => 'avisos', 'url' => route('notifications.index')],
            ],
            'naoLidos' => self::naoLidos($request->user()->id),
            'statusOptions' => ['' => 'todos', 'nao_lidos' => 'não lidos', 'lidos' => 'lidos'],
            'niveis' => ['' => 'todos'] + Notification::NIVEIS,
        ]);
    }

    public function read(Notification $notification)
    {
        Gate::authorize('update', $notification);

        if (! $notification->isRead()) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return back()->with('sucesso', 'aviso marcado como lido.');
    }

    public function readAll(Request $request)
    {
        Gate::authorize('viewAny', Notification::class);

        Notification::query()
            ->visibleTo($request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return back()->with('sucesso', 'todos os avisos foram marcados como lidos.');
    }

    /**
     * Contador usado pelo menu lateral.
     *
     * Fica aqui, e não numa view composer, para que a barra lateral não faça
     * consulta em página que nem mostra o contador.
     */
    public static function naoLidos(string $userId): int
    {
        return Notification::query()->visibleTo($userId)->unread()->count();
    }
}
