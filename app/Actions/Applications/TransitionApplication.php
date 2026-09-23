<?php

namespace App\Actions\Applications;

use App\Business\Applications\ApplicationStatusValidator;
use App\Data\Application\TransitionApplicationData;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use App\Results\Application\ApplicationResult;
use Illuminate\Support\Facades\DB;

final class TransitionApplication
{
    public function __construct(private ApplicationStatusValidator $statusValidator) {}

    public function execute(User $actor, Application $application, TransitionApplicationData $data): ApplicationResult
    {
        $next = ApplicationStatus::tryFrom($data->status);
        if ($next === null) {
            return ApplicationResult::error(__('That application status is invalid.'));
        }

        return DB::transaction(function () use ($actor, $application, $data, $next): ApplicationResult {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            $isOwner = $locked->user_id === $actor->id;
            $isOperator = $actor->isOwner() || $actor->can('tenants.view');

            if (($next === ApplicationStatus::Withdrawn && ! $isOwner && ! $isOperator) || ($next !== ApplicationStatus::Withdrawn && ! $isOperator)) {
                return ApplicationResult::error(__('You cannot change this application.'));
            }

            if (! $this->statusValidator->canTransition($locked->status, $next)) {
                return ApplicationResult::error(__('That application status transition is not allowed.'));
            }

            $locked->update([
                'status' => $next,
                'open_application_key' => $next->isOpen() ? $locked->open_application_key : null,
                'operator_notes' => $isOperator ? $data->operatorNotes : $locked->operator_notes,
                'applicant_feedback' => $isOperator ? $data->applicantFeedback : $locked->applicant_feedback,
                'reviewed_by' => $isOperator ? $actor->id : $locked->reviewed_by,
                'reviewed_at' => $isOperator ? now() : $locked->reviewed_at,
            ]);

            return ApplicationResult::success($locked->fresh());
        });
    }
}
