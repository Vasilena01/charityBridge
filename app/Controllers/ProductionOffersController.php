<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\ProductionOffer;

final class ProductionOffersController
{
    public function store(Request $request): void
    {
        $user       = Auth::require();
        $campaignId = (int)$request->param('id');

        if (!in_array($user['role'], ['volunteer', 'company'], true)) {
            Response::forbidden('Only volunteers and companies can offer to produce items.');
        }

        $campaign = Campaign::findRaw($campaignId);
        if (!$campaign) {
            Response::notFound('Campaign not found');
        }
        if (!Campaign::canActOn($campaign, (int)$user['id'])) {
            if ((int)$campaign['organizer_id'] === (int)$user['id']) {
                Response::forbidden('You cannot offer to produce for your own campaign.');
            }
            Flash::set('error', 'Cannot offer to produce for an unpublished campaign.');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }

        $name = trim((string)$request->input('name', ''));
        if ($name === '' || strlen($name) > 255) {
            Flash::set('error', 'Item name is required (max 255 chars).');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }
        $type = (string)$request->input('item_type', 'good');
        if (!in_array($type, ['good', 'service'], true)) {
            Flash::set('error', 'Item type must be "good" or "service".');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }
        $cost = $request->input('proposed_production_cost');
        if (!is_numeric($cost) || (float)$cost < 0) {
            Flash::set('error', 'Production cost must be a non-negative number.');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }
        $donation = $request->input('proposed_donation_amount');
        if (!is_numeric($donation) || (float)$donation < 0) {
            Flash::set('error', 'Donation must be a non-negative number.');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }
        if ((float)$cost + (float)$donation <= 0) {
            Flash::set('error', 'Total price (cost + donation) must be greater than zero.');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }
        $qty = $request->input('quantity_offered', 1);
        if (!is_numeric($qty) || (int)$qty < 1 || (int)$qty > 1000) {
            Flash::set('error', 'Quantity must be between 1 and 1000.');
            Response::redirect(Url::to('campaigns/' . $campaignId));
        }
        $desc = trim((string)$request->input('description', ''));

        ProductionOffer::create([
            'campaign_id' => $campaignId,
            'producer_id' => (int)$user['id'],
            'name'        => $name,
            'description' => $desc === '' ? null : $desc,
            'item_type'   => $type,
            'proposed_production_cost' => number_format((float)$cost, 2, '.', ''),
            'proposed_donation_amount' => number_format((float)$donation, 2, '.', ''),
            'quantity_offered' => (int)$qty,
        ]);

        Flash::set('success', 'Offer submitted.');
        Response::redirect(Url::to('campaigns/' . $campaignId));
    }

    public function decide(Request $request): void
    {
        $user   = Auth::require();
        $offerId = (int)$request->param('id');
        $action  = (string)$request->input('action', '');

        if (!in_array($action, ['accept', 'reject', 'cancel'], true)) {
            Flash::set('error', 'Action must be accept, reject, or cancel.');
            Response::redirect(Url::to('my-campaigns'));
        }

        $offer = ProductionOffer::findWithCampaign($offerId);
        if (!$offer) {
            Response::notFound('Offer not found');
        }
        if ($offer['status'] !== 'pending') {
            Flash::set('error', 'This offer has already been decided.');
            Response::redirect(Url::to('campaigns/' . $offer['campaign_id'] . '/edit'));
        }

        if ($action === 'cancel') {
            if ((int)$offer['producer_id'] !== (int)$user['id']) {
                Response::forbidden('Only the producer can cancel their offer.');
            }
            ProductionOffer::setCancelled($offerId);
            Flash::set('success', 'Offer cancelled.');
            Response::redirect(Url::to('profile'));
        }

        if ((int)$offer['organizer_id'] !== (int)$user['id']) {
            Response::forbidden('Only the campaign organizer can decide this offer.');
        }

        if ($action === 'reject') {
            $note = $request->input('organizer_note');
            $note = $note !== null ? trim((string)$note) : null;
            ProductionOffer::setRejected($offerId, $note ?: null);
            Flash::set('success', 'Offer rejected.');
            Response::redirect(Url::to('campaigns/' . $offer['campaign_id'] . '/edit'));
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $itemId = CampaignItem::create([
                'campaign_id'        => (int)$offer['campaign_id'],
                'producer_id'        => (int)$offer['producer_id'],
                'name'               => $offer['name'],
                'description'        => $offer['description'],
                'item_type'          => $offer['item_type'],
                'production_cost'    => $offer['proposed_production_cost'],
                'donation_amount'    => $offer['proposed_donation_amount'],
                'quantity_available' => (int)$offer['quantity_offered'],
            ]);
            ProductionOffer::setAccepted($offerId, $itemId);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        Flash::set('success', 'Offer accepted.');
        Response::redirect(Url::to('campaigns/' . $offer['campaign_id'] . '/edit'));
    }
}
