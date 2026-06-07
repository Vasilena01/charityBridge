<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Models\Campaign;
use App\Models\CampaignItem;

final class CampaignItemsController
{
    public function store(Request $request): void
    {
        $user       = Auth::requireRole('organizer');
        $campaignId = (int)$request->param('id');
        $campaign   = Campaign::findRaw($campaignId);

        if (!$campaign) {
            Response::notFound('Campaign not found');
        }
        if ((int)$campaign['organizer_id'] !== (int)$user['id']) {
            Response::forbidden('You can only add items to your own campaigns.');
        }

        $fields = self::validate($request, true);
        if (is_string($fields)) {
            Flash::set('error', $fields);
            Response::redirect(Url::to('campaigns/' . $campaignId . '/edit'));
        }

        CampaignItem::create(array_merge($fields, ['campaign_id' => $campaignId]));
        Flash::set('success', 'Item added.');
        Response::redirect(Url::to('campaigns/' . $campaignId . '/edit'));
    }

    public function update(Request $request): void
    {
        $user = Auth::requireRole('organizer');
        $id   = (int)$request->param('id');
        $item = CampaignItem::findWithOrganizer($id);

        if (!$item) {
            Response::notFound('Item not found');
        }
        if ((int)$item['organizer_id'] !== (int)$user['id']) {
            Response::forbidden('You can only edit items on your own campaigns.');
        }

        $fields = self::validate($request, false);
        if (is_string($fields)) {
            Flash::set('error', $fields);
            Response::redirect(Url::to('campaigns/' . $item['campaign_id'] . '/edit'));
        }

        CampaignItem::update($id, $fields);
        Flash::set('success', 'Item updated.');
        Response::redirect(Url::to('campaigns/' . $item['campaign_id'] . '/edit'));
    }

    public function destroy(Request $request): void
    {
        $user = Auth::requireRole('organizer');
        $id   = (int)$request->param('id');
        $item = CampaignItem::findWithOrganizer($id);

        if (!$item) {
            Response::notFound('Item not found');
        }
        if ((int)$item['organizer_id'] !== (int)$user['id']) {
            Response::forbidden('You can only delete items on your own campaigns.');
        }

        CampaignItem::delete($id);
        Flash::set('success', 'Item deleted.');
        Response::redirect(Url::to('campaigns/' . $item['campaign_id'] . '/edit'));
    }

    private static function validate(Request $request, bool $strict): array|string
    {
        $out = [];

        $name = trim((string)$request->input('name', ''));
        if ($strict || $request->input('name') !== null) {
            if ($name === '' || strlen($name) > 255) {
                return 'Item name is required (max 255 chars).';
            }
            $out['name'] = $name;
        }

        $desc = $request->input('description');
        if ($desc !== null) {
            $desc = trim((string)$desc);
            $out['description'] = $desc === '' ? null : $desc;
        }

        $type = (string)$request->input('item_type', 'good');
        if (!in_array($type, ['good', 'service'], true)) {
            return 'Item type must be "good" or "service".';
        }
        $out['item_type'] = $type;

        $cost = $request->input('production_cost');
        if (!is_numeric($cost) || (float)$cost < 0) {
            return 'Production cost must be a non-negative number.';
        }
        $out['production_cost'] = number_format((float)$cost, 2, '.', '');

        $don = $request->input('donation_amount');
        if (!is_numeric($don) || (float)$don < 0) {
            return 'Donation amount must be a non-negative number.';
        }
        $out['donation_amount'] = number_format((float)$don, 2, '.', '');

        if ((float)$out['production_cost'] + (float)$out['donation_amount'] <= 0) {
            return 'Total price (cost + donation) must be greater than zero.';
        }

        $qty = $request->input('quantity_available', 1);
        if (!is_numeric($qty) || (int)$qty < -1) {
            return 'Quantity must be -1 (unlimited) or a non-negative integer.';
        }
        $out['quantity_available'] = (int)$qty;

        $status = $request->input('status');
        if ($status !== null) {
            if (!in_array($status, ['active', 'sold_out', 'removed'], true)) {
                return 'Invalid status.';
            }
            $out['status'] = $status;
        }

        return $out;
    }
}
