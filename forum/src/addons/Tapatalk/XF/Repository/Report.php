<?php

namespace Tapatalk\XF\Repository;


class Report extends XFCP_Report
{

    /**
     * @param $reportId
     * @return \XF\Entity\Report
     */
    public function getReportById($reportId)
    {
        /** @var \XF\Entity\Report $report */
        $report = $this->finder('XF:Report')->whereId($reportId)->fetchOne();
        return $report;
    }

    /**
     * only content_type = post
     *
     * @param array $state
     * @return \XF\Finder\Report
     */
    public function getPostReports($state = ['open', 'assigned'], $timeFrame = null)
    {
        $finder = $this->finder('XF:Report');

        $finder->inTimeFrame($timeFrame)
            ->order('last_modified_date', 'desc');

        if ($state)
        {
            $finder->where('report_state', $state);
        }
        $finder->where('content_type', '=', 'post');

        return $finder;
    }


}