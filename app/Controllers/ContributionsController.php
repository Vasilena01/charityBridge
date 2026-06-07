<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Models\Campaign;
use App\Models\Contribution;
use App\Models\User;

final class ContributionsController
{
    public function store(Request $request): void
    {
        $user       = Auth::require();
        $campaignId = (int)$request->param('id');

        if (!in_array($user['role'], ['volunteer', 'company'], true)) {
            Response::forbidden('Only volunteers and companies can contribute.');
        }

        $type = (string)$request->input('type', '');
        if (!in_array($type, ['monetary', 'hours', 'goods'], true)) {
            Flash::set('error', 'Contribution type must be monetary, hours, or goods.');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }

        $note = $request->input('note');
        $note = $note !== null ? trim((string)$note) : null;
        if ($note === '') {
            $note = null;
        }

        $campaign = Campaign::findRaw($campaignId);
        if (!$campaign) {
            Response::notFound('Campaign not found');
        }
        if (!Campaign::canActOn($campaign, (int)$user['id'])) {
            if ((int)$campaign['organizer_id'] === (int)$user['id']) {
                Response::forbidden('You cannot contribute to your own campaign.');
            }
            Flash::set('error', 'Campaign is not accepting contributions.');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }

        if ($type === 'monetary') {
            $this->createMonetary($campaignId, $user, $request, $note);
        } elseif ($type === 'hours') {
            $this->createHours($campaignId, $user, $request, $note);
        } else {
            $this->createGoods($campaignId, $user, $request, $note);
        }
    }

    private function createMonetary(int $campaignId, array $user, Request $request, ?string $note): void
    {
        $amount = $request->input('amount');
        if (!is_numeric($amount) || (float)$amount <= 0) {
            Flash::set('error', 'Amount must be a positive number.');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }
        $amount = round((float)$amount, 2);

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $balance = User::getBalance((int)$user['id']);
            if ($balance < $amount) {
                $pdo->rollBack();
                Flash::set('error', sprintf('Insufficient balance: need %.2f, have %.2f', $amount, $balance));
                Response::redirect(Url::to('campaigns/' . $campaignId));
            }

            User::setBalance((int)$user['id'], $balance - $amount);
            Campaign::addToCurrentAmount($campaignId, $amount);
            Contribution::createMonetary($campaignId, (int)$user['id'], $amount, $note);

            $pdo->commit();
            Auth::refresh();
            Flash::set('success', sprintf('Donated %.2f.', $amount));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        Response::redirect(Url::to('campaigns/' . $campaignId));
    }

    private function createHours(int $campaignId, array $user, Request $request, ?string $note): void
    {
        $hours = $request->input('hours_count');
        if (!is_numeric($hours) || (float)$hours <= 0 || (float)$hours > 10000) {
            Flash::set('error', 'Hours must be a positive number (max 10000).');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }
        Contribution::createHours($campaignId, (int)$user['id'], round((float)$hours, 2), $note);
        Flash::set('success', sprintf('Signed up for %.2f hours.', (float)$hours));
        Response::redirect(Url::to('campaigns/' . $campaignId));
    }

    private function createGoods(int $campaignId, array $user, Request $request, ?string $note): void
    {
        $desc = trim((string)$request->input('goods_description', ''));
        if ($desc === '' || strlen($desc) > 1000) {
            Flash::set('error', 'Goods description is required (max 1000 chars).');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }
        $est = $request->input('goods_estimated_value');
        if ($est !== null && $est !== '' && (!is_numeric($est) || (float)$est < 0)) {
            Flash::set('error', 'Estimated value must be a non-negative number.');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }
        $estValue = ($est === null || $est === '') ? null : round((float)$est, 2);

        Contribution::createGoods($campaignId, (int)$user['id'], $desc, $estValue, $note);
        Flash::set('success', 'Goods pledged.');
        Response::redirect(Url::to('campaigns/' . $campaignId));
    }

    public function cancel(Request $request): void
    {
        $user = Auth::require();
        $id   = (int)$request->param('id');
        $c    = Contribution::find($id);

        if (!$c) {
            Response::notFound('Contribution not found');
        }
        if ((int)$c['contributor_id'] !== (int)$user['id']) {
            Response::forbidden('You can only cancel your own contributions.');
        }
        if ($c['type'] === 'monetary') {
            Flash::set('error', 'Monetary donations cannot be cancelled.');
            Response::redirect(Url::to('profile'));
        }
        if ($c['status'] !== 'pending') {
            Flash::set('error', 'Only pending contributions can be cancelled.');
            Response::redirect(Url::to('profile'));
        }

        Contribution::cancel($id);
        Flash::set('success', 'Contribution cancelled.');
        Response::redirect(Url::to('profile'));
    }
}
