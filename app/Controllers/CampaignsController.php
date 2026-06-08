<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Core\View;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\Contribution;
use App\Models\ProductionOffer;

final class CampaignsController
{
    public function index(Request $request): void
    {
        $type      = (string)$request->input('type', '');
        $search    = (string)$request->input('search', '');
        $campaigns = Campaign::listPublished($type ?: null, $search ?: null);

        Response::html(View::render('campaigns/index', [
            'title'     => 'Browse Campaigns - CharityBridge',
            'css'       => ['campaigns.css'],
            'campaigns' => $campaigns,
            'filters'   => ['type' => $type, 'search' => $search],
        ]));
    }

    public function show(Request $request): void
    {
        $id       = (int)$request->param('id');
        $campaign = Campaign::find($id);
        $user     = Auth::user();
        $userId   = $user ? (int)$user['id'] : null;

        if (!$campaign) {
            Response::notFound('Campaign not found');
        }
        if (!Campaign::canView($campaign, $userId)) {
            Response::forbidden($campaign['status'] === 'draft' ? 'This campaign is not yet published' : 'This campaign is not accessible');
        }

        $items   = CampaignItem::listForCampaign($id);
        $summary = Contribution::summaryForCampaign($id);
        $isOwner = $userId !== null && (int)$campaign['organizer_id'] === $userId;

        Response::html(View::render('campaigns/show', [
            'title'     => e($campaign['title']) . ' - CharityBridge',
            'css'       => ['campaign-detail.css'],
            'campaign'  => $campaign,
            'items'     => $items,
            'summary'   => $summary,
            'is_owner'  => $isOwner,
        ]));
    }

    public function create(Request $request): void
    {
        Auth::requireRole('organizer');
        Response::html(View::render('campaigns/create', [
            'title'  => 'Create Campaign - CharityBridge',
            'css'    => ['create-campaign.css'],
            'errors' => [],
            'old'    => [],
        ]));
    }

    public function store(Request $request): void
    {
        $user = Auth::requireRole('organizer');

        $title       = trim((string)$request->input('title', ''));
        $description = trim((string)$request->input('description', ''));
        $type        = (string)$request->input('campaign_type', '');
        $goalRaw     = (string)$request->input('goal_amount', '');
        $deadline    = (string)$request->input('deadline', '');

        $errors = [];
        if ($title === '')                                     $errors[] = 'Title is required.';
        if ($description === '')                               $errors[] = 'Description is required.';
        if ($type === '')                                      $errors[] = 'Campaign type is required.';
        if ($deadline === '')                                  $errors[] = 'Deadline is required.';

        $goal = 1.0;
        if (!in_array($type, ['volunteer', 'goods'], true)) {
            if (!is_numeric($goalRaw) || (float)$goalRaw <= 0) {
                $errors[] = 'Goal amount must be a positive number.';
            } else {
                $goal = (float)$goalRaw;
            }
        }

        $deadlineTs = strtotime($deadline);
        if (!$deadlineTs || $deadlineTs <= time()) {
            $errors[] = 'Deadline must be in the future.';
        }

        if ($errors) {
            Response::html(View::render('campaigns/create', [
                'title'  => 'Create Campaign - CharityBridge',
                'css'    => ['create-campaign.css'],
                'errors' => $errors,
                'old'    => [
                    'title' => $title, 'description' => $description,
                    'campaign_type' => $type, 'goal_amount' => $goalRaw, 'deadline' => $deadline,
                ],
            ]));
            return;
        }

        $campaignId = Campaign::create([
            'organizer_id'  => (int)$user['id'],
            'title'         => $title,
            'description'   => $description,
            'campaign_type' => $type,
            'goal_amount'   => $goal,
            'deadline'      => date('Y-m-d H:i:s', $deadlineTs),
            'status'        => 'published',
        ]);

        Flash::set('success', 'Campaign published.');
        Response::redirect(Url::to('campaigns/' . $campaignId . '/edit'));
    }

    public function edit(Request $request): void
    {
        $user     = Auth::requireRole('organizer');
        $id       = (int)$request->param('id');
        $campaign = Campaign::find($id);

        if (!$campaign) {
            Response::notFound('Campaign not found');
        }
        if ((int)$campaign['organizer_id'] !== (int)$user['id']) {
            Response::forbidden('You can only edit your own campaigns.');
        }

        $items         = CampaignItem::listForCampaign($id);
        $offers        = ProductionOffer::listForCampaign($id);
        $contributions = Contribution::listForCampaign($id);

        Response::html(View::render('campaigns/edit', [
            'title'         => 'Edit Campaign - CharityBridge',
            'css'           => ['create-campaign.css'],
            'campaign'      => $campaign,
            'items'         => $items,
            'offers'        => $offers,
            'contributions' => $contributions,
            'errors'        => [],
        ]));
    }

    public function update(Request $request): void
    {
        $user     = Auth::requireRole('organizer');
        $id       = (int)$request->param('id');
        $campaign = Campaign::findRaw($id);

        if (!$campaign) {
            Response::notFound('Campaign not found');
        }
        if ((int)$campaign['organizer_id'] !== (int)$user['id']) {
            Response::forbidden('You can only edit your own campaigns.');
        }

        $title       = trim((string)$request->input('title', ''));
        $description = trim((string)$request->input('description', ''));
        $type        = (string)$request->input('campaign_type', '');
        $goalRaw     = (string)$request->input('goal_amount', '');
        $deadline    = (string)$request->input('deadline', '');

        $errors = [];
        if ($title === '')                                     $errors[] = 'Title is required.';
        if ($description === '')                               $errors[] = 'Description is required.';
        if ($type === '')                                      $errors[] = 'Campaign type is required.';
        if ($deadline === '')                                  $errors[] = 'Deadline is required.';

        $goal = 1.0;
        if (!in_array($type, ['volunteer', 'goods'], true)) {
            if (!is_numeric($goalRaw) || (float)$goalRaw <= 0) {
                $errors[] = 'Goal amount must be a positive number.';
            } else {
                $goal = (float)$goalRaw;
            }
        }

        $deadlineTs = strtotime($deadline);
        if (!$deadlineTs) {
            $errors[] = 'Deadline is required.';
        }

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            Response::redirect(Url::to('campaigns/' . $id . '/edit'));
        }

        $fields = [
            'title'         => $title,
            'description'   => $description,
            'campaign_type' => $type,
            'goal_amount'   => $goal,
            'deadline'      => date('Y-m-d H:i:s', $deadlineTs),
        ];

        Campaign::update($id, $fields);
        Flash::set('success', 'Campaign saved.');
        Response::redirect(Url::to('campaigns/' . $id . '/edit'));
    }

    public function destroy(Request $request): void
    {
        $user     = Auth::requireRole('organizer');
        $id       = (int)$request->param('id');
        $campaign = Campaign::findRaw($id);

        if (!$campaign) {
            Response::notFound('Campaign not found');
        }
        if ((int)$campaign['organizer_id'] !== (int)$user['id']) {
            Response::forbidden('You can only delete your own campaigns.');
        }

        Campaign::delete($id);
        Flash::set('success', 'Campaign deleted.');
        Response::redirect(Url::to('my-campaigns'));
    }

    public function my(Request $request): void
    {
        $user      = Auth::requireRole('organizer');
        $campaigns = Campaign::listForOrganizer((int)$user['id']);

        Response::html(View::render('campaigns/my', [
            'title'     => 'My Campaigns - CharityBridge',
            'css'       => ['my-campaigns.css'],
            'campaigns' => $campaigns,
        ]));
    }
}
