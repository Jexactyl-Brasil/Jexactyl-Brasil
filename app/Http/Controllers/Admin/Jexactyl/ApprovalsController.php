<?php

namespace Pterodactyl\Http\Controllers\Admin\Jexactyl;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Jexactyl\ApprovalFormRequest;

class ApprovalsController extends Controller
{
    /**
     * ApprovalsController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private SettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * Render the Jexactyl referrals interface.
     */
    public function index(): View
    {
        $users = User::where('approved', false)->get();

        return view('admin.jexactyl.approvals', [
            'enabled' => $this->settings->get('jexactyl::approvals:enabled', false),
            'webhook' => $this->settings->get('jexactyl::approvals:webhook'),
            'users' => $users,
        ]);
    }

    /**
     * Updates the settings for approvals.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(ApprovalFormRequest $request): RedirectResponse
    {
        foreach ($request->normalize() as $key => $value) {
            $this->settings->set('jexactyl::approvals:' . $key, $value);
        }

        $this->alert->success('As configurações de aprovação do Jexactyl foram atualizadas.')->flash();

        return redirect()->route('admin.jexactyl.approvals');
    }

    /**
     * Perform a bulk action for approval status.
     */
    public function bulkAction(Request $request, string $action): RedirectResponse
    {
        if ($action === 'approve') {
            User::query()->where('approved', false)->update(['approved' => true]);
        } else {
            try {
                User::query()->where('approved', false)->delete();
            } catch (DisplayException $ex) {
                throw new DisplayException('Incapaz de completar a ação: ' . $ex->getMessage());
            }
        }

        $this->alert->success('Todos os usuários foram ' . $action === 'approve' ? 'approved ' : 'negados com sucesso.')->flash();

        return redirect()->route('admin.jexactyl.approvals');
    }

    /**
     * Approve an incoming approval request.
     */
    public function approve(Request $request, int $id): RedirectResponse
    {
        $user = User::where('id', $id)->first();
        $user->update(['approved' => true]);
        // This gives the user access to the frontend.

        $this->alert->success($user->username . ' Foi aprovado.')->flash();

        return redirect()->route('admin.jexactyl.approvals');
    }

    /**
     * Deny an incoming approval request.
     */
    public function deny(Request $request, int $id): RedirectResponse
    {
        $user = User::where('id', $id)->first();
        $user->delete();
        // While typically we should look for associated servers, there
        // shouldn't be any present - as the user has been waiting for approval.

        $this->alert->success($user->username . ' foi negado.')->flash();

        return redirect()->route('admin.jexactyl.approvals');
    }
}
