<?php

namespace App\Filters;

use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Advances batch schedule state on scanner and distribution requests.
 *
 * This is the whole trigger for automatic open and close. The deployment is a
 * laptop that travels to the venue, so a Windows scheduled task would have to
 * be registered again on every machine by whoever sets it up, and its absence
 * would be invisible until staff met a refusal at the venue. A filter ships in
 * the repository and cannot be left off a machine.
 *
 * The cost is that state advances only when a request arrives, which is
 * acceptable because a batch matters only when someone is using it, and
 * DistributionBatchModel::reconcileSchedule() computes the same closed_at
 * whenever it runs.
 */
class BatchScheduleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        model(DistributionBatchModel::class)->reconcileSchedule();

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
