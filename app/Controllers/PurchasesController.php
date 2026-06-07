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
use App\Models\Purchase;
use App\Models\User;

final class PurchasesController
{
    public function store(Request $request): void
    {
        $user   = Auth::require();
        $itemId = (int)$request->param('id');

        if (!in_array($user['role'], ['volunteer', 'company'], true)) {
            Response::forbidden('Only volunteers and companies can purchase items.');
        }

        $maxQty   = (int)env('MAX_QUANTITY_PER_PURCHASE', 100);
        $quantity = (int)$request->input('quantity', 0);
        if ($quantity <= 0 || $quantity > $maxQty) {
            Flash::set('error', "Quantity must be between 1 and $maxQty.");
            Response::redirect(Url::to(''));
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT i.id, i.campaign_id, i.production_cost, i.donation_amount,
                        i.quantity_available, i.quantity_sold, i.status,
                        c.organizer_id, c.status AS campaign_status
                 FROM campaign_items i
                 JOIN campaigns c ON c.id = i.campaign_id
                 WHERE i.id = :id'
            );
            $stmt->execute(['id' => $itemId]);
            $item = $stmt->fetch();

            if (!$item) {
                $pdo->rollBack();
                Response::notFound('Item not found');
            }
            if ($item['status'] !== 'active') {
                $pdo->rollBack();
                Flash::set('error', 'Item is not available for purchase.');
                Response::redirect(Url::to('campaigns/' . $item['campaign_id']));
            }

            $campaignForAccess = [
                'id' => (int)$item['campaign_id'],
                'organizer_id' => $item['organizer_id'],
                'status' => $item['campaign_status'],
            ];
            if (!Campaign::canActOn($campaignForAccess, (int)$user['id'])) {
                $pdo->rollBack();
                if ((int)$item['organizer_id'] === (int)$user['id']) {
                    Response::forbidden('Organizers cannot purchase items from their own campaign.');
                }
                Flash::set('error', 'This campaign is not currently accepting purchases.');
                Response::redirect(Url::to('campaigns/' . $item['campaign_id']));
            }

            $qtyAvailable = (int)$item['quantity_available'];
            $qtySold      = (int)$item['quantity_sold'];
            $remaining    = $qtyAvailable === -1 ? PHP_INT_MAX : ($qtyAvailable - $qtySold);

            if ($quantity > $remaining) {
                $pdo->rollBack();
                Flash::set('error', "Only $remaining item(s) remaining.");
                Response::redirect(Url::to('campaigns/' . $item['campaign_id']));
            }

            $unitCost     = (float)$item['production_cost'];
            $unitDonation = (float)$item['donation_amount'];
            $totalCost    = $unitCost * $quantity;
            $totalDonation = $unitDonation * $quantity;
            $totalPaid    = $totalCost + $totalDonation;

            $balance = User::getBalance((int)$user['id']);
            if ($balance < $totalPaid) {
                $pdo->rollBack();
                Flash::set('error', sprintf('Insufficient balance: need %.2f, have %.2f', $totalPaid, $balance));
                Response::redirect(Url::to('campaigns/' . $item['campaign_id']));
            }

            User::setBalance((int)$user['id'], $balance - $totalPaid);

            $newSold   = $qtySold + $quantity;
            $newStatus = ($qtyAvailable !== -1 && $newSold >= $qtyAvailable) ? 'sold_out' : 'active';
            CampaignItem::recordSale($itemId, $newSold, $newStatus);
            Campaign::addToCurrentAmount((int)$item['campaign_id'], $totalDonation);

            Purchase::create([
                'item_id'        => $itemId,
                'buyer_id'       => (int)$user['id'],
                'campaign_id'    => (int)$item['campaign_id'],
                'quantity'       => $quantity,
                'unit_cost'      => $unitCost,
                'unit_donation'  => $unitDonation,
                'total_paid'     => $totalPaid,
                'total_donation' => $totalDonation,
            ]);

            $pdo->commit();
            Auth::refresh();

            Flash::set('success', sprintf('Purchase complete. %.2f donated to the campaign.', $totalDonation));
            Response::redirect(Url::to('campaigns/' . $item['campaign_id']));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
