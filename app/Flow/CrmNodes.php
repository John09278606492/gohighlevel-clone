<?php

namespace App\Flow;

use App\Flow\Actions\AddContactTag;
use App\Flow\Actions\CreateContactTask;
use App\Flow\Actions\CreateOpportunity;
use App\Flow\Actions\GrantCourseAccess;
use App\Flow\Actions\MoveOpportunityStage;
use App\Flow\Actions\RemoveContactTag;
use App\Flow\Actions\SendContactMessage;
use App\Flow\Actions\SendReviewRequest;
use App\Flow\Actions\UpdateContact;
use App\Flow\Triggers\CrmEvent;
use Packstub\Flow\NodeRegistry;

/**
 * The CRM's own workflow triggers and actions. They are registered at boot, not
 * only by the panel plugin, so workflows also run from public pages, webhooks,
 * the API and queued jobs.
 */
class CrmNodes
{
    public const TRIGGERS = [CrmEvent::class];

    public const ACTIONS = [
        SendContactMessage::class,
        CreateOpportunity::class,
        MoveOpportunityStage::class,
        AddContactTag::class,
        RemoveContactTag::class,
        CreateContactTask::class,
        UpdateContact::class,
        SendReviewRequest::class,
        GrantCourseAccess::class,
    ];

    public static function register(): void
    {
        app(NodeRegistry::class)->registerTriggers(self::TRIGGERS)->registerActions(self::ACTIONS);
    }
}
