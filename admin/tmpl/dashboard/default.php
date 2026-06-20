<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Joomla\Component\Mcpserver\Administrator\View\Dashboard\HtmlView $this */

$summary = $this->summary;

// Largest daily count drives the chart's vertical scale.
$maxDay = 0;
foreach ($this->perDay as $point) {
    $maxDay = max($maxDay, (int) $point['count']);
}

$statusBadge = static function (string $status): string {
    switch ($status) {
        case 'ok':
            return 'bg-success';
        case 'error':
            return 'bg-danger';
        case 'rate_limited':
            return 'bg-warning text-dark';
        case 'auth_failed':
            return 'bg-secondary';
        case 'invalid_request':
            return 'bg-info text-dark';
        default:
            return 'bg-light text-dark';
    }
};

$cards = [
    ['label' => Text::_('COM_MCPSERVER_DASHBOARD_CARD_TOTAL'),        'value' => number_format((int) $summary['total'])],
    ['label' => Text::_('COM_MCPSERVER_DASHBOARD_CARD_24H'),          'value' => number_format((int) $summary['last_24h'])],
    ['label' => Text::_('COM_MCPSERVER_DASHBOARD_CARD_7D'),           'value' => number_format((int) $summary['last_7d'])],
    ['label' => Text::_('COM_MCPSERVER_DASHBOARD_CARD_ERROR_RATE'),   'value' => (float) $summary['error_rate'] . '%'],
    ['label' => Text::_('COM_MCPSERVER_DASHBOARD_CARD_AVG_LATENCY'),  'value' => number_format((float) $summary['avg_latency_ms'], 1)],
    ['label' => Text::_('COM_MCPSERVER_DASHBOARD_CARD_RATE_LIMITED'), 'value' => number_format((int) $summary['rate_limited'])],
    ['label' => Text::_('COM_MCPSERVER_DASHBOARD_CARD_AUTH_FAILED'),  'value' => number_format((int) $summary['auth_failed'])],
];
?>
<div class="container-fluid py-3">

    <div class="card border-warning shadow-sm mb-4" id="mcpserverStarBanner" hidden>
        <button type="button" class="btn-close position-absolute top-0 end-0 m-2" id="mcpserverStarDismiss" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
        <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="icon-star text-warning fs-2" aria-hidden="true"></span>
                <div>
                    <h5 class="mb-1"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_STAR_TITLE'); ?></h5>
                    <p class="mb-0 text-muted"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_STAR_TEXT'); ?></p>
                </div>
            </div>
            <a class="btn btn-warning text-nowrap me-4" href="https://github.com/OnepointConsultingLtd/joomla-mcp-server" target="_blank" rel="noopener noreferrer">
                <span class="icon-star" aria-hidden="true"></span>
                <?php echo Text::_('COM_MCPSERVER_DASHBOARD_STAR_BUTTON'); ?>
            </a>
        </div>
    </div>
    <script>
        (function () {
            var key = 'mcpserver.starBannerDismissed';
            var banner = document.getElementById('mcpserverStarBanner');
            if (!banner) {
                return;
            }
            try {
                if (window.localStorage.getItem(key) !== '1') {
                    banner.hidden = false;
                }
            } catch (e) {
                banner.hidden = false;
            }
            document.getElementById('mcpserverStarDismiss').addEventListener('click', function () {
                banner.hidden = true;
                try {
                    window.localStorage.setItem(key, '1');
                } catch (e) {}
            });
        })();
    </script>

    <?php if (!$this->metricsEnabled) : ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center" role="alert">
            <span><?php echo Text::_('COM_MCPSERVER_DASHBOARD_METRICS_DISABLED'); ?></span>
            <a class="btn btn-sm btn-primary" href="index.php?option=com_config&amp;view=component&amp;component=com_mcpserver">
                <span class="icon-options" aria-hidden="true"></span>
                <?php echo Text::_('JOPTIONS'); ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="row row-cols-2 row-cols-md-4 row-cols-xl-7 g-3 mb-4">
        <?php foreach ($cards as $card) : ?>
            <div class="col">
                <div class="card shadow-sm h-100 text-center">
                    <div class="card-body py-3">
                        <div class="fs-3 fw-bold"><?php echo htmlspecialchars((string) $card['value']); ?></div>
                        <div class="text-muted small"><?php echo htmlspecialchars($card['label']); ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_REQUESTS_PER_DAY'); ?></h5>
        </div>
        <div class="card-body">
            <?php if ($maxDay === 0) : ?>
                <p class="text-muted mb-0"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_NO_DATA'); ?></p>
            <?php else : ?>
                <div class="d-flex align-items-end justify-content-between" style="height: 180px; gap: 4px;">
                    <?php foreach ($this->perDay as $point) :
                        $count   = (int) $point['count'];
                        $errors  = (int) $point['errors'];
                        $ok      = max(0, $count - $errors);
                        $heightPct = $maxDay > 0 ? ($count / $maxDay) * 100 : 0;
                        $okPct     = $count > 0 ? ($ok / $count) * 100 : 0;
                        $errPct    = $count > 0 ? ($errors / $count) * 100 : 0;
                        $title     = $point['day'] . ': ' . $count . ' (' . $errors . ' err)';
                        ?>
                        <div class="d-flex flex-column justify-content-end align-items-center" style="flex: 1 1 0; height: 100%;" title="<?php echo htmlspecialchars($title); ?>">
                            <div class="w-100 d-flex flex-column justify-content-end" style="height: <?php echo round($heightPct, 2); ?>%; min-height: 2px;">
                                <?php if ($errors > 0) : ?>
                                    <div class="bg-danger" style="height: <?php echo round($errPct, 2); ?>%;"></div>
                                <?php endif; ?>
                                <div class="bg-primary" style="height: <?php echo round($okPct, 2); ?>%;"></div>
                            </div>
                            <div class="text-muted mt-1" style="font-size: 0.65rem;"><?php echo htmlspecialchars(substr((string) $point['day'], 5)); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex gap-3 mt-3 small text-muted">
                    <span><span class="badge bg-primary">&nbsp;</span> <?php echo Text::_('COM_MCPSERVER_DASHBOARD_LEGEND_OK'); ?></span>
                    <span><span class="badge bg-danger">&nbsp;</span> <?php echo Text::_('COM_MCPSERVER_DASHBOARD_LEGEND_ERRORS'); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header"><h5 class="mb-0"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_TOP_TOOLS'); ?></h5></div>
                <div class="card-body p-0">
                    <?php if (empty($this->topTools)) : ?>
                        <p class="text-muted m-3"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_NO_DATA'); ?></p>
                    <?php else : ?>
                        <table class="table table-hover mb-0">
                            <thead><tr><th class="ps-3"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_TOOL'); ?></th><th class="text-end pe-3"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COUNT'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($this->topTools as $row) : ?>
                                <tr>
                                    <td class="ps-3"><code><?php echo htmlspecialchars((string) $row['tool_name']); ?></code></td>
                                    <td class="text-end pe-3"><?php echo number_format((int) $row['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header"><h5 class="mb-0"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_TOP_METHODS'); ?></h5></div>
                <div class="card-body p-0">
                    <?php if (empty($this->topMethods)) : ?>
                        <p class="text-muted m-3"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_NO_DATA'); ?></p>
                    <?php else : ?>
                        <table class="table table-hover mb-0">
                            <thead><tr><th class="ps-3"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_METHOD'); ?></th><th class="text-end pe-3"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COUNT'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($this->topMethods as $row) : ?>
                                <tr>
                                    <td class="ps-3"><code><?php echo htmlspecialchars((string) $row['method']); ?></code></td>
                                    <td class="text-end pe-3"><?php echo number_format((int) $row['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_RECENT'); ?></h5></div>
        <div class="card-body p-0">
            <?php if (empty($this->recent)) : ?>
                <p class="text-muted m-3"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_NO_DATA'); ?></p>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="ps-3"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_TIME'); ?></th>
                                <th><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_CONTEXT'); ?></th>
                                <th><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_METHOD'); ?></th>
                                <th><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_TOOL'); ?></th>
                                <th><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_STATUS'); ?></th>
                                <th class="text-end"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_HTTP'); ?></th>
                                <th class="text-end"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_DURATION'); ?></th>
                                <th class="pe-3"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_IP'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->recent as $row) : ?>
                            <tr>
                                <td class="ps-3 text-nowrap"><?php echo htmlspecialchars(HTMLHelper::_('date', $row->created, 'Y-m-d H:i:s')); ?></td>
                                <td><?php echo htmlspecialchars((string) $row->context); ?></td>
                                <td><code><?php echo htmlspecialchars((string) $row->method); ?></code></td>
                                <td><?php echo $row->tool_name !== '' ? '<code>' . htmlspecialchars((string) $row->tool_name) . '</code>' : '<span class="text-muted">&mdash;</span>'; ?></td>
                                <td><span class="badge <?php echo $statusBadge((string) $row->status); ?>"><?php echo htmlspecialchars((string) $row->status); ?></span></td>
                                <td class="text-end"><?php echo (int) $row->http_status; ?></td>
                                <td class="text-end"><?php echo (int) $row->duration_ms; ?></td>
                                <td class="pe-3"><?php echo htmlspecialchars((string) $row->client_ip); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
