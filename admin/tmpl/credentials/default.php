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
use Joomla\CMS\Session\Session;

/** @var \Joomla\Component\Mcpserver\Administrator\View\Credentials\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip');
?>
<div class="container-fluid py-3">

    <?php if ($this->isCoreAdmin): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_SETUP_TITLE'); ?></h5>
            </div>
            <div class="card-body">
                <p class="text-muted small"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_SETUP_DESC'); ?></p>
                <dl class="row">
                    <dt class="col-sm-3"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_STATUS_LABEL'); ?></dt>
                    <dd class="col-sm-9">
                        <?php if ($this->governanceStatus['configured']): ?>
                            <span class="badge bg-success"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_STATUS_ACTIVE'); ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_STATUS_INACTIVE'); ?></span>
                        <?php endif; ?>
                    </dd>
                    <?php if ($this->governanceStatus['recovery_key_fingerprint'] !== null): ?>
                        <dt class="col-sm-3"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_FINGERPRINT_LABEL'); ?></dt>
                        <dd class="col-sm-9"><code><?php echo htmlspecialchars($this->governanceStatus['recovery_key_fingerprint'], ENT_QUOTES, 'UTF-8'); ?></code></dd>
                    <?php endif; ?>
                </dl>
                <form action="index.php?option=com_mcpserver&amp;task=credentials.setup" method="post">
                    <div class="mb-3">
                        <label class="form-label" for="metrics_retention_days"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_RETENTION_LABEL'); ?></label>
                        <input type="number" class="form-control" id="metrics_retention_days" name="metrics_retention_days" value="30" min="1" max="3650" style="max-width: 160px;">
                    </div>
                    <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_SETUP_BUTTON'); ?></button>
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($this->isAdmin): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_AUDIT_TITLE'); ?></h5>
            </div>
            <div class="card-body">
                <p class="text-muted small"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_AUDIT_DESC'); ?></p>
                <form action="index.php?option=com_mcpserver&amp;view=credentials" method="get" class="row gy-2 gx-2 align-items-end mb-3">
                    <input type="hidden" name="option" value="com_mcpserver">
                    <input type="hidden" name="view" value="credentials">
                    <div class="col-auto">
                        <label class="form-label" for="audit_user_id"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_AUDIT_FILTER_USER'); ?></label>
                        <input type="number" class="form-control" id="audit_user_id" name="audit_user_id" value="<?php echo $this->auditFilters['userId'] !== null ? (int) $this->auditFilters['userId'] : ''; ?>" min="1" style="max-width: 140px;">
                    </div>
                    <div class="col-auto">
                        <label class="form-label" for="audit_tool"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_AUDIT_FILTER_TOOL'); ?></label>
                        <input type="text" class="form-control" id="audit_tool" name="audit_tool_name" value="<?php echo htmlspecialchars((string) $this->auditFilters['toolName'], ENT_QUOTES, 'UTF-8'); ?>" style="max-width: 200px;">
                    </div>
                    <div class="col-auto">
                        <label class="form-label" for="audit_date_from"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_AUDIT_FILTER_FROM'); ?></label>
                        <input type="date" class="form-control" id="audit_date_from" name="audit_date_from" value="<?php echo htmlspecialchars((string) $this->auditFilters['dateFrom'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-auto">
                        <label class="form-label" for="audit_date_to"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_AUDIT_FILTER_TO'); ?></label>
                        <input type="date" class="form-control" id="audit_date_to" name="audit_date_to" value="<?php echo htmlspecialchars((string) $this->auditFilters['dateTo'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-secondary"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_AUDIT_FILTER_BUTTON'); ?></button>
                    </div>
                </form>

                <?php if (empty($this->auditRows)): ?>
                    <p class="text-muted mb-0"><?php echo Text::_('COM_MCPSERVER_DASHBOARD_NO_DATA'); ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_TIME'); ?></th>
                                    <th><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_METHOD'); ?></th>
                                    <th><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_TOOL'); ?></th>
                                    <th><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_STATUS'); ?></th>
                                    <th><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_AUDIT_COL_USER'); ?></th>
                                    <th><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_AUDIT_COL_TARGET'); ?></th>
                                    <th><?php echo Text::_('COM_MCPSERVER_DASHBOARD_COL_IP'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->auditRows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) ($row['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['method'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['tool_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['user_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><code><?php echo htmlspecialchars((string) ($row['target'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                        <td><?php echo htmlspecialchars((string) ($row['client_ip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($this->isCoreAdmin): ?>
                    <hr>
                    <p class="text-muted small"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_AUDIT_PRUNE_DESC'); ?></p>
                    <form action="index.php?option=com_mcpserver&amp;task=credentials.prune" method="post" class="d-flex gap-2 align-items-end" onsubmit="return confirm(<?php echo json_encode(Text::_('COM_MCPSERVER_GOVERNANCE_AUDIT_PRUNE_CONFIRM')); ?>);">
                        <div>
                            <label class="form-label" for="prune_retention_days"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_RETENTION_LABEL'); ?></label>
                            <input type="number" class="form-control" id="prune_retention_days" name="prune_retention_days" value="30" min="1" max="3650" style="max-width: 160px;">
                        </div>
                        <button type="submit" class="btn btn-outline-danger"><?php echo Text::_('COM_MCPSERVER_GOVERNANCE_AUDIT_PRUNE_BUTTON'); ?></button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$this->governedConfigured): ?>
        <div class="alert alert-warning" role="alert">
            <?php echo Text::_('COM_MCPSERVER_CREDENTIALS_NOT_CONFIGURED'); ?>
        </div>
    <?php else: ?>

        <?php if ($this->justIssued !== null): ?>
            <div class="alert alert-success" role="alert">
                <h5 class="alert-heading"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_ISSUED_TITLE'); ?></h5>
                <p><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_ISSUED_WARNING'); ?></p>
                <dl class="row mb-0">
                    <dt class="col-sm-2"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_ISSUED_ID'); ?></dt>
                    <dd class="col-sm-10"><code><?php echo htmlspecialchars((string) $this->justIssued['id'], ENT_QUOTES, 'UTF-8'); ?></code></dd>
                    <dt class="col-sm-2"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_ISSUED_TOKEN'); ?></dt>
                    <dd class="col-sm-10"><code><?php echo htmlspecialchars((string) $this->justIssued['bearer_token'], ENT_QUOTES, 'UTF-8'); ?></code></dd>
                </dl>
            </div>
        <?php endif; ?>

        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_CREATE_TITLE'); ?></h5>
            </div>
            <div class="card-body">
                <form action="index.php?option=com_mcpserver&amp;task=credentials.create" method="post">
                    <div class="mb-3">
                        <label class="form-label" for="api_token"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_API_TOKEN_LABEL'); ?></label>
                        <input type="password" class="form-control" id="api_token" name="api_token" required autocomplete="off">
                        <div class="form-text"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_API_TOKEN_DESC'); ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="expires_days"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_EXPIRES_DAYS_LABEL'); ?></label>
                        <input type="number" class="form-control" id="expires_days" name="expires_days" value="30" min="1" max="3650" style="max-width: 160px;">
                    </div>
                    <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_CREATE_BUTTON'); ?></button>
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
            </div>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_LIST_TITLE'); ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($this->credentials)): ?>
                    <p class="text-muted p-3 mb-0"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_NO_DATA'); ?></p>
                <?php else: ?>
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_COL_NAME'); ?></th>
                                <th><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_COL_SELECTOR'); ?></th>
                                <th><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_COL_CREATED'); ?></th>
                                <th><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_COL_EXPIRES'); ?></th>
                                <th><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_COL_STATUS'); ?></th>
                                <th class="pe-3"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_COL_ACTIONS'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->credentials as $credential): ?>
                                <tr>
                                    <td class="ps-3"><?php echo htmlspecialchars($credential['owner_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><code><?php echo htmlspecialchars($credential['selector'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    <td><?php echo htmlspecialchars(HTMLHelper::_('date', $credential['created_at'], 'Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(HTMLHelper::_('date', $credential['expires_at'], 'Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if ($credential['revoked']): ?>
                                            <span class="badge bg-secondary"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_STATUS_REVOKED'); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_STATUS_ACTIVE'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-3">
                                        <?php if (!$credential['revoked']): ?>
                                            <form action="index.php?option=com_mcpserver&amp;task=credentials.revoke" method="post" onsubmit="return confirm(<?php echo json_encode(Text::_('COM_MCPSERVER_CREDENTIALS_REVOKE_CONFIRM')); ?>);">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $credential['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_REVOKE_BUTTON'); ?></button>
                                                <?php echo HTMLHelper::_('form.token'); ?>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($this->isAdmin): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_ADMIN_REVOKE_TITLE'); ?></h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_ADMIN_NOTE'); ?></p>
                    <form action="index.php?option=com_mcpserver&amp;task=credentials.revoke" method="post" class="d-flex gap-2" onsubmit="return confirm(<?php echo json_encode(Text::_('COM_MCPSERVER_CREDENTIALS_REVOKE_CONFIRM')); ?>);">
                        <label class="visually-hidden" for="admin_revoke_id"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_ADMIN_REVOKE_ID_LABEL'); ?></label>
                        <input type="text" class="form-control" id="admin_revoke_id" name="id" placeholder="<?php echo Text::_('COM_MCPSERVER_CREDENTIALS_ADMIN_REVOKE_ID_LABEL'); ?>" style="max-width: 240px;" required>
                        <button type="submit" class="btn btn-outline-danger"><?php echo Text::_('COM_MCPSERVER_CREDENTIALS_REVOKE_BUTTON'); ?></button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
